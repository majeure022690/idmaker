<?php

namespace App\Services\Print;

use App\Models\IdRecord;
use App\Services\Import\FieldAliases;
use App\Support\FieldKey;
use Endroid\QrCode\Color\Color as QrColor;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Picqer\Barcode\BarcodeGeneratorPNG;

class DesignRenderer
{
    public static function renderCard(Mpdf $mpdf, array $side, ?IdRecord $record, float $offsetX, float $offsetY, float $w, float $h): void
    {
        self::drawBackground($mpdf, $side['background'] ?? null, $offsetX, $offsetY, $w, $h);

        foreach ($side['elements'] ?? [] as $element) {
            self::drawElement($mpdf, $element, $record, $offsetX, $offsetY);
        }
    }

    private static function drawBackground(Mpdf $mpdf, ?array $background, float $x, float $y, float $w, float $h): void
    {
        if ($background && ($background['type'] ?? 'color') === 'image' && ! empty($background['value'])) {
            $path = self::resolvePublicPath($background['value']);
            if ($path) {
                $mpdf->WriteFixedPosHTML(
                    '<img src="'.self::escapeUrl($path)."\" style=\"width:{$w}mm; height:{$h}mm;\" />",
                    $x, $y, $w, $h, 'hidden'
                );

                return;
            }
        }

        $color = self::escapeAttr($background['value'] ?? '#ffffff');
        $mpdf->WriteFixedPosHTML(
            "<div style=\"width:{$w}mm; height:{$h}mm; background-color:{$color};\"></div>",
            $x, $y, $w, $h, 'hidden'
        );
    }

    private static function drawElement(Mpdf $mpdf, array $element, ?IdRecord $record, float $offsetX, float $offsetY): void
    {
        $x = $offsetX + (float) ($element['x'] ?? 0);
        $y = $offsetY + (float) ($element['y'] ?? 0);
        $w = (float) ($element['width'] ?? 0);
        $h = (float) ($element['height'] ?? 0);
        $rotation = (float) ($element['rotation'] ?? 0);
        $opacity = (float) ($element['opacity'] ?? 1);

        $html = match ($element['type'] ?? null) {
            'text' => self::textHtml((string) ($element['content'] ?? ''), $element, $w, $h),
            'dynamic_text' => self::textHtml(self::resolveDynamicField((string) ($element['field'] ?? ''), $record), $element, $w, $h),
            'image' => self::imageHtml(self::resolvePublicPath($element['source'] ?? null), $w, $h),
            'photo' => self::imageHtml(self::resolvePublicPath($record?->photo_path), $w, $h),
            'signature' => self::imageHtml(self::resolvePublicPath($record?->signature_path), $w, $h),
            'qrcode' => self::qrCodeHtml($element, $record, $w, $h),
            'barcode' => self::barcodeHtml($element, $record, $w, $h),
            'shape' => self::shapeHtml($element, $w, $h),
            default => null,
        };

        if ($html === null || $html === '') {
            return;
        }

        if ($opacity < 1.0) {
            $mpdf->SetAlpha($opacity);
        }

        if ($rotation !== 0.0) {
            $mpdf->Rotate($rotation, $x, $y);
        }

        $mpdf->WriteFixedPosHTML($html, $x, $y, $w, $h, 'hidden');

        if ($rotation !== 0.0) {
            $mpdf->Rotate(0);
        }
        if ($opacity < 1.0) {
            $mpdf->SetAlpha(1);
        }
    }

    private static function textHtml(string $text, array $element, float $w, float $h): string
    {
        $weight = ($element['fontWeight'] ?? 'normal') === 'bold' ? 'bold' : 'normal';
        $style = ($element['fontStyle'] ?? 'normal') === 'italic' ? 'italic' : 'normal';
        $align = match ($element['textAlign'] ?? 'left') {
            'center' => 'center',
            'right' => 'right',
            default => 'left',
        };
        $fontSize = (float) ($element['fontSize'] ?? 12);
        $lineHeight = (float) ($element['lineHeight'] ?? 1.16);
        $letterSpacing = (float) ($element['letterSpacing'] ?? 0) / 1000; 

        $css = "width:{$w}mm; height:{$h}mm; margin:0; padding:0; overflow:hidden;"
            .' font-family:'.self::mapFont((string) ($element['fontFamily'] ?? 'Arial')).';'
            ." font-size:{$fontSize}pt; font-weight:{$weight}; font-style:{$style};"
            .' color:'.self::escapeAttr((string) ($element['color'] ?? '#000000')).';'
            ." text-align:{$align}; line-height:{$lineHeight};"
            .($letterSpacing !== 0.0 ? ' letter-spacing:'.self::n($letterSpacing).'em;' : '');

        return '<div style="'.$css.'">'.nl2br(self::escapeHtml($text)).'</div>';
    }

    private static function imageHtml(?string $path, float $w, float $h): ?string
    {
        if (! $path) {
            return null;
        }

        return '<img src="'.self::escapeUrl($path)."\" style=\"width:{$w}mm; height:{$h}mm;\" />";
    }

