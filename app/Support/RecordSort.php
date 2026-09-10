<?php

namespace App\Support;

class RecordSort
{
    public static function apply($query, ?string $sortBy, ?string $sortDir): void
    {
        $direction = strtolower((string) $sortDir) === 'desc' ? 'desc' : 'asc';

        if ($sortBy && $sortBy !== 'id') {
            foreach (explode(',', $sortBy) as $field) {
                $field = trim($field);
                if ($field === '') {
                    continue;
                }
                $normalized = FieldKey::normalize($field);
                $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(data, ?)) {$direction}", ['$."'.$normalized.'"']);
            }
        }

        $query->orderByDesc('id');
    }
}
