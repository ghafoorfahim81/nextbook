<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the same tabular payload that SpreadsheetExportService consumes, so a
 * controller can hand one array to either service and let the requested format
 * decide. Keep the payload contract in sync between the two.
 *
 * Expected payload keys:
 * - filename (any extension; it is rewritten to .pdf)
 * - sheet_title / title
 * - company_name
 * - exported_on
 * - rtl
 * - orientation ('P' | 'L'); omitted means "landscape once the table is wide"
 * - include_row_number
 * - row_number_label
 * - columns: array<int, array{key:string,label:string,type?:string,align?:string,width?:numeric}>
 * - rows: array<int, array<string, mixed>>
 * - summary_fields: array<int, array{label:string,value:string}>
 */
class PdfExportService
{
    /**
     * Column count past which portrait A4 stops fitting the statement layout.
     */
    protected const LANDSCAPE_COLUMN_THRESHOLD = 6;

    protected const NUMERIC_TYPES = ['money', 'quantity', 'integer', 'number'];

    public function download(array $payload): Response
    {
        $filename = $this->pdfFilename((string) ($payload['filename'] ?? 'export.pdf'));

        $mpdf = $this->makeMpdf($payload);
        $mpdf->WriteHTML($this->render($payload));

        return response(
            $mpdf->Output($filename, Destination::STRING_RETURN),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * Render the payload to raw PDF bytes, for callers that need to stream or
     * attach the document rather than return a download response.
     */
    public function raw(array $payload): string
    {
        $mpdf = $this->makeMpdf($payload);
        $mpdf->WriteHTML($this->render($payload));

        return (string) $mpdf->Output('', Destination::STRING_RETURN);
    }

    protected function makeMpdf(array $payload): Mpdf
    {
        $rtl = (bool) ($payload['rtl'] ?? false);
        $orientation = $this->orientation($payload);

        // mPDF's bundled fonts have no Arabic-script coverage, so IranYekan is
        // registered alongside the defaults and made the document default. The
        // OTL flags are what actually drive Arabic glyph shaping and joining.
        $fontDirs = (new ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new FontVariables())->getDefaults()['fontdata'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-' . $orientation,
            'tempDir' => $this->tempDir(),
            'fontDir' => array_merge($fontDirs, [public_path('fonts/fa')]),
            'fontdata' => $fontData + [
                'iranyekan' => [
                    'R' => 'Qs_Iranyekan.ttf',
                    'B' => 'Qs_Iranyekan bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font' => 'iranyekan',
            'default_font_size' => 9,
            'margin_top' => 12,
            'margin_bottom' => 14,
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_header' => 4,
            'margin_footer' => 6,
            'directionality' => $rtl ? 'rtl' : 'ltr',
        ]);

        $mpdf->SetTitle((string) ($payload['sheet_title'] ?? $payload['title'] ?? 'Export'));
        $mpdf->SetAuthor((string) ($payload['company_name'] ?? config('app.name')));
        $mpdf->SetCreator('Nextbook');
        $mpdf->showImageErrors = false;

        // A long statement spans pages; mPDF repeats <thead> across them on its own.
        $mpdf->SetHTMLFooter(
            '<div style="text-align:center;font-size:7pt;color:#6b7280;">{PAGENO} / {nbpg}</div>'
        );

        return $mpdf;
    }

    protected function render(array $payload): string
    {
        $columns = array_values($payload['columns'] ?? []);
        $includeRowNumber = (bool) ($payload['include_row_number'] ?? true);

        if ($includeRowNumber) {
            array_unshift($columns, [
                'key' => '__row_number',
                'label' => (string) ($payload['row_number_label'] ?? '#'),
                'type' => 'integer',
                'align' => 'right',
                'width' => 4,
            ]);
        }

        return view('exports.table', [
            'columns' => $this->withPercentageWidths($columns),
            'rows' => array_values($payload['rows'] ?? []),
            'summaryFields' => array_values($payload['summary_fields'] ?? []),
            'title' => (string) ($payload['title'] ?? 'Export'),
            'sheetTitle' => (string) ($payload['sheet_title'] ?? $payload['title'] ?? 'Export'),
            'companyName' => (string) ($payload['company_name'] ?? config('app.name')),
            'exportedOn' => (string) ($payload['exported_on'] ?? Carbon::now()->format('Y m d')),
            'exportedOnLabel' => $this->reportTranslation('exported_on', 'Exported on'),
            'rtl' => (bool) ($payload['rtl'] ?? false),
            'formatCell' => fn (array $column, array $row, int $index) => $this->cellValue($column, $row, $index),
            'isNumericColumn' => fn (array $column) => in_array((string) ($column['type'] ?? ''), self::NUMERIC_TYPES, true),
        ])->render();
    }

    /**
     * Turn the spreadsheet's character-based width hints into table percentages.
     * Columns without a hint share whatever the hinted ones leave behind.
     */
    protected function withPercentageWidths(array $columns): array
    {
        $hinted = array_filter($columns, fn (array $c) => is_numeric($c['width'] ?? null));
        $totalHint = array_sum(array_map(fn (array $c) => (float) $c['width'], $hinted));
        $unhintedCount = count($columns) - count($hinted);

        // With no hints at all, fall back to an even split.
        if ($totalHint <= 0) {
            $even = 100 / max(1, count($columns));

            return array_map(fn (array $c) => $c + ['percent' => round($even, 2)], $columns);
        }

        // Reserve a slice for unhinted columns so they do not collapse.
        $unhintedShare = $unhintedCount > 0 ? min(40, $unhintedCount * 10) : 0;
        $hintedShare = 100 - $unhintedShare;

        return array_map(function (array $column) use ($totalHint, $hintedShare, $unhintedShare, $unhintedCount) {
            $percent = is_numeric($column['width'] ?? null)
                ? ((float) $column['width'] / $totalHint) * $hintedShare
                : $unhintedShare / max(1, $unhintedCount);

            return $column + ['percent' => round($percent, 2)];
        }, $columns);
    }

    protected function cellValue(array $column, array $row, int $index): string
    {
        $key = (string) ($column['key'] ?? '');

        if ($key === '__row_number') {
            return (string) ($index + 1);
        }

        $value = data_get($row, $key);

        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (in_array((string) ($column['type'] ?? ''), self::NUMERIC_TYPES, true) && is_numeric($value)) {
            $decimals = (string) $column['type'] === 'integer' ? 0 : 2;

            return number_format((float) $value, $decimals);
        }

        return (string) $value;
    }

    protected function orientation(array $payload): string
    {
        $explicit = strtoupper((string) ($payload['orientation'] ?? ''));

        if (in_array($explicit, ['P', 'L'], true)) {
            return $explicit;
        }

        $columnCount = count($payload['columns'] ?? [])
            + ((bool) ($payload['include_row_number'] ?? true) ? 1 : 0);

        return $columnCount > self::LANDSCAPE_COLUMN_THRESHOLD ? 'L' : 'P';
    }

    protected function tempDir(): string
    {
        $dir = storage_path('app/mpdf-temp');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    protected function pdfFilename(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);

        return ($base !== '' ? $base : 'export') . '.pdf';
    }

    /**
     * Labels live in the frontend JSON locale files, matching how
     * SpreadsheetExportService resolves its own chrome.
     */
    protected function reportTranslation(string $key, ?string $fallback = null): string
    {
        $locale = app()->getLocale();
        $path = resource_path("js/locales/{$locale}/report.json");

        if (! is_file($path)) {
            $path = resource_path('js/locales/en/report.json');
        }

        $content = str_replace("\xEF\xBB\xBF", '', (string) file_get_contents($path));
        $value = data_get(json_decode($content, true) ?: [], $key);

        return filled($value) ? (string) $value : (string) ($fallback ?? $key);
    }
}
