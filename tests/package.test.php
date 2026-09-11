<?php

declare(strict_types=1);

/**
 * Smoke tests for the thin Packagist package (no Flex overlay copies here).
 */

$root = dirname(__DIR__);
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
fyrst_assert(
    ($composer['homepage'] ?? '') === 'https://github.com/fyrst-dev/shopware-cd',
    'homepage is fyrst-dev/shopware-cd'
);
fyrst_assert(
    ($composer['support']['docs'] ?? '') === 'https://github.com/fyrst-dev/recipes',
    'support.docs points at fyrst-dev/recipes'
);
fyrst_assert(
    ($composer['support']['source'] ?? '') === 'https://github.com/fyrst-dev/shopware-cd',
    'support.source is fyrst-dev/shopware-cd'
);
fyrst_assert(
    ($composer['support']['issues'] ?? '') === 'https://github.com/fyrst-dev/shopware-cd/issues',
    'support.issues is fyrst-dev/shopware-cd'
);
fyrst_assert(
    str_contains((string) ($composer['description'] ?? ''), 'fyrst-dev/recipes'),
    'description mentions fyrst-dev/recipes'
);
fyrst_assert(!is_dir($root . '/bin'), 'bin/ removed');
fyrst_assert(!is_dir($root . '/src'), 'src/ plugin/CLI removed');
fyrst_assert(!is_dir($root . '/scripts'), 'scripts/ create wrapper removed');
fyrst_assert(!is_dir($root . '/overlay'), 'overlay/ not in this package');
fyrst_assert(!is_dir($root . '/flex-recipe'), 'flex-recipe/ not in this package');
fyrst_assert(is_file($root . '/LICENSE'), 'LICENSE file present');
fyrst_assert(is_file($root . '/README.md'), 'README.md present');
fyrst_assert(is_file($root . '/CREATE.md'), 'CREATE.md present');
fyrst_assert(!is_file($root . '/.github/workflows/cd.yml'), 'shop CD is not at package .github');
fyrst_assert(!is_file($root . '/.github/workflows/cd.yaml'), 'shop CD yaml is not at package .github');
fyrst_assert(!is_file($root . '/.gitlab-ci.yaml'), 'shop GitLab CI is not in this package');
fyrst_assert(!is_file($root . '/compose.yaml'), 'shop compose.yaml is not in this package');
fyrst_assert(!is_file($root . '/Dockerfile'), 'shop Dockerfile is not in this package');

$readme = (string) file_get_contents($root . '/README.md');
fyrst_assert(
    str_contains($readme, 'does **not** contain overlay files'),
    'README states this package does not contain overlay files'
);
fyrst_assert(
    str_contains($readme, 'https://raw.githubusercontent.com/fyrst-dev/recipes/flex/main/index.json'),
    'README documents the fyrst-dev/recipes Flex endpoint'
);
fyrst_assert(
    !str_contains($readme, 'flex-recipe/'),
    'README does not list flex-recipe/ as part of this repo'
);
fyrst_assert(
    str_contains($readme, 'https://github.com/fyrst-dev/shopware-cd'),
    'README links to fyrst-dev/shopware-cd'
);
fyrst_assert(
    !str_contains($readme, 'shopware-cd-template'),
    'README does not use the old shopware-cd-template repo name'
);

$create = (string) file_get_contents($root . '/CREATE.md');
fyrst_assert(
    str_contains($create, 'does **not** contain overlay files'),
    'CREATE.md states this package does not contain overlay files'
);

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "\nAll package tests passed.\n");
exit(0);
