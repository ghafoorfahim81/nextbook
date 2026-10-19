<?php

/**
 * Bug class three from docs/phase-2-handoff.md: a method whose signature moved
 * up to the company while a caller still hands it a branch.
 *
 * Collects every function in app/ with a `$companyId` parameter, remembers the
 * parameter's position, then reports each call to a function of that name
 * whose argument in that position — or named `companyId:` argument — mentions
 * a branch. Matching is by name, not by type, so an unrelated method sharing
 * the name can produce a false positive; a call passing a variable whose name
 * hides the level (`$id`) produces a false negative. The run is the start of a
 * review, not its end.
 *
 * Usage: php tools/phase-2/signature_mismatch.php [dir ...]
 */

$dirs = array_slice($argv, 1) ?: ['app', 'database', 'tests'];

$files = [];
foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() === 'php') {
            $files[] = $f->getPathname();
        }
    }
}

/** Split an argument list on top-level commas. */
function splitArgs(string $s): array
{
    $args = [];
    $depth = 0;
    $cur = '';
    $quote = null;
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        $c = $s[$i];
        if ($quote !== null) {
            $cur .= $c;
            if ($c === '\\') {
                $cur .= $s[++$i] ?? '';
            } elseif ($c === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($c === '"' || $c === "'") {
            $quote = $c;
        } elseif (strpbrk($c, '([{') !== false) {
            $depth++;
        } elseif (strpbrk($c, ')]}') !== false) {
            $depth--;
        } elseif ($c === ',' && $depth === 0) {
            $args[] = trim($cur);
            $cur = '';
            continue;
        }
        $cur .= $c;
    }
    if (trim($cur) !== '') {
        $args[] = trim($cur);
    }

    return $args;
}

/** The balanced (...) starting at $open, without the parens. */
function balanced(string $src, int $open): ?string
{
    $depth = 0;
    for ($i = $open, $n = strlen($src); $i < $n; $i++) {
        if ($src[$i] === '(') {
            $depth++;
        } elseif ($src[$i] === ')') {
            $depth--;
            if ($depth === 0) {
                return substr($src, $open + 1, $i - $open - 1);
            }
        }
    }

    return null;
}

// Pass 1: functions taking $companyId, and its position.
$targets = [];
foreach ($files as $file) {
    $src = file_get_contents($file);
    if (! preg_match_all('/function\s+(\w+)\s*\(/', $src, $m, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    foreach ($m[1] as $k => [$name, $off]) {
        $params = balanced($src, $m[0][$k][1] + strlen($m[0][$k][0]) - 1);
        if ($params === null) {
            continue;
        }
        foreach (splitArgs($params) as $pos => $param) {
            if (preg_match('/\$companyId\b/', $param)) {
                $targets[$name][$pos] = true;
            }
        }
    }
}

// Pass 2: calls handing those positions a branch.
$hits = [];
foreach ($files as $file) {
    $src = file_get_contents($file);
    if (! preg_match_all('/(?:->|::|\bnew\s+|(?<![\w$>:]))(\w+)\s*\(/', $src, $m, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    foreach ($m[1] as $k => [$name, $off]) {
        if (! isset($targets[$name])) {
            continue;
        }
        $before = substr($src, max(0, $m[0][$k][1] - 9), 9);
        if (str_contains($before, 'function')) {
            continue;
        }
        $args = balanced($src, $m[0][$k][1] + strlen($m[0][$k][0]) - 1);
        if ($args === null) {
            continue;
        }
        $list = splitArgs($args);
        $suspect = [];
        foreach ($list as $pos => $arg) {
            if (preg_match('/^companyId\s*:\s*(.*)$/s', $arg, $named)) {
                $arg = $named[1];
            } elseif (preg_match('/^\w+\s*:/', $arg) || ! isset($targets[$name][$pos])) {
                continue;
            }
            if (preg_match('/branch/i', $arg) && ! preg_match('/company/i', $arg)) {
                $suspect[] = $arg;
            }
        }
        if ($suspect) {
            $line = substr_count($src, "\n", 0, $m[0][$k][1]) + 1;
            $hits[] = sprintf('%s:%d  %s(... %s ...)', $file, $line, $name, implode(', ', $suspect));
        }
    }
}

echo 'BRANCH PASSED WHERE A COMPANY IS EXPECTED ('.count($hits)."):\n";
foreach ($hits as $h) {
    echo "  $h\n";
}
