<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IdRecord;
use App\Services\Import\FieldAliases;
use App\Services\Import\SpreadsheetReader;
use App\Support\FieldKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls,ods', 'max:20480'],
        ]);

        $file = $request->file('file');
        $importId = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension());
        $file->storeAs('imports', "{$importId}.{$extension}", 'local');

        [$headers, $rows] = $this->readImport($importId, $extension);

        $suggestedMapping = [];
        foreach ($headers as $header) {
            $suggestedMapping[$header] = $header === '' ? null : FieldAliases::suggest($header);
        }

        return response()->json([
            'import_id' => $importId,
            'extension' => $extension,
            'headers' => $headers,
            'row_count' => count($rows),
            'sample_rows' => array_slice($rows, 0, 10),
            'suggested_mapping' => $suggestedMapping,
        ], 201);
    }

    public function preview(Request $request, string $importId)
    {
        $validated = $request->validate([
            'extension' => ['required', 'string'],
            'mapping' => ['required', 'array'],
            'unique_field' => ['nullable', 'string'],
        ]);

        [$headers, $rows] = $this->readImport($importId, $validated['extension']);
        $mapped = $this->mapRows($headers, $rows, $validated['mapping']);

        $uniqueField = $validated['unique_field'] ?? null;
        $seen = [];
        $issues = [];
        foreach ($mapped as $index => $data) {
            $rowNumber = $index + 2; 
            if ($uniqueField && empty($data[$uniqueField] ?? null)) {
                $issues[] = "Row {$rowNumber}: missing value for unique field \"{$uniqueField}\".";
            } elseif ($uniqueField) {
                $key = $data[$uniqueField];
                if (isset($seen[$key])) {
                    $issues[] = "Row {$rowNumber}: duplicate \"{$uniqueField}\" value \"{$key}\" (also on row {$seen[$key]}).";
                }
                $seen[$key] = $rowNumber;
            }
        }

        return response()->json([
            'preview_rows' => array_slice($mapped, 0, 20),
            'total_rows' => count($mapped),
            'issues' => $issues,
        ]);
    }

    public function commit(Request $request, string $importId)
    {
        $validated = $request->validate([
            'extension' => ['required', 'string'],
            'mapping' => ['required', 'array'],
            'mode' => ['required', 'string', 'in:create,update,create_or_update'],
            'unique_field' => ['nullable', 'string', 'required_unless:mode,create'],
        ]);

        [$headers, $rows] = $this->readImport($importId, $validated['extension']);
        $mapped = $this->mapRows($headers, $rows, $validated['mapping']);
        $mode = $validated['mode'];
        $uniqueField = $validated['unique_field'] ?? null;

        set_time_limit(300);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        // Look up all potential duplicates in one query instead of one
        // per row - at a few thousand rows, a per-row whereRaw() JSON scan
        // means thousands of individual round-trips, and it only gets
        // slower as more rows accumulate from earlier imports.
        $existingByValue = [];
        if ($uniqueField) {
            $values = array_values(array_unique(array_filter(array_column($mapped, $uniqueField), fn ($v) => $v !== null && $v !== '')));
            if ($values !== []) {
                $placeholders = implode(',', array_fill(0, count($values), '?'));
                $existingByValue = IdRecord::query()
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, ?)) IN ({$placeholders})", ['$."'.$uniqueField.'"', ...$values])
                    ->get()
                    ->keyBy(fn (IdRecord $r) => $r->data[$uniqueField] ?? null)
                    ->all();
            }
        }

        $toInsert = [];
        $toUpdate = [];

        foreach ($mapped as $index => $data) {
            $rowNumber = $index + 2;
            if (empty(array_filter($data, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $existing = null;
            if ($uniqueField) {
                $value = $data[$uniqueField] ?? null;
                if ($value === null || $value === '') {
                    $errors[] = "Row {$rowNumber}: missing value for unique field \"{$uniqueField}\", skipped.";
                    $skipped++;
                    continue;
                }
                $existing = $existingByValue[$value] ?? null;
            }

            if ($existing) {
                if ($mode === 'create') {
                    $errors[] = "Row {$rowNumber}: already exists, skipped (mode is Create only).";
                    $skipped++;
                    continue;
                }
                $toUpdate[] = [$existing, $data];
                $updated++;
            } else {
                if ($mode === 'update') {
                    $errors[] = "Row {$rowNumber}: no existing record for \"{$uniqueField}\" = \"{$data[$uniqueField]}\", skipped (mode is Update only).";
                    $skipped++;
                    continue;
                }
                $toInsert[] = $data;
                $created++;
            }
        }

        DB::transaction(function () use ($toInsert, $toUpdate) {
            $now = now();
            foreach (array_chunk($toInsert, 500) as $chunk) {
                IdRecord::insert(array_map(fn (array $data) => [
                    'data' => json_encode($data),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }

            foreach ($toUpdate as [$existing, $data]) {
                $existing->update(['data' => array_merge($existing->data, $data)]);
            }
        });

        Storage::disk('local')->delete("imports/{$importId}.{$validated['extension']}");

        return response()->json(compact('created', 'updated', 'skipped', 'errors'));
    }

    private function readImport(string $importId, string $extension): array
    {
        $path = Storage::disk('local')->path("imports/{$importId}.{$extension}");

        return $this->read($path, $extension);
    }

    private function read(string $path, string $extension): array
    {
        $result = SpreadsheetReader::read($path, $extension);

        return [$result['headers'], $result['rows']];
    }

    private function mapRows(array $headers, array $rows, array $mapping): array
    {
        $columnFields = [];
        foreach ($headers as $index => $header) {
            $target = $mapping[$header] ?? null;
            $columnFields[$index] = $target ? FieldKey::normalize($target) : null;
        }

        $mapped = [];
        foreach ($rows as $row) {
            $data = [];
            foreach ($columnFields as $index => $field) {
                if ($field === null) {
                    continue;
                }
                $data[$field] = $row[$index] ?? null;
            }
            $mapped[] = $data;
        }

        return $mapped;
    }
}
