<?php

namespace App\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class SpreadsheetExportService
{
    /**
     * Translation cache for frontend JSON locale files.
     */
    protected array $localeCache = [];

    /**
     * Download a single-sheet workbook built from tabular data.
     *
     * Expected payload keys:
     * - filename
     * - sheet_name
     * - title
     * - company_name
     * - exported_on
     * - rtl
     * - include_row_number
     * - row_number_label
     * - columns: array<int, array{key:string,label:string,type?:string,align?:string}>
     * - rows: array<int, array<string, mixed>>
     */
    public function download(array $payload): BinaryFileResponse
    {
        $path = $this->buildWorkbook($payload);

        return response()->download($path, $payload['filename'] ?? 'export.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Create a temporary xlsx workbook and return the file path.
     */
    public function buildWorkbook(array $payload): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nextbook_xlsx_');

        if ($path === false) {
            throw new \RuntimeException('Unable to create a temporary export file.');
        }

        $xlsxPath = $path . '.xlsx';
        @unlink($path);

        $zip = new ZipArchive();
        if ($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create the spreadsheet archive.');
        }

        $sheetName = $this->sanitizeSheetName((string) ($payload['sheet_name'] ?? 'Sheet1'));
        $columns = array_values($payload['columns'] ?? []);
        $rows = array_values($payload['rows'] ?? []);
        $includeRowNumber = (bool) ($payload['include_row_number'] ?? true);

        if ($includeRowNumber) {
            array_unshift($columns, [
                'key' => '__row_number',
                'label' => (string) ($payload['row_number_label'] ?? '#'),
                'type' => 'integer',
                'align' => 'right',
            ]);
        }

        $columnCount = max(1, count($columns));
        $lastColumn = $this->columnLetter($columnCount);
        $headerRowIndex = 5;
        $firstDataRowIndex = $headerRowIndex + 1;
        $rtl = (bool) ($payload['rtl'] ?? false);
        $title = (string) ($payload['title'] ?? 'Export');
        $companyName = (string) ($payload['company_name'] ?? config('app.name'));
        $exportedOn = (string) ($payload['exported_on'] ?? Carbon::now()->format('Y m d'));
        $sheetTitle = (string) ($payload['sheet_title'] ?? $title);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('docProps/app.xml', $this->appXml($sheetName));
        $zip->addFromString('docProps/core.xml', $this->coreXml($title));
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml([
            'title' => $title,
            'company_name' => $companyName,
            'exported_on' => $exportedOn,
            'sheet_title' => $sheetTitle,
            'rows' => $rows,
            'columns' => $columns,
            'header_row_index' => $headerRowIndex,
            'first_data_row_index' => $firstDataRowIndex,
            'last_column' => $lastColumn,
            'rtl' => $rtl,
        ]));

        $zip->close();

        return $xlsxPath;
    }

