<?php

declare(strict_types=1);

/**
 * Validates the Flex recipe layout (contrib-compatible) without Symfony Flex.
 */

$root = dirname(__DIR__);
$recipe = $root . '/flex-recipe/fyrst/shopware-cd/1.0';
$recipeRoot = $recipe . '/root';
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

$composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
fyrst_assert(is_array($composer), 'composer.json is JSON');
fyrst_assert(($composer['name'] ?? '') === 'fyrst/shopware-cd', 'package name fyrst/shopware-cd');
fyrst_assert(($composer['type'] ?? '') === 'library', 'type is library (not composer-plugin)');
fyrst_assert(($composer['license'] ?? '') === 'MIT', 'MIT license for Packagist');
fyrst_assert(!isset($composer['extra']['class']), 'no Composer plugin extra.class');
fyrst_assert(!isset($composer['bin']), 'no composer bin wrappers');
fyrst_assert(!isset($composer['scripts']['post-install-cmd']), 'no post-install-cmd copier');
fyrst_assert(!is_dir($root . '/bin'), 'bin/ removed');
fyrst_assert(!is_dir($root . '/src'), 'src/ plugin/CLI removed');
fyrst_assert(!is_dir($root . '/scripts'), 'scripts/ create wrapper removed');
fyrst_assert(!is_dir($root . '/overlay'), 'overlay/ moved into the recipe');
fyrst_assert(is_file($root . '/LICENSE'), 'LICENSE file present');

$manifestFile = $recipe . '/manifest.json';
fyrst_assert(is_file($manifestFile), 'manifest.json exists');
$manifest = json_decode((string) file_get_contents($manifestFile), true);
fyrst_assert(is_array($manifest), 'manifest.json is JSON');
fyrst_assert(($manifest['copy-from-recipe']['root/'] ?? null) === '', 'copy-from-recipe maps root/ to shop project root');
fyrst_assert(is_file($recipe . '/post-install.txt'), 'post-install.txt exists');

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

fyrst_assert(is_dir($recipeRoot), 'recipe root/ exists');
foreach ($expected as $rel) {
    fyrst_assert(is_file($recipeRoot . '/' . $rel), "recipe has root/{$rel}");
}

fyrst_assert(!is_file($recipeRoot . '/.env'), 'recipe does not contain .env');
fyrst_assert(is_executable($recipeRoot . '/deploy/vps-release.sh'), 'deploy/vps-release.sh is executable');

$cd = (string) file_get_contents($recipeRoot . '/.github/workflows/cd.yml');
fyrst_assert(str_contains($cd, 'name: CD'), 'cd.yml is the GitHub CD workflow');
fyrst_assert(!is_file($root . '/.github/workflows/cd.yml'), 'shop CD is not at package .github (recipe only)');

// Simulate Flex copy-from-recipe "root/" → ""
$shop = sys_get_temp_dir() . '/fyrst-flex-' . bin2hex(random_bytes(4));
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($recipeRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($it as $file) {
    $abs = str_replace('\\', '/', $file->getPathname());
    $rel = substr($abs, strlen(str_replace('\\', '/', $recipeRoot)) + 1);
    $dest = $shop . '/' . $rel;
    if ($file->isDir()) {
        mkdir($dest, 0755, true);
        continue;
    }
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    copy($file->getPathname(), $dest);
    chmod($dest, $file->getPerms() & 0777);
}

foreach ($expected as $rel) {
    fyrst_assert(is_file($shop . '/' . $rel), "Flex mapping would write shop path {$rel}");
}
fyrst_assert(is_executable($shop . '/deploy/vps-release.sh'), 'copied vps-release.sh stays executable');
fyrst_assert(!is_file($shop . '/.env'), 'simulated copy does not write .env');
fyrst_assert(!is_file($shop . '/README.md'), 'does not overwrite a shop README');

passthru('rm -rf ' . escapeshellarg($shop));

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "\nAll recipe tests passed.\n");
exit(0);
