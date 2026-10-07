<?php

/**
 * Renders docs/multi-company-plan-fa.md into a Persian PDF.
 *
 * Re-run this after editing the markdown — the PDF is a build artifact, the
 * markdown is the source.
 *
 * Font handling follows docs/build-hr-guide.php and PdfExportService: mPDF's
 * bundled fonts carry no Arabic-script coverage, and `useOTL` is what drives
 * Arabic glyph shaping and joining — without it Persian renders as a row of
 * disconnected letters.
 *
 * Usage:  php docs/build-multi-company-plan.php
 */

require __DIR__.'/../vendor/autoload.php';

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

$root = dirname(__DIR__);
$source = $root.'/docs/multi-company-plan-fa.md';
$target = $root.'/docs/multi-company-plan-fa.pdf';
$fontPath = $root.'/public/fonts/fa';
$tempDir = $root.'/storage/app/mpdf-temp';

if (! is_dir($tempDir)) {
    mkdir($tempDir, 0775, true);
}

$markdown = file_get_contents($source);

// The metadata lines under the title are separate lines in the markdown, but
// CommonMark folds a run of lines into one paragraph with soft breaks. Two
// trailing spaces turn them into hard breaks so each keeps its own line, and
// only these lines are touched — making soft breaks hard everywhere would
// reproduce the markdown's own 80-column wrapping in the PDF.
$markdown = preg_replace(
    '/^((?:وضعیت|تاریخ تصمیم|برنچ در زمان نوشتن|نسخهٔ انگلیسی):.*)$/mu',
    '$1  ',
    $markdown
);

$environment = new Environment([
    'html_input' => 'allow',
    'allow_unsafe_links' => false,
]);
$environment->addExtension(new CommonMarkCoreExtension());
$environment->addExtension(new GithubFlavoredMarkdownExtension());

$body = (new MarkdownConverter($environment))->convert($markdown)->getContent();

/**
 * Preformatted blocks come in two kinds and cannot share one treatment.
 *
 * A block of pure code (a query, a pivot definition) is Latin and wants a
 * monospace font, left to right. A block that is really a diagram carries
 * Persian labels, and a Latin monospace font has no glyphs for those at all —
 * it would render as empty boxes. So each block is classified by whether it
 * contains Arabic-script characters, and the box-drawing characters are
 * flattened to ASCII: column alignment is already lost once a proportional
 * Persian font is used, so guaranteed glyphs are worth more than the lines.
 */
$body = preg_replace_callback(
    '#<pre><code([^>]*)>(.*?)</code></pre>#su',
    function (array $m): string {
        $isPersian = (bool) preg_match('/\p{Arabic}/u', $m[2]);

        $content = $isPersian
            ? strtr($m[2], [
                '└' => '+', '├' => '+', '┌' => '+', '┐' => '+', '┘' => '+',
                '─' => '-', '│' => '|',
            ])
            : $m[2];

        $class = $isPersian ? 'block-rtl' : 'block-ltr';

        return '<pre class="'.$class.'"><code'.$m[1].'>'.$content.'</code></pre>';
    },
    $body
);

