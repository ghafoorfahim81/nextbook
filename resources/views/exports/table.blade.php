{{--
    Generic tabular export document, rendered by PdfExportService. It consumes
    the same columns/rows contract as SpreadsheetExportService so both formats
    stay visually and structurally aligned.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $sheetTitle }}</title>
    <style>
        body {
            font-family: iranyekan, sans-serif;
            font-size: 8.5pt;
            color: #1f2937;
        }

        .doc-header {
            text-align: center;
            border-bottom: 1.2pt solid #6d28d9;
            padding-bottom: 5pt;
            margin-bottom: 8pt;
        }

        .doc-header .company {
            font-size: 13pt;
            font-weight: bold;
            color: #6d28d9;
        }

        .doc-header .title {
            font-size: 10.5pt;
            margin-top: 2pt;
        }

        .doc-header .meta {
            font-size: 7.5pt;
            color: #6b7280;
            margin-top: 2pt;
        }

        table.summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8pt;
        }

        table.summary td {
            border: 0.4pt solid #e5e7eb;
            background-color: #f5f3ff;
            padding: 3pt 5pt;
            font-size: 8pt;
        }

        table.summary td.label {
            color: #6b7280;
            width: 25%;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data thead th {
            background-color: #6d28d9;
            color: #ffffff;
            font-weight: bold;
            font-size: 8pt;
            padding: 4pt 3pt;
            border: 0.4pt solid #5b21b6;
            text-align: center;
        }

        table.data tbody td {
            border: 0.4pt solid #e5e7eb;
            padding: 3pt;
            vertical-align: top;
        }

        table.data tbody tr.alt td {
            background-color: #faf9ff;
        }

        .align-right {
            text-align: right;
        }

        .align-center {
            text-align: center;
        }

        .align-start {
            text-align: {{ $rtl ? 'right' : 'left' }};
        }

        .empty {
            text-align: center;
            color: #6b7280;
            padding: 12pt;
        }
    </style>
</head>
<body>
    <div class="doc-header">
        <div class="company">{{ $companyName }}</div>
        <div class="title">{{ $sheetTitle }}</div>
        <div class="meta">{{ $exportedOnLabel }}: {{ $exportedOn }}</div>
    </div>

    @if (! empty($summaryFields))
        <table class="summary">
            @foreach ($summaryFields as $field)
                <tr>
                    <td class="label align-start">{{ $field['label'] ?? '' }}</td>
                    <td class="align-start">{{ $field['value'] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <table class="data">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th style="width: {{ $column['percent'] }}%">{{ $column['label'] ?? $column['key'] ?? '' }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $row)
                <tr @class(['alt' => $index % 2 === 1])>
                    @foreach ($columns as $column)
                        @php
                            $numeric = $isNumericColumn($column);
                            $alignment = $numeric || ($column['align'] ?? '') === 'right'
                                ? 'align-right'
                                : 'align-start';
                        @endphp
                        <td class="{{ $alignment }}">{{ $formatCell($column, $row, $index) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="{{ count($columns) }}">&mdash;</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
