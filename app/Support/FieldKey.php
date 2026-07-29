<?php

namespace App\Support;

class FieldKey
{
    public static function normalize(string $label): string
    {
        $key = strtolower(trim($label));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';
        $key = trim($key, '_');

        return $key === '' ? 'field' : $key;
    }
}