$css = <<<'CSS'
body {
    font-family: iranyekan;
    direction: rtl;
    text-align: right;
    font-size: 9.5pt;
    line-height: 1.8;
    color: #1f2937;
}
h1 {
    font-size: 19pt;
    color: #6d28d9;
    border-bottom: 1.5px solid #8b5cf6;
    padding-bottom: 6pt;
    margin: 0 0 10pt 0;
}
h2 {
    font-size: 13.5pt;
    color: #6d28d9;
    margin: 18pt 0 6pt 0;
    border-bottom: 0.5px solid #e5e7eb;
    padding-bottom: 3pt;
    page-break-after: avoid;
}
h3 {
    font-size: 11pt;
    color: #374151;
    margin: 13pt 0 5pt 0;
    page-break-after: avoid;
}
p { margin: 0 0 7pt 0; }
ul, ol { margin: 0 0 7pt 0; padding-right: 15pt; padding-left: 0; }
li { margin-bottom: 3pt; }
strong { font-weight: bold; color: #111827; }
a { color: #6d28d9; text-decoration: none; }
hr { border: none; border-top: 0.5px solid #e5e7eb; margin: 13pt 0; }
blockquote {
    border-right: 3px solid #8b5cf6;
    background-color: #f5f3ff;
    margin: 9pt 0;
    padding: 7pt 11pt;
}
blockquote p { margin: 0 0 3pt 0; }
code {
    font-family: dejavusansmono;
    font-size: 8.2pt;
    background-color: #f3f4f6;
    color: #9d174d;
    direction: ltr;
}
pre {
    background-color: #f9fafb;
    border: 0.5px solid #e5e7eb;
    border-right: 2px solid #8b5cf6;
    padding: 7pt 9pt;
    margin: 9pt 0;
    line-height: 1.55;
}
pre code {
    background-color: transparent;
    color: #1f2937;
    font-size: 8pt;
}
pre.block-ltr { direction: ltr; text-align: left; }
pre.block-ltr code { font-family: dejavusansmono; }
pre.block-rtl { direction: rtl; text-align: right; }
pre.block-rtl code { font-family: iranyekan; font-size: 8.5pt; color: #1f2937; }
table {
    width: 100%;
    border-collapse: collapse;
    margin: 9pt 0;
    font-size: 8.5pt;
}
th {
    background-color: #8b5cf6;
    color: #ffffff;
    font-weight: bold;
    padding: 4pt 6pt;
    text-align: right;
    border: 0.5px solid #7c3aed;
}
td {
    padding: 4pt 6pt;
    border: 0.5px solid #e5e7eb;
    text-align: right;
    vertical-align: top;
}
td code, th code { font-size: 7.8pt; }
CSS;

$html = '<html><head><meta charset="utf-8"><style>'.$css.'</style></head>'
    .'<body dir="rtl">'.$body.'</body></html>';

$fontDirs = (new ConfigVariables())->getDefaults()['fontDir'];
$fontData = (new FontVariables())->getDefaults()['fontdata'];

$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'tempDir' => $tempDir,
    'fontDir' => array_merge($fontDirs, [$fontPath]),
    'fontdata' => $fontData + [
        'iranyekan' => [
            'R' => 'Qs_Iranyekan.ttf',
            'B' => 'Qs_Iranyekan bold.ttf',
            'useOTL' => 0xFF,
            'useKashida' => 75,
        ],
    ],
    'default_font' => 'iranyekan',
    'default_font_size' => 9.5,
    'margin_top' => 18,
    'margin_bottom' => 18,
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_header' => 7,
    'margin_footer' => 8,
    'directionality' => 'rtl',
]);

// Arrows and box-drawing characters are not in IranYekan. This pulls a missing
// glyph from another registered font instead of drawing an empty box.
$mpdf->useSubstitutions = true;
$mpdf->showImageErrors = false;

$mpdf->SetTitle('پلان چند-کمپنی — نکست‌بوک');
$mpdf->SetAuthor('Nextbook');
$mpdf->SetCreator('Nextbook');

// The running header sits on the right in an RTL document, and direction has to
// be restated here: header and footer HTML are parsed separately from the body.
$mpdf->SetHTMLHeader(
    '<div dir="rtl" style="direction:rtl;text-align:right;font-size:7.5pt;color:#9ca3af;'
    .'border-bottom:0.5px solid #e5e7eb;padding-bottom:2pt;">'
    .'پلان چند-کمپنی — نکست‌بوک</div>'
);

$mpdf->SetHTMLFooter(
    '<div style="text-align:center;font-size:7.5pt;color:#9ca3af;">{PAGENO}</div>'
);

$mpdf->WriteHTML($html);

file_put_contents($target, $mpdf->Output('', Destination::STRING_RETURN));

printf(
    "Written: %s (%d pages, %s KB)\n",
    $target,
    $mpdf->page,
    number_format(filesize($target) / 1024)
);
