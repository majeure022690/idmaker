<?php

namespace App\Services\Print;

class PrintLayoutCalculator
{
    public static function calculate(
        float $paperWidth,
        float $paperHeight,
        float $cardWidth,
        float $cardHeight,
        float $marginTop,
        float $marginBottom,
        float $marginLeft,
        float $marginRight,
        float $spacingX,
        float $spacingY,
        ?int $requestedColumns = null,
        ?int $requestedRows = null,
    ): array {
        $printableWidth = $paperWidth - $marginLeft - $marginRight;
        $printableHeight = $paperHeight - $marginTop - $marginBottom;

        if ($printableWidth <= 0 || $printableHeight <= 0) {
            return self::empty('Margins leave no printable area on this paper size.');
        }

        $maxColumns = (int) floor(($printableWidth + $spacingX) / ($cardWidth + $spacingX));
        $maxRows = (int) floor(($printableHeight + $spacingY) / ($cardHeight + $spacingY));

        if ($maxColumns < 1 || $maxRows < 1) {
            return self::empty('The card is larger than the printable area for this paper size and margins.');
        }

        $columns = $requestedColumns ?? $maxColumns;
        $rows = $requestedRows ?? $maxRows;

        if ($columns > $maxColumns || $rows > $maxRows) {
            return self::empty(
                "Requested {$columns}×{$rows} grid does not fit — at most {$maxColumns}×{$maxRows} cards fit this paper size with the given margins/spacing."
            );
        }

        $gridWidth = $columns * $cardWidth + ($columns - 1) * $spacingX;
        $gridHeight = $rows * $cardHeight + ($rows - 1) * $spacingY;
        $startX = $marginLeft + ($printableWidth - $gridWidth) / 2;
        $startY = $marginTop + ($printableHeight - $gridHeight) / 2;

        $positions = [];
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $columns; $col++) {
                $positions[] = [
                    'x' => $startX + $col * ($cardWidth + $spacingX),
                    'y' => $startY + $row * ($cardHeight + $spacingY),
                ];
            }
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'perPage' => count($positions),
            'positions' => $positions,
            'fits' => true,
            'error' => null,
        ];
    }

    private static function empty(string $error): array
    {
        return ['columns' => 0, 'rows' => 0, 'perPage' => 0, 'positions' => [], 'fits' => false, 'error' => $error];
    }
}
