<?php

declare(strict_types=1);

/**
 * composer require fyrst/shopware-cd (path repo) must apply overlay into a shop.
 */

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "package root not found\n");
    exit(1);
}

function fyrst_rmdir(string $dir): void
{
    if ($dir === '' || !is_dir($dir)) {
        return;
    }
    // Unlink vendor path-repo symlinks; do not recurse into the package checkout.
    passthru('rm -rf ' . escapeshellarg($dir));
}

$shop = sys_get_temp_dir() . '/fyrst-plugin-' . bin2hex(random_bytes(4));
mkdir($shop . '/bin', 0755, true);
file_put_contents($shop . '/bin/console', "#!/usr/bin/env php\n<?php\n");
file_put_contents($shop . '/composer.json', json_encode([
    'name' => 'acme/plugin-shop',
    'require' => [
        'php' => '>=8.2',
    ],
    'config' => [
        'allow-plugins' => [
            'fyrst/shopware-cd' => true,
        ],
    ],
    'minimum-stability' => 'dev',
    'prefer-stable' => true,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$composer = trim((string) shell_exec('command -v composer'));
if ($composer === '') {
    fwrite(STDERR, "composer not found\n");
    exit(1);
}

$run = static function (string $cmd) use ($shop): array {
    $full = 'cd ' . escapeshellarg($shop) . ' && ' . $cmd . ' 2>&1';
    exec($full, $lines, $code);

    return [$code, implode("\n", $lines)];
};

[$code, $out] = $run('composer config repositories.fyrst path ' . escapeshellarg($root));
if ($code !== 0) {
    fwrite(STDERR, $out . "\nFAIL - composer config repositories.fyrst\n");
    fyrst_rmdir($shop);
    exit(1);
}

[$code, $out] = $run('composer require fyrst/shopware-cd:@dev --no-interaction --no-progress');
fwrite(STDOUT, $out . "\n");
if ($code !== 0) {
    fwrite(STDERR, "FAIL - composer require fyrst/shopware-cd:@dev exited {$code}\n");
    fyrst_rmdir($shop);
    exit(1);
}

$failures = 0;
$paths = [
    '.github/workflows/cd.yml',
    '.gitlab-ci.yml',
    'compose.yaml',
    'compose.prod.yaml',
    'deploy/vps-release.sh',
    'Dockerfile',
    '.env.example',
    '.shopware-project.yml',
];
foreach ($paths as $rel) {
    if (!is_file($shop . '/' . $rel)) {
        fwrite(STDERR, "FAIL - composer require did not write {$rel}\n");
        $failures++;
    } else {
        fwrite(STDOUT, "ok - {$rel} after composer require\n");
    }
}

if (!is_file($shop . '/vendor/bin/fyrst-shopware-cd')) {
    fwrite(STDERR, "FAIL - vendor/bin/fyrst-shopware-cd missing\n");
    $failures++;
} else {
    fwrite(STDOUT, "ok - vendor/bin/fyrst-shopware-cd installed\n");
}

file_put_contents($shop . '/compose.yaml', "# shop edit\n");
file_put_contents($shop . '/.env', "APP_SECRET=keep\n");

[$code, $out] = $run('composer update fyrst/shopware-cd --no-interaction --no-progress');
if ($code !== 0) {
    fwrite(STDERR, $out . "\nFAIL - composer update\n");
    $failures++;
} else {
    fwrite(STDOUT, "ok - composer update exits 0\n");
}

if (file_get_contents($shop . '/compose.yaml') !== "# shop edit\n") {
    fwrite(STDERR, "FAIL - update overwrote compose.yaml (should skip existing)\n");
    $failures++;
} else {
    fwrite(STDOUT, "ok - update skipped existing compose.yaml\n");
}

if (file_get_contents($shop . '/.env') !== "APP_SECRET=keep\n") {
    fwrite(STDERR, "FAIL - .env was overwritten\n");
    $failures++;
} else {
    fwrite(STDOUT, "ok - .env still protected after update\n");
}

$apply = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($shop . '/vendor/bin/fyrst-shopware-cd') . ' apply --force';
[$code, $out] = $run($apply);
if ($code !== 0) {
    fwrite(STDERR, $out . "\nFAIL - vendor/bin apply --force\n");
    $failures++;
} elseif (file_get_contents($shop . '/compose.yaml') === "# shop edit\n") {
    fwrite(STDERR, "FAIL - --force did not overwrite compose.yaml\n");
    $failures++;
} else {
    fwrite(STDOUT, "ok - vendor/bin/fyrst-shopware-cd apply --force overwrites template paths\n");
}

if (file_get_contents($shop . '/.env') !== "APP_SECRET=keep\n") {
    fwrite(STDERR, "FAIL - --force overwrote .env\n");
    $failures++;
} else {
    fwrite(STDOUT, "ok - --force still protects .env\n");
}

fyrst_rmdir($shop);

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "\nComposer plugin apply tests passed.\n");
exit(0);