    protected function sheetXml(array $payload): string
    {
        $columns = array_values($payload['columns'] ?? []);
        $rows = array_values($payload['rows'] ?? []);
        $headerRowIndex = (int) ($payload['header_row_index'] ?? 5);
        $firstDataRowIndex = (int) ($payload['first_data_row_index'] ?? 6);
        $lastColumn = (string) ($payload['last_column'] ?? 'A');
        $rtl = (bool) ($payload['rtl'] ?? false);

        $rowsXml = [];
        $rowsXml[] = $this->sheetRow(1, [
            ['value' => $payload['company_name'] ?? config('app.name'), 'style' => 1, 'type' => 'inlineStr'],
        ], $lastColumn);
        $rowsXml[] = $this->sheetRow(2, [
            ['value' => $payload['sheet_title'] ?? $payload['title'] ?? 'Export', 'style' => 2, 'type' => 'inlineStr'],
        ], $lastColumn);
        $rowsXml[] = $this->sheetRow(3, [
            ['value' => $this->reportTranslation('exported_on', 'Exported on') . ': ' . ($payload['exported_on'] ?? Carbon::now()->format('Y m d')), 'style' => 2, 'type' => 'inlineStr'],
        ], $lastColumn);
        $rowsXml[] = $this->sheetRow(4, [], $lastColumn);

        $headerCells = [];
        foreach ($columns as $columnIndex => $column) {
            $headerCells[] = [
                'value' => (string) ($column['label'] ?? $column['key'] ?? ''),
                'style' => 3,
                'type' => 'inlineStr',
                'column_index' => $columnIndex + 1,
            ];
        }
        $rowsXml[] = $this->sheetRow($headerRowIndex, $headerCells, $lastColumn);

        foreach ($rows as $rowIndex => $row) {
            $sheetRowIndex = $firstDataRowIndex + $rowIndex;
            $rowStyle = $rowIndex % 2 === 0 ? 4 : 5;
            $cells = [];

            foreach ($columns as $columnIndex => $column) {
                $key = (string) ($column['key'] ?? '');

                if ($key === '__row_number') {
                    $value = $rowIndex + 1;
                    $cells[] = [
                        'value' => $value,
                        'style' => $rowStyle,
                        'type' => 'number',
                        'column_index' => $columnIndex + 1,
                    ];
                    continue;
                }

                $value = data_get($row, $key);
                $type = (string) ($column['type'] ?? 'text');
                $align = (string) ($column['align'] ?? '');
                $isNumeric = in_array($type, ['money', 'quantity', 'integer', 'number'], true) && is_numeric($value);

                $cells[] = [
                    'value' => $isNumeric ? (float) $value : $value,
                    'style' => $rowStyle + (($align === 'right' || $isNumeric) ? 2 : 0),
                    'type' => $isNumeric ? 'number' : 'inlineStr',
                    'column_index' => $columnIndex + 1,
                ];
            }

            $rowsXml[] = $this->sheetRow($sheetRowIndex, $cells, $lastColumn);
        }

        $rowCount = $firstDataRowIndex + count($rows) - 1;
        $dimension = 'A1:' . $lastColumn . max($rowCount, $headerRowIndex);
        $autoFilter = $rows === []
            ? ''
            : '<autoFilter ref="' . $this->rangeRef(1, $headerRowIndex, count($columns), $rowCount) . '"/>';
        $mergeCells = sprintf(
            '<mergeCells count="3">%s%s%s</mergeCells>',
            $this->mergeCellRef('A1', $lastColumn . '1'),
            $this->mergeCellRef('A2', $lastColumn . '2'),
            $this->mergeCellRef('A3', $lastColumn . '3')
        );

        $sheetView = '<sheetViews><sheetView workbookViewId="0"' . ($rtl ? ' rightToLeft="1"' : '') . '/></sheetViews>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"'
            . ' xmlns:x14="http://schemas.microsoft.com/office/spreadsheetml/2009/9/main"'
            . ' xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"'
            . ' mc:Ignorable="x14ac"'
            . ' xmlns:x14ac="http://schemas.microsoft.com/office/spreadsheetml/2009/9/ac">'
            . '<dimension ref="' . $dimension . '"/>'
            . $sheetView
            . '<sheetFormatPr defaultRowHeight="20"/>'
            . '<cols>' . $this->columnWidthsXml($columns) . '</cols>'
            . '<sheetData>' . implode('', $rowsXml) . '</sheetData>'
            . $autoFilter
            . $mergeCells
            . '<pageMargins left="0.5" right="0.5" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            . '</worksheet>';
    }

    protected function sheetRow(int $rowIndex, array $cells, string $lastColumn): string
    {
        $xml = '<row r="' . $rowIndex . '" spans="1:' . max(1, count($cells)) . '">';

        foreach ($cells as $index => $cell) {
            $columnIndex = (int) ($cell['column_index'] ?? ($index + 1));
            $value = $cell['value'] ?? '';
            $style = (int) ($cell['style'] ?? 0);
            $type = (string) ($cell['type'] ?? 'inlineStr');
            $cellRef = $this->cellRef($columnIndex, $rowIndex);

            if ($type === 'number' && is_numeric($value)) {
                $xml .= '<c r="' . $cellRef . '" s="' . $style . '"><v>' . $value . '</v></c>';
                continue;
            }

            $xml .= '<c r="' . $cellRef . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">'
                . $this->escape((string) $value)
                . '</t></is></c>';
        }

        $xml .= '</row>';

        return $xml;
    }

