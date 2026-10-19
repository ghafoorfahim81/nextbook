<?php

require_once __DIR__ . '/bootstrap.php';
$root = repo('database/migrations');
$tables = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$files = [];
foreach ($it as $f) {
    if ($f->getExtension() === 'php') $files[] = $f->getPathname();
}
sort($files);
foreach ($files as $path) {
    $src = file_get_contents($path);
    preg_match_all("/Schema::create\('([a-z_]+)'/", $src, $m, PREG_OFFSET_CAPTURE);
    foreach ($m[1] as $i => $hit) {
        $t = $hit[0];
        $start = $m[0][$i][1];
        $end = isset($m[0][$i + 1]) ? $m[0][$i + 1][1] : strlen($src);
        $body = substr($src, $start, $end - $start);
        preg_match_all("/->(?:foreignUlid|foreignId|ulid|uuid)\('([a-z_]+)'/", $body, $c);
        $fks = array_values(array_unique(array_filter($c[1], fn($x) => str_ends_with($x, '_id') && $x !== 'branch_id')));
        preg_match_all("/->unique\(\[([^\]]*)\]/", $body, $u);
        $tables[$t] = [
            'branch' => str_contains($body, 'branch_id'),
            'fks' => $fks,
            'uniques' => $u[1],
        ];
    }
}
$skip = ['cache','cache_locks','jobs','job_batches','failed_jobs','sessions','password_reset_tokens','personal_access_tokens','notifications','countries','provinces','migrations'];
ksort($tables);
$n = 0;
foreach ($tables as $t => $d) {
    if (in_array($t, $skip)) continue;
    $n++;
    printf("%s %-34s | %s\n", $d['branch'] ? 'B' : '.', $t, substr(implode(',', $d['fks']), 0, 88));
}
echo "\nTOTAL: $n\n";