    private static function qrCodeHtml(array $element, ?IdRecord $record, float $w, float $h): ?string
    {
        $value = self::resolveDynamicField((string) ($element['value'] ?? ''), $record);
        if ($value === '') {
            return null;
        }

        $qrCode = new QrCode(
            data: $value,
            margin: 0,
            foregroundColor: self::qrColor((string) ($element['foreground'] ?? '#000000')),
            backgroundColor: self::qrColor((string) ($element['background'] ?? '#ffffff')),
        );
        $dataUri = (new PngWriter())->write($qrCode)->getDataUri();

        return "<img src=\"{$dataUri}\" style=\"width:{$w}mm; height:{$h}mm;\" />";
    }

    private static function barcodeHtml(array $element, ?IdRecord $record, float $w, float $h): ?string
    {
        $value = self::resolveDynamicField((string) ($element['value'] ?? ''), $record);
        if ($value === '') {
            return null;
        }

        $type = match ($element['format'] ?? 'CODE128') {
            'CODE39' => BarcodeGeneratorPNG::TYPE_CODE_39,
            'EAN13' => BarcodeGeneratorPNG::TYPE_EAN_13,
            'EAN8' => BarcodeGeneratorPNG::TYPE_EAN_8,
            'UPC' => BarcodeGeneratorPNG::TYPE_UPC_A,
            default => BarcodeGeneratorPNG::TYPE_CODE_128,
        };

        $generator = new BarcodeGeneratorPNG();
        $foreground = self::hexToRgb((string) ($element['foreground'] ?? '#000000'));

        try {
            $binary = $generator->getBarcode($value, $type, 2, 60, $foreground);
        } catch (\Exception) {
            return null;
        }

        $background = self::escapeAttr((string) ($element['background'] ?? '#ffffff'));
        $dataUri = 'data:image/png;base64,'.base64_encode($binary);

        return "<img src=\"{$dataUri}\" style=\"width:{$w}mm; height:{$h}mm; background-color:{$background};\" />";
    }

    private static function shapeHtml(array $element, float $w, float $h): string
    {
        $fill = self::escapeAttr((string) ($element['fill'] ?? '#e2e8f0'));
        $shape = $element['shape'] ?? 'rectangle';

        if ($shape === 'line') {
            return "<div style=\"width:{$w}mm; height:{$h}mm; background-color:{$fill};\"></div>";
        }

        $stroke = self::escapeAttr((string) ($element['stroke'] ?? '#000000'));
        $strokeWidth = (float) ($element['strokeWidth'] ?? 0);
        $border = $strokeWidth > 0 ? "border: {$strokeWidth}mm solid {$stroke};" : '';
        $radius = $shape === 'circle' ? ' border-radius:50%;' : '';

        return "<div style=\"box-sizing:border-box; width:{$w}mm; height:{$h}mm; background-color:{$fill}; {$border}{$radius}\"></div>";
    }

    private static function resolveTemplate(string $template, ?IdRecord $record): string
    {
        return preg_replace_callback(
            '/\{\{\s*([^{}]+?)\s*\}\}/',
            fn (array $m) => self::lookupFieldValue($m[1], $record),
            $template
        ) ?? $template;
    }

    private static function resolveDynamicField(string $fieldOrTemplate, ?IdRecord $record): string
    {
        if (str_contains($fieldOrTemplate, '{{')) {
            return self::resolveTemplate($fieldOrTemplate, $record);
        }

        return self::lookupFieldValue($fieldOrTemplate, $record);
    }

    private static function lookupFieldValue(string $rawKey, ?IdRecord $record): string
    {
        if (! $record) {
            return '';
        }

        $key = FieldKey::normalize($rawKey);
        if (array_key_exists($key, $record->data)) {
            return (string) ($record->data[$key] ?? '');
        }

        $canonical = FieldAliases::canonicalize($key);

        return (string) ($record->data[$canonical] ?? '');
    }

    private static function mapFont(string $family): string
    {
        $normalized = strtolower($family);
        if (str_contains($normalized, 'times') || str_contains($normalized, 'serif') || str_contains($normalized, 'georgia')) {
            return 'serif';
        }
        if (str_contains($normalized, 'courier') || str_contains($normalized, 'mono') || str_contains($normalized, 'consolas')) {
            return 'monospace';
        }

        return 'sans-serif';
    }

    private static function qrColor(string $hex): QrColor
    {
        [$r, $g, $b] = self::hexToRgb($hex);

        return new QrColor($r, $g, $b);
    }

    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) {
            return [0, 0, 0];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function resolvePublicPath(?string $storagePath): ?string
    {
        if (! $storagePath) {
            return null;
        }
        $full = Storage::disk('public')->path($storagePath);

        return is_file($full) ? $full : null;
    }

    private static function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }

    private static function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function escapeUrl(string $path): string
    {
        return str_replace(['"', "'"], ['%22', '%27'], $path);
    }
}
