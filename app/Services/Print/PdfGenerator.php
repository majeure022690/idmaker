<?php

namespace App\Services\Print;

use App\Models\IdRecord;
use App\Models\Template;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class PdfGenerator
{
    public static function generate(Template $template, Collection $records, array $printConfig): string
    {
        [$paperWidth, $paperHeight] = PaperSize::dimensions(
            $printConfig['paper'],
            $printConfig['custom_width'] ?? null,
            $printConfig['custom_height'] ?? null,
            $printConfig['orientation'] ?? 'portrait',
        );

        $cardWidth = (float) $template->width;
        $cardHeight = (float) $template->height;

        $layout = PrintLayoutCalculator::calculate(
            paperWidth: $paperWidth,
            paperHeight: $paperHeight,
            cardWidth: $cardWidth,
            cardHeight: $cardHeight,
            marginTop: (float) $printConfig['margin_top'],
            marginBottom: (float) $printConfig['margin_bottom'],
            marginLeft: (float) $printConfig['margin_left'],
            marginRight: (float) $printConfig['margin_right'],
            spacingX: (float) $printConfig['spacing_x'],
            spacingY: (float) $printConfig['spacing_y'],
            requestedColumns: isset($printConfig['columns']) ? (int) $printConfig['columns'] : null,
            requestedRows: isset($printConfig['rows']) ? (int) $printConfig['rows'] : null,
        );

        if (! $layout['fits']) {
            throw new \RuntimeException($layout['error']);
        }

        $sides = match ($printConfig['side'] ?? 'front') {
            'back' => ['back'],
            'both' => ['front', 'back'],
            default => ['front'],
        };

        $tempDir = storage_path('app/private/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $mpdf = new Mpdf([
            'format' => [$paperWidth, $paperHeight],
            'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0,
            'margin_header' => 0, 'margin_footer' => 0,
            'tempDir' => $tempDir,
        ]);

        $pageCount = 0;
        foreach ($sides as $sideKey) {
            $design = $sideKey === 'front' ? $template->front_design : $template->back_design;
            if (! $design) {
                continue;
            }

            foreach ($records->chunk($layout['perPage']) as $pageRecords) {
                $mpdf->AddPage();
                $pageCount++;
                foreach (array_values($pageRecords->all()) as $index => $record) {
                    $pos = $layout['positions'][$index];
                    DesignRenderer::renderCard($mpdf, $design, $record, $pos['x'], $pos['y'], $cardWidth, $cardHeight);
                }
            }
        }

        if ($pageCount === 0) {
            throw new \RuntimeException('Nothing to generate — the template has no design for the selected side(s).');
        }

        $relativePath = 'generated/'.Str::uuid().'.pdf';
        Storage::disk('public')->makeDirectory('generated');
        $mpdf->Output(Storage::disk('public')->path($relativePath), Destination::FILE);

        return $relativePath;
    }

    public static function generateSingleCardPdf(float $width, float $height, array $design, ?IdRecord $record): string
    {
        $tempDir = storage_path('app/private/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $mpdf = new Mpdf([
            'format' => [$width, $height],
            'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0,
            'margin_header' => 0, 'margin_footer' => 0,
            'tempDir' => $tempDir,
        ]);
        $mpdf->AddPage();

        DesignRenderer::renderCard($mpdf, $design, $record, 0, 0, $width, $height);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
