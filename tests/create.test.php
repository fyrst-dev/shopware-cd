<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$bin = $root . '/bin/fyrst-shopware-cd';

$cmd = sprintf(
    '%s %s create dry-run-shop 6.6.0.0 --dry-run --repo=%s',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($bin),
    escapeshellarg($root)
);
exec($cmd . ' 2>&1', $lines, $code);
$out = implode("\n", $lines);

if ($code !== 0) {
    fwrite(STDERR, $out . "\nFAIL - create --dry-run exited {$code}\n");
    exit(1);
}

$needles = [
    'project create dry-run-shop 6.6.0.0',
    '--docker',
    'shopware/docker',
    'shopware/deployment-helper',
    'fyrst/shopware-cd',
    'repositories.fyrst',
    'fyrst-shopware-cd apply',
];
$failures = 0;
foreach ($needles as $needle) {
    if (!str_contains($out, $needle)) {
        fwrite(STDERR, "FAIL - dry-run output missing: {$needle}\n");
        $failures++;
    } else {
        fwrite(STDOUT, "ok - dry-run contains {$needle}\n");
    }
}

if (is_dir($root . '/dry-run-shop') || is_dir(getcwd() . '/dry-run-shop')) {
    fwrite(STDERR, "FAIL - dry-run created a shop directory\n");
    $failures++;
} else {
    fwrite(STDOUT, "ok - dry-run does not create a directory\n");
}

exec(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' create --help 2>&1',
    $helpLines,
    $helpCode
);
if ($helpCode !== 0) {
    fwrite(STDERR, "FAIL - create --help exited {$helpCode}\n");
    $failures++;
} else {
    fwrite(STDOUT, "ok - create --help exits 0\n");
}

exec(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' create 2>&1',
    $miss,
    $missCode
);
if ($missCode === 0) {
    fwrite(STDERR, "FAIL - create without a name should fail\n");
    $failures++;
} else {
    fwrite(STDOUT, "ok - create without a name fails\n");
}

if ($failures > 0) {
    fwrite(STDERR, "\n{$out}\n{$failures} assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "\nAll create tests passed.\n");
exit(0);