    protected function columnWidthsXml(array $columns): string
    {
        $widths = [];

        foreach ($columns as $index => $column) {
            $key = (string) ($column['key'] ?? '');
            $label = (string) ($column['label'] ?? $key);
            $baseWidth = max(8, min(40, mb_strlen($label) + 2));

            if ($key === '__row_number') {
                $baseWidth = 8;
            }

            if (in_array((string) ($column['type'] ?? ''), ['money', 'quantity', 'integer', 'number'], true)) {
                $baseWidth = max($baseWidth, 12);
            }

            $widths[] = '<col min="' . ($index + 1) . '" max="' . ($index + 1) . '" width="' . $baseWidth . '" customWidth="1"/>';
        }

        return implode('', $widths);
    }

    protected function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3">'
            . '<font><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="14"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><i/><sz val="11"/><color rgb="FF6B7280"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF6D28D9"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF5F3FF"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="8">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    public function localeTranslation(string $group, string $key, ?string $fallback = null): string
    {
        $value = data_get($this->localeFileTranslations($group), $key);

        return filled($value) ? (string) $value : (string) ($fallback ?? $key);
    }

    protected function localeFileTranslations(string $group): array
    {
        $locale = app()->getLocale();
        $cacheKey = $locale . ':' . $group;

        if (! array_key_exists($cacheKey, $this->localeCache)) {
            $path = resource_path("js/locales/{$locale}/{$group}.json");

            if (! is_file($path)) {
                $path = resource_path("js/locales/en/{$group}.json");
            }

            $this->localeCache[$cacheKey] = json_decode((string) file_get_contents($path), true) ?: [];
        }

        return $this->localeCache[$cacheKey];
    }

    protected function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    protected function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    protected function workbookXml(string $sheetName): string
    {
        $sheetName = $this->escape($sheetName);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $sheetName . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    protected function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    protected function appXml(string $sheetName): string
    {
        $sheetName = $this->escape($sheetName);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>Nextbook</Application>'
            . '<DocSecurity>0</DocSecurity>'
            . '<ScaleCrop>false</ScaleCrop>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>' . $sheetName . '</vt:lpstr></vt:vector></TitlesOfParts>'
            . '<Company>Nextbook</Company>'
            . '<LinksUpToDate>false</LinksUpToDate>'
            . '<SharedDoc>false</SharedDoc>'
            . '<HyperlinksChanged>false</HyperlinksChanged>'
            . '<AppVersion>16.0300</AppVersion>'
            . '</Properties>';
    }

    protected function coreXml(string $title): string
    {
        $title = $this->escape($title);
        $now = Carbon::now()->toAtomString();

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>Nextbook</dc:creator>'
            . '<cp:lastModifiedBy>Nextbook</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '<dc:title>' . $title . '</dc:title>'
            . '</cp:coreProperties>';
    }

    protected function mergeCellRef(string $start, string $end): string
    {
        return '<mergeCell ref="' . $start . ':' . $end . '"/>';
    }

    protected function rangeRef(int $startColumn, int $startRow, int $endColumn, int $endRow): string
    {
        return $this->cellRef($startColumn, $startRow) . ':' . $this->cellRef($endColumn, $endRow);
    }

    protected function cellRef(int $columnIndex, int $rowIndex): string
    {
        return $this->columnLetter($columnIndex) . $rowIndex;
    }

    protected function columnLetter(int $columnIndex): string
    {
        $column = '';

        while ($columnIndex > 0) {
            $columnIndex--;
            $column = chr(65 + ($columnIndex % 26)) . $column;
            $columnIndex = intdiv($columnIndex, 26);
        }

        return $column;
    }

    protected function sanitizeSheetName(string $name): string
    {
        $name = Str::of($name)
            ->replace(['[', ']', ':', '*', '?', '/', '\\'], ' ')
            ->squish()
            ->substr(0, 31)
            ->trim()
            ->toString();

        return $name !== '' ? $name : 'Sheet1';
    }

    protected function reportTranslation(string $key, ?string $fallback = null): string
    {
        $locale = app()->getLocale();
        $path = resource_path("js/locales/{$locale}/report.json");

        if (! is_file($path)) {
            $path = resource_path('js/locales/en/report.json');
        }

        $data = json_decode((string) file_get_contents($path), true) ?: [];
        $value = data_get($data, $key);

        return filled($value) ? (string) $value : (string) ($fallback ?? $key);
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
