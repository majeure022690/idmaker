<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;

class SpreadsheetReader
{
    public static function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        return in_array($extension, ['csv', 'txt'], true)
            ? self::readCsv($path)
            : self::readSpreadsheet($path);
    }

    private static function readCsv(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return ['headers' => [], 'rows' => []];
        }

        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        $firstLine = strtok($contents, "\r\n") ?: '';
        $delimiter = self::detectDelimiter($firstLine);

        $rows = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $contents);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = array_map(fn ($v) => trim((string) $v), $row);
        }
        fclose($handle);

        $headers = array_shift($rows) ?? [];

        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function detectDelimiter(string $firstLine): string
    {
        $candidates = [',', ';', "\t"];
        $best = ',';
        $bestCount = 0;
        foreach ($candidates as $candidate) {
            $count = substr_count($firstLine, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }

    private static function readSpreadsheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = [];
        foreach ($sheet->toArray(null, true, true, false) as $row) {
            $rows[] = array_map(fn ($v) => trim((string) ($v ?? '')), $row);
        }

        $headers = array_shift($rows) ?? [];

        return ['headers' => $headers, 'rows' => $rows];
    }
}
