<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GenerationBatch;
use App\Models\IdRecord;
use App\Models\Template;
use App\Services\Print\PaperSize;
use App\Services\Print\PdfGenerator;
use App\Services\Print\PrintLayoutCalculator;
use App\Support\RecordSort;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class GenerationBatchController extends Controller
{
    private const PRINT_CONFIG_RULES = [
        'print_config' => ['required', 'array'],
        'print_config.paper' => ['required', 'string', 'in:A4,Letter,Legal,A5,Custom'],
        'print_config.custom_width' => ['required_if:print_config.paper,Custom', 'nullable', 'numeric', 'gt:0'],
        'print_config.custom_height' => ['required_if:print_config.paper,Custom', 'nullable', 'numeric', 'gt:0'],
        'print_config.orientation' => ['required', 'string', 'in:portrait,landscape'],
        'print_config.margin_top' => ['required', 'numeric', 'min:0'],
        'print_config.margin_bottom' => ['required', 'numeric', 'min:0'],
        'print_config.margin_left' => ['required', 'numeric', 'min:0'],
        'print_config.margin_right' => ['required', 'numeric', 'min:0'],
        'print_config.spacing_x' => ['required', 'numeric', 'min:0'],
        'print_config.spacing_y' => ['required', 'numeric', 'min:0'],
        'print_config.columns' => ['nullable', 'integer', 'min:1'],
        'print_config.rows' => ['nullable', 'integer', 'min:1'],
        'print_config.side' => ['required', 'string', 'in:front,back,both'],
    ];

    public function index()
    {
        return GenerationBatch::query()
            ->with('template:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (GenerationBatch $batch) => $this->present($batch));
    }

    public function show(GenerationBatch $generationBatch)
    {
        return $this->present($generationBatch->load('template:id,name'));
    }

    public function layoutPreview(Request $request)
    {
        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:templates,id'],
            ...self::PRINT_CONFIG_RULES,
        ]);

        $template = Template::findOrFail($validated['template_id']);
        $layout = $this->calculateLayout($template, $validated['print_config']);

        return response()->json($layout);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'template_id' => ['required', 'integer', 'exists:templates,id'],
            'record_ids' => ['required', 'array', 'min:1'],
            'record_ids.*' => ['integer', 'exists:id_records,id'],
            'sort_by' => ['nullable', 'string'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
            ...self::PRINT_CONFIG_RULES,
        ]);

        $template = Template::findOrFail($validated['template_id']);

        // Print order is resolved from the requested sort *now*, not
        // trusted from the order record_ids happened to arrive in - a
        // selection built while one sort was active (e.g. "select all
        // matching") doesn't retroactively reorder itself if the user
        // then switches to a different sort before generating.
        $records = $this->orderedRecords($validated['record_ids'], $validated['sort_by'] ?? null, $validated['sort_dir'] ?? null);
        $resolvedIds = $records->pluck('id')->all();

        $batch = GenerationBatch::create([
            'name' => $validated['name'],
            'template_id' => $template->id,
            'record_ids' => $resolvedIds,
            'print_config' => $validated['print_config'],
        ]);

        try {
            $path = PdfGenerator::generate($template, $records, $validated['print_config']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $batch->update(['pdf_path' => $path, 'generated_at' => now()]);

        return response()->json($this->present($batch->fresh()->load('template:id,name')), 201);
    }

    public function regenerate(GenerationBatch $generationBatch)
    {
        $template = $generationBatch->template;
        $records = $this->orderedRecords($generationBatch->record_ids);

        if ($records->isEmpty()) {
            return response()->json(['message' => "None of this batch's records exist anymore."], 422);
        }

        try {
            $path = PdfGenerator::generate($template, $records, $generationBatch->print_config);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $generationBatch->update(['pdf_path' => $path, 'generated_at' => now()]);

        return $this->present($generationBatch->fresh()->load('template:id,name'));
    }

    public function destroy(GenerationBatch $generationBatch)
    {
        if ($generationBatch->pdf_path) {
            Storage::disk('public')->delete($generationBatch->pdf_path);
        }
        $generationBatch->delete();

        return response()->noContent();
    }

    private function calculateLayout(Template $template, array $printConfig): array
    {
        [$paperWidth, $paperHeight] = PaperSize::dimensions(
            $printConfig['paper'],
            $printConfig['custom_width'] ?? null,
            $printConfig['custom_height'] ?? null,
            $printConfig['orientation'] ?? 'portrait',
        );

        $layout = PrintLayoutCalculator::calculate(
            paperWidth: $paperWidth,
            paperHeight: $paperHeight,
            cardWidth: (float) $template->width,
            cardHeight: (float) $template->height,
            marginTop: (float) $printConfig['margin_top'],
            marginBottom: (float) $printConfig['margin_bottom'],
            marginLeft: (float) $printConfig['margin_left'],
            marginRight: (float) $printConfig['margin_right'],
            spacingX: (float) $printConfig['spacing_x'],
            spacingY: (float) $printConfig['spacing_y'],
            requestedColumns: isset($printConfig['columns']) ? (int) $printConfig['columns'] : null,
            requestedRows: isset($printConfig['rows']) ? (int) $printConfig['rows'] : null,
        );

        return $layout + [
            'paperWidth' => $paperWidth,
            'paperHeight' => $paperHeight,
            'cardWidth' => (float) $template->width,
            'cardHeight' => (float) $template->height,
        ];
    }

    private function orderedRecords(array $ids, ?string $sortBy = null, ?string $sortDir = null): Collection
    {
        $query = IdRecord::query()->whereIn('id', $ids);

        if ($sortBy) {
            RecordSort::apply($query, $sortBy, $sortDir);

            return $query->get();
        }

        // No sort requested (e.g. regenerating an existing batch) - preserve
        // the given id order as-is, since it's already the resolved order
        // from when the batch was first generated.
        $records = $query->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $records->get($id))->filter()->values();
    }

    private function present(GenerationBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'template_id' => $batch->template_id,
            'template_name' => $batch->template?->name,
            'record_ids' => $batch->record_ids,
            'record_count' => count($batch->record_ids),
            'print_config' => $batch->print_config,
            'pdf_path' => $batch->pdf_path,
            'pdf_url' => $batch->pdf_path ? Storage::disk('public')->url($batch->pdf_path) : null,
            'generated_at' => $batch->generated_at?->toIso8601String(),
            'created_at' => $batch->created_at?->toIso8601String(),
        ];
    }
}
