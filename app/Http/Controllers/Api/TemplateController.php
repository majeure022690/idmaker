<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Models\IdRecord;
use App\Models\Template;
use App\Services\Print\PdfGenerator;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index()
    {
        return Template::query()
            ->select(['id', 'name', 'width', 'height', 'unit', 'has_back', 'updated_at'])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function store(StoreTemplateRequest $request)
    {
        $template = Template::create($request->validated());

        return response()->json($template, 201);
    }

    public function show(Template $template)
    {
        return $template;
    }

    public function update(UpdateTemplateRequest $request, Template $template)
    {
        $template->update($request->validated());

        return $template;
    }

    public function destroy(Template $template)
    {
        $template->delete();

        return response()->noContent();
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'width' => ['required', 'numeric', 'gt:0'],
            'height' => ['required', 'numeric', 'gt:0'],
            'design' => ['required', 'array'],
            'design.elements' => ['present', 'array'],
            'record_id' => ['nullable', 'integer', 'exists:id_records,id'],
        ]);

        $record = isset($validated['record_id']) ? IdRecord::find($validated['record_id']) : null;

        $pdf = PdfGenerator::generateSingleCardPdf(
            (float) $validated['width'],
            (float) $validated['height'],
            $validated['design'],
            $record,
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
        ]);
    }
}
