<?php

namespace App\Support;

class RecordSort
{
    /**
     * A comma-separated $sortBy (e.g. "region,province,municipality,barangay")
     * chains multiple ORDER BY clauses for a drill-down/cascading sort, not
     * just a single flat field. Shared between the records list and PDF
     * generation so both resolve a given sort choice identically.
     */
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

        // Deterministic tie-breaker for equal/missing sort values, and the
        // whole ordering when no field sort was requested at all.
        $query->orderByDesc('id');
    }
}
