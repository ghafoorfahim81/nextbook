<?php

/**
 * Renders the customer getting-started guide (docs/customer-getting-started*.md)
 * into PDFs: English, Dari and Pashto.
 *
 * The Arabic-script editions use the same IranYekan registration as
 * build-hr-guide.php; useOTL is what makes mPDF join the letters.
 *
 * Usage:  php docs/build-getting-started.php
 */

require __DIR__.'/../vendor/autoload.php';

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

$root = dirname(__DIR__);
$tempDir = $root.'/storage/app/mpdf-temp';

if (! is_dir($tempDir)) {
    mkdir($tempDir, 0775, true);
}

$editions = [
    'en' => ['file' => 'customer-getting-started', 'rtl' => false, 'header' => 'NextBook: Getting started'],
    'fa' => ['file' => 'customer-getting-started-fa', 'rtl' => true, 'font' => 'iranyekan', 'header' => 'شروع کار با نکست‌بوک'],
    'ps' => ['file' => 'customer-getting-started-ps', 'rtl' => true, 'font' => 'pashto', 'header' => 'د نکست‌بوک سره پیل'],
];

$css = <<<'CSS'
body { color: #1f2937; line-height: 1.55; }
h1 { font-size: 20pt; color: #6d28d9; margin: 0 0 6pt; }
h2 { font-size: 14pt; color: #6d28d9; border-bottom: 1px solid #ddd6fe; padding-bottom: 2pt; margin-top: 16pt; }
h3 { font-size: 11.5pt; color: #374151; margin-top: 12pt; }
h4 { font-size: 10.5pt; color: #374151; margin-top: 10pt; }
table { border-collapse: collapse; width: 100%; margin: 6pt 0; }
th { background: #f5f3ff; color: #4c1d95; text-align: start; }
th, td { border: 0.5px solid #d1d5db; padding: 4pt 5pt; vertical-align: top; }
blockquote { background: #fffbeb; border-inline-start: 3px solid #f59e0b; margin: 8pt 0; padding: 4pt 8pt; }
hr { border: 0; border-top: 0.5px solid #e5e7eb; margin: 10pt 0; }
ul, ol { margin: 4pt 0; }
CSS;

foreach ($editions as $edition) {
    $markdown = file_get_contents($root.'/docs/'.$edition['file'].'.md');

    // The fonts carry neither the emoji nor the task-list boxes the Markdown uses.
    $markdown = preg_replace('/^(\s*)- \[ \]/m', '$1- ☐', $markdown);
    $markdown = str_replace(['⚠️', '🎉'], '', $markdown);

    $html = (new GithubFlavoredMarkdownConverter())->convert($markdown)->getContent();

    $fontDirs = (new ConfigVariables())->getDefaults()['fontDir'];
    $fontData = (new FontVariables())->getDefaults()['fontdata'];

    $config = [
        'mode' => 'utf-8',
        'format' => 'A4',
        'tempDir' => $tempDir,
        'default_font_size' => 10,
        'margin_top' => 18,
        'margin_bottom' => 18,
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_header' => 7,
        'margin_footer' => 8,
    ];

    if ($edition['rtl']) {
        // IranYekan is a Persian face and lacks the Pashto letters (ټ ډ ړ ږ ښ ڼ ې ۍ
        // ګ څ ځ). Neither does any font mPDF bundles have ې and ۍ, so the Pashto
        // edition uses Arial when this machine has it and DejaVu Sans otherwise.
        $arial = 'C:/Windows/Fonts/arial.ttf';
        $pashto = is_file($arial)
            ? ['dir' => 'C:/Windows/Fonts', 'R' => 'arial.ttf', 'B' => 'arialbd.ttf']
            : ['dir' => $root.'/vendor/mpdf/mpdf/ttfonts', 'R' => 'DejaVuSans.ttf', 'B' => 'DejaVuSans-Bold.ttf'];

        $config += [
            'fontDir' => array_merge($fontDirs, [$root.'/public/fonts/fa', $pashto['dir']]),
            'fontdata' => $fontData + [
                'iranyekan' => [
                    'R' => 'Qs_Iranyekan.ttf',
                    'B' => 'Qs_Iranyekan bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
                'pashto' => [
                    'R' => $pashto['R'],
                    'B' => $pashto['B'],
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font' => $edition['font'],
            'directionality' => 'rtl',
        ];
    } else {
        $config['default_font'] = 'dejavusans';
    }

    $mpdf = new Mpdf($config);
    $mpdf->SetTitle($edition['header']);
    $mpdf->SetAuthor('Nextbook');
    $mpdf->showImageErrors = false;
    // The menu arrow and a few punctuation marks are missing from IranYekan.
    $mpdf->useSubstitutions = true;
    $mpdf->backupSubsFont = ['dejavusans'];

    $direction = $edition['rtl'] ? 'rtl' : 'ltr';
    $align = $edition['rtl'] ? 'right' : 'left';

    $mpdf->SetHTMLHeader(
        '<div dir="'.$direction.'" style="direction:'.$direction.';text-align:'.$align.';font-size:7.5pt;color:#9ca3af;'
        .'border-bottom:0.5px solid #e5e7eb;padding-bottom:2pt;">'.$edition['header'].'</div>'
    );
    $mpdf->SetHTMLFooter('<div style="text-align:center;font-size:7.5pt;color:#9ca3af;">{PAGENO}</div>');

    $mpdf->WriteHTML('<style>'.$css.'</style>', \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML('<div dir="'.$direction.'">'.$html.'</div>', \Mpdf\HTMLParserMode::HTML_BODY);

    file_put_contents(
        $root.'/docs/'.$edition['file'].'.pdf',
        $mpdf->Output('', Destination::STRING_RETURN)
    );

    echo "built {$edition['file']}.pdf\n";
}
