<?php

require_once __DIR__ . '/bootstrap.php';
$root = repo('database/migrations');
$out = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$files = [];
foreach ($it as $f) if ($f->getExtension() === 'php') $files[] = $f->getPathname();
sort($files);
foreach ($files as $path) {
    $src = file_get_contents($path);
    preg_match_all("/Schema::(?:create|table)\('([a-z_]+)'/", $src, $m, PREG_OFFSET_CAPTURE);
    foreach ($m[1] as $i => $hit) {
        $t = $hit[0];
        $start = $m[0][$i][1];
        $end = isset($m[0][$i + 1]) ? $m[0][$i + 1][1] : strlen($src);
        $body = substr($src, $start, $end - $start);
        preg_match_all("/->unique\(\s*\[([^\]]*)\]/", $body, $u);
        foreach ($u[1] as $cols) {
            $cols = preg_replace("/[\s'\"]/", '', $cols);
            if (str_contains($cols, 'branch_id')) $out[$t][] = $cols;
        }
        // raw SQL unique indexes
        preg_match_all("/CREATE UNIQUE INDEX[^;']*/i", $body, $r);
        foreach ($r[0] as $sql) if (str_contains($sql, 'branch_id')) $out[$t][] = 'RAW: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 110);
    }
}
ksort($out);
$n = 0;
foreach ($out as $t => $list) {
    foreach (array_unique($list) as $c) { printf("%-32s %s\n", $t, $c); $n++; }
}
echo "\nTOTAL unique indexes containing branch_id: $n\n";
