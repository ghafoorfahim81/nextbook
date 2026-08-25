<?php

/**
 * Renders docs/hr-user-guide-fa.html into a Persian PDF.
 *
 * Uses the same IranYekan registration as PdfExportService: mPDF's bundled
 * fonts have no Arabic-script coverage at all, and the useOTL flag is what
 * actually drives Arabic glyph shaping and joining — without it the text
 * renders as disconnected letters.
 *
 * Usage:  php docs/build-hr-guide.php
 */

require __DIR__.'/../vendor/autoload.php';

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

$root = dirname(__DIR__);
$source = $root.'/docs/hr-user-guide-fa.html';
$target = $root.'/docs/hr-user-guide-fa.pdf';
$fontPath = $root.'/public/fonts/fa';
$tempDir = $root.'/storage/app/mpdf-temp';

if (! is_dir($tempDir)) {
    mkdir($tempDir, 0775, true);
}

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
    'margin_top' => 16,
    'margin_bottom' => 18,
    'margin_left' => 14,
    'margin_right' => 14,
    'margin_header' => 6,
    'margin_footer' => 8,
    'directionality' => 'rtl',
]);

$mpdf->SetTitle('راهنمای ماژول منابع بشری — نکست‌بوک');
$mpdf->SetAuthor('Nextbook');
$mpdf->SetCreator('Nextbook');
$mpdf->showImageErrors = false;

// The running header belongs on the right in an RTL document; dir has to be
// stated here too, because header/footer HTML is parsed separately from the body.
$mpdf->SetHTMLHeader(
    '<div dir="rtl" style="direction:rtl;text-align:right;font-size:7.5pt;color:#9ca3af;'
    .'border-bottom:0.5px solid #e5e7eb;padding-bottom:2pt;">'
    .'راهنمای ماژول منابع بشری — نکست‌بوک</div>'
);

$mpdf->SetHTMLFooter(
    '<div style="text-align:center;font-size:7.5pt;color:#9ca3af;">{PAGENO}</div>'
);

$html = file_get_contents($source);

// Page 1 is the cover; the header would clutter it.
$mpdf->WriteHTML($html);

file_put_contents($target, $mpdf->Output('', Destination::STRING_RETURN));

printf(
    "Written: %s (%s pages, %s KB)\n",
    $target,
    $mpdf->page,
    number_format(filesize($target) / 1024)
);
