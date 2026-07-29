<?php

namespace App\Services\Print;

class PaperSize
{
    private const PRESETS = [
        'A4' => [210.0, 297.0],
        'Letter' => [215.9, 279.4],
        'Legal' => [215.9, 355.6],
        'A5' => [148.0, 210.0],
    ];

    public static function dimensions(string $preset, ?float $customWidth, ?float $customHeight, string $orientation = 'portrait'): array
    {
        [$width, $height] = $preset === 'Custom'
            ? [$customWidth ?? 210.0, $customHeight ?? 297.0]
            : (self::PRESETS[$preset] ?? self::PRESETS['A4']);

        if ($orientation === 'landscape' && $width < $height) {
            [$width, $height] = [$height, $width];
        } elseif ($orientation === 'portrait' && $width > $height) {
            [$width, $height] = [$height, $width];
        }

        return [$width, $height];
    }

    public static function presetNames(): array
    {
        return [...array_keys(self::PRESETS), 'Custom'];
    }
}
