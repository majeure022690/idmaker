<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IdRecord;
use App\Support\FieldKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IdRecordController extends Controller
{
    public const STANDARD_FIELDS = [
        'id_number', 'full_name', 'first_name', 'middle_name', 'last_name',
        'position', 'office', 'department', 'date_of_birth', 'address',
        'contact_number', 'date_issued', 'expiration_date',
    ];

    public function index(Request $request)
    {
        $query = IdRecord::query();
        $this->applySearch($query, $request->string('search')->toString());
        $this->applyFilters($query, (array) $request->input('filter', []));

        $perPage = (int) $request->input('per_page', 25);
        $records = $query->orderByDesc('id')->paginate(max(1, min($perPage, 500)));

        return $records;
    }

    public function ids(Request $request)
    {
        $query = IdRecord::query();
        $this->applySearch($query, $request->string('search')->toString());
        $this->applyFilters($query, (array) $request->input('filter', []));

        return response()->json(['ids' => $query->orderByDesc('id')->pluck('id')]);
    }

    public function fields()
    {
        $used = IdRecord::query()->pluck('data')
            ->flatMap(fn ($data) => array_keys($data ?? []))
            ->unique()
            ->values();

        $fields = collect(self::STANDARD_FIELDS)->merge($used)->unique()->values();

        return response()->json(['fields' => $fields]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'data' => ['required', 'array'],
            'data.*' => ['nullable'],
        ]);

        $record = IdRecord::create(['data' => $this->normalizeData($validated['data'])]);

        return response()->json($record, 201);
    }

    public function show(IdRecord $idRecord)
    {
        return $idRecord;
    }

    public function update(Request $request, IdRecord $idRecord)
    {
        $validated = $request->validate([
            'data' => ['required', 'array'],
            'data.*' => ['nullable'],
        ]);

        $idRecord->update(['data' => $this->normalizeData($validated['data'])]);

        return $idRecord;
    }

    public function destroy(IdRecord $idRecord)
    {
        $idRecord->delete();

        return response()->noContent();
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $count = IdRecord::query()->whereIn('id', $validated['ids'])->count();
        IdRecord::query()->whereIn('id', $validated['ids'])->delete();

        return response()->json(['deleted' => $count]);
    }

    public function trashed(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);

        return IdRecord::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->paginate(max(1, min($perPage, 500)));
    }

    public function restore(string $id)
    {
        $record = IdRecord::onlyTrashed()->findOrFail($id);
        $record->restore();

        return $record;
    }

    public function forceDelete(string $id)
    {
        $record = IdRecord::onlyTrashed()->findOrFail($id);
        $this->deleteWithAssets($record);

        return response()->noContent();
    }

    public function emptyTrash()
    {
        $records = IdRecord::onlyTrashed()->get();
        foreach ($records as $record) {
            $this->deleteWithAssets($record);
        }

        return response()->json(['deleted' => $records->count()]);
    }

    private function deleteWithAssets(IdRecord $idRecord): void
    {
        foreach ([$idRecord->photo_path, $idRecord->signature_path] as $path) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }
        $idRecord->forceDelete();
    }

    public function uploadPhoto(Request $request, IdRecord $idRecord)
    {
        return $this->uploadAsset($request, $idRecord, 'photo_path');
    }

    public function uploadSignature(Request $request, IdRecord $idRecord)
    {
        return $this->uploadAsset($request, $idRecord, 'signature_path');
    }

    private function uploadAsset(Request $request, IdRecord $idRecord, string $column)
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:png,jpg,jpeg,svg', 'max:5120'],
        ]);

        $file = $request->file('file');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('uploads', $filename, 'public');

        $previous = $idRecord->{$column};
        $idRecord->update([$column => $path]);
        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return $idRecord;
    }

    private function normalizeData(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            $normalized[FieldKey::normalize((string) $key)] = $value;
        }

        return $normalized;
    }

    private function applySearch($query, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $query->where('data', 'like', '%'.$search.'%');
    }

    private function applyFilters($query, array $filters): void
    {
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(data, ?)) = ?",
                ['$."'.FieldKey::normalize((string) $key).'"', $value]
            );
        }
    }
}
