<?php

declare(strict_types=1);

/**
 * Self-contained tests for overlay apply (no PHPUnit).
 */

$root = dirname(__DIR__);
require_once $root . '/src/ShopDetector.php';
require_once $root . '/src/OverlayLocator.php';
require_once $root . '/src/ApplyResult.php';
require_once $root . '/src/OverlayApplier.php';

$failures = 0;

function fyrst_assert(bool $ok, string $message): void
{
    global $failures;
    if ($ok) {
        fwrite(STDOUT, "ok - {$message}\n");
        return;
    }
    fwrite(STDERR, "FAIL - {$message}\n");
    $failures++;
}

function fyrst_tmp(string $prefix): string
{
    $dir = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(4));
    if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("mkdir {$dir}");
    }

    return $dir;
}

function fyrst_rmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($dir);
}

function fyrst_shop(string $dir, array $composer = []): void
{
    $json = array_merge([
        'name' => 'acme/test-shop',
        'require' => ['shopware/core' => '^6.6'],
    ], $composer);
    file_put_contents($dir . '/composer.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    mkdir($dir . '/bin', 0755, true);
    file_put_contents($dir . '/bin/console', "#!/usr/bin/env php\n<?php\necho \"fake\\n\";\n");
    chmod($dir . '/bin/console', 0755);
}

$package = $root;
$overlay = $root . '/overlay';
$expected = [
    '.github/workflows/cd.yml',
    '.gitlab-ci.yml',
    'compose.yaml',
    'compose.prod.yaml',
    'deploy/vps-release.sh',
    'deploy/compose.vps.yaml',
    'deploy/README.md',
    'deploy/managed/README.md',
    'Dockerfile',
    '.dockerignore',
    '.env.example',
    '.shopware-project.yml',
    '.gitignore',
];

fyrst_assert(is_dir($overlay), 'overlay/ exists');
foreach ($expected as $rel) {
    fyrst_assert(is_file($overlay . '/' . $rel), "overlay has {$rel}");
}

$shop = fyrst_tmp('fyrst-shop');
fyrst_shop($shop);

fyrst_assert(Fyrst\ShopwareCd\ShopDetector::isShopwareProject($shop), 'detects shop via shopware/core');
fyrst_assert(Fyrst\ShopwareCd\ShopDetector::isTemplateRoot($package), 'detects package root');
fyrst_assert(!Fyrst\ShopwareCd\ShopDetector::isShopwareProject($package), 'package root is not a shop');

$bin = $root . '/bin/fyrst-shopware-cd';
$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' apply --dir=' . escapeshellarg($shop);
exec($cmd . ' 2>&1', $outLines, $code);
$out = implode("\n", $outLines);
fyrst_assert($code === 0, 'apply exits 0');
foreach ($expected as $rel) {
    fyrst_assert(is_file($shop . '/' . $rel), "wrote shop path {$rel}");
}
fyrst_assert(is_executable($shop . '/deploy/vps-release.sh'), 'deploy/vps-release.sh is executable');
fyrst_assert(!is_file($shop . '/.env'), 'did not write .env');
fyrst_assert(!is_file($shop . '/README.md'), 'did not overwrite/write package README into the shop');
fyrst_assert(str_contains($out, 'written'), 'summary mentions written');

$firstCd = file_get_contents($shop . '/.github/workflows/cd.yml');
fyrst_assert(is_string($firstCd) && str_contains($firstCd, 'name: CD'), 'cd.yml is the GitHub workflow');

file_put_contents($shop . '/.env', "APP_SECRET=keep-me\n");
file_put_contents($shop . '/compose.yaml', "# shop-specific\n");

$outLines = [];
exec($cmd . ' 2>&1', $outLines, $code);
$out = implode("\n", $outLines);
fyrst_assert($code === 0, 'second apply exits 0');
fyrst_assert(str_contains($out, 'skipped'), 'second apply skips existing');
fyrst_assert(file_get_contents($shop . '/compose.yaml') === "# shop-specific\n", 'skipped compose.yaml keeps shop edits');
fyrst_assert(file_get_contents($shop . '/.env') === "APP_SECRET=keep-me\n", '.env untouched on second apply');

$forceCmd = $cmd . ' --force';
$outLines = [];
exec($forceCmd . ' 2>&1', $outLines, $code);
fyrst_assert($code === 0, '--force apply exits 0');
fyrst_assert(file_get_contents($shop . '/compose.yaml') !== "# shop-specific\n", '--force overwrites template-managed compose.yaml');
fyrst_assert(file_get_contents($shop . '/.env') === "APP_SECRET=keep-me\n", '--force still never writes .env');

$outLines = [];
exec($cmd . ' --dry-run --force 2>&1', $outLines, $code);
fyrst_assert($code === 0, '--dry-run exits 0');

$refuse = [];
exec(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' apply --dir=' . escapeshellarg($package) . ' 2>&1',
    $refuse,
    $code
);
fyrst_assert($code === 2, 'apply into package root is refused');

$miniOverlay = fyrst_tmp('fyrst-overlay');
file_put_contents($miniOverlay . '/.env', "SECRET=from-overlay\n");
file_put_contents($miniOverlay . '/.env.example', "SECRET=\n");
mkdir($miniOverlay . '/custom/plugins', 0755, true);
file_put_contents($miniOverlay . '/custom/plugins/evil.php', "<?php\n");
file_put_contents($miniOverlay . '/hello.txt', "ok\n");

$shop2 = fyrst_tmp('fyrst-shop2');
fyrst_shop($shop2);
file_put_contents($shop2 . '/.env', "SECRET=original\n");

$cmd2 = sprintf(
    '%s %s apply --dir=%s --overlay=%s --force',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($bin),
    escapeshellarg($shop2),
    escapeshellarg($miniOverlay)
);
$outLines = [];
exec($cmd2 . ' 2>&1', $outLines, $code);
fyrst_assert($code === 0, 'mini overlay apply exits 0');
fyrst_assert(is_file($shop2 . '/hello.txt'), 'copies unprotected file');
fyrst_assert(is_file($shop2 . '/.env.example'), 'copies .env.example');
fyrst_assert(file_get_contents($shop2 . '/.env') === "SECRET=original\n", 'never copies overlay .env over shop .env');
fyrst_assert(!is_file($shop2 . '/custom/plugins/evil.php'), 'never copies custom/ shop app code');

$noShop = fyrst_tmp('fyrst-empty');
$outLines = [];
exec(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' apply --dir=' . escapeshellarg($noShop) . ' 2>&1',
    $outLines,
    $code
);
fyrst_assert($code === 2, 'non-shop directory is refused');

$consoleOnly = fyrst_tmp('fyrst-console');
mkdir($consoleOnly . '/bin', 0755, true);
file_put_contents($consoleOnly . '/bin/console', "<?php\n");
file_put_contents($consoleOnly . '/composer.json', json_encode(['name' => 'acme/symfony-ish'], JSON_PRETTY_PRINT) . "\n");
fyrst_assert(Fyrst\ShopwareCd\ShopDetector::isShopwareProject($consoleOnly), 'bin/console counts as a shop');

fyrst_rmdir($shop);
fyrst_rmdir($shop2);
fyrst_rmdir($miniOverlay);
fyrst_rmdir($noShop);
fyrst_rmdir($consoleOnly);

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "\nAll apply tests passed.\n");
exit(0);
