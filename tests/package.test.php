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
fyrst_assert(
    str_contains((string) ($composer['description'] ?? ''), 'docker/Dockerfile'),
    'description names docker/Dockerfile from shopware/docker'
);
$suggestDocker = (string) ($composer['suggest']['shopware/docker'] ?? '');
fyrst_assert($suggestDocker !== '', 'suggest.shopware/docker is present');
fyrst_assert(
    str_contains($suggestDocker, 'Required'),
    'suggest.shopware/docker marks shopware/docker as required'
);
fyrst_assert(
    str_contains($suggestDocker, 'docker/Dockerfile'),
    'suggest.shopware/docker names docker/Dockerfile'
);
fyrst_assert(
    str_contains($suggestDocker, 'DOCKERFILE=docker/Dockerfile'),
    'suggest.shopware/docker documents CI DOCKERFILE=docker/Dockerfile'
);
fyrst_assert(
    str_contains($suggestDocker, 'same composer require'),
    'suggest.shopware/docker requires the same composer require line'
);
fyrst_assert(
    !preg_match('/fallback/i', $suggestDocker),
    'suggest.shopware/docker has no fallback wording'
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
fyrst_assert(!is_file($root . '/Dockerfile'), 'shop-root Dockerfile is not in this package');
fyrst_assert(!is_file($root . '/docker/Dockerfile'), 'shop docker/Dockerfile is not in this package');

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
fyrst_assert(
    str_contains($readme, 'docker/Dockerfile'),
    'README names docker/Dockerfile'
);
fyrst_assert(
    str_contains($readme, 'DOCKERFILE=docker/Dockerfile'),
    'README documents CI DOCKERFILE=docker/Dockerfile'
);
fyrst_assert(
    str_contains($readme, 'composer require shopware/docker shopware/deployment-helper fyrst/shopware-cd'),
    'README uses the same composer require line as fyrst/shopware-cd'
);
fyrst_assert(
    str_contains($readme, '**must** `composer require shopware/docker`'),
    'README requires shopware/docker'
);

$create = (string) file_get_contents($root . '/CREATE.md');
fyrst_assert(
    str_contains($create, 'does **not** contain overlay files'),
    'CREATE.md states this package does not contain overlay files'
);
fyrst_assert(
    str_contains($create, 'docker/Dockerfile'),
    'CREATE.md names docker/Dockerfile'
);
fyrst_assert(
    str_contains($create, 'DOCKERFILE=docker/Dockerfile'),
    'CREATE.md documents CI DOCKERFILE=docker/Dockerfile'
);
fyrst_assert(
    str_contains($create, 'composer require shopware/docker shopware/deployment-helper fyrst/shopware-cd'),
    'CREATE.md uses the same composer require line as fyrst/shopware-cd'
);

fyrst_assert(
    str_contains($readme, 'does **not** copy `compose.yaml`, `.gitignore`, or `.shopware-project.yaml`'),
    'README states Flex does not copy CLI-owned compose.yaml / .gitignore / .shopware-project.yaml'
);
fyrst_assert(
    str_contains($readme, '`shopware-cli project create` owns those'),
    'README states shopware-cli project create owns those files'
);
fyrst_assert(
    str_contains($readme, 'deploy/compose.yaml'),
    'README names deploy/compose.yaml as CD Compose'
);
fyrst_assert(
    str_contains($readme, 'deploy/compose.prod.yaml'),
    'README names deploy/compose.prod.yaml'
);
fyrst_assert(
    str_contains($readme, 'deploy/compose.vps.yaml'),
    'README names deploy/compose.vps.yaml'
);
fyrst_assert(
    str_contains($readme, 'deploy/sync-runtime.sh'),
    'README names deploy/sync-runtime.sh as Flex overlay'
);
fyrst_assert(
    str_contains($readme, '**No S3.**'),
    'README states VPS runtime sync does not use S3'
);
fyrst_assert(
    str_contains($readme, 'SSH + `mysqldump`'),
    'README states runtime sync is SSH + dump + volume archives'
);
fyrst_assert(
    str_contains($readme, 'live → staging / playground / dev'),
    'README states sync direction live → staging/playground/dev'
);
fyrst_assert(
    str_contains($readme, 'on the consumer'),
    'README states cron/script runs on the consumer'
);
fyrst_assert(
    str_contains($readme, '**out of git** and **out of the image**'),
    'README states runtime DB/volumes stay out of git and the image'
);
fyrst_assert(
    str_contains($readme, 'does **not** use the CLI-managed root `compose.yaml`'),
    'README states VPS does not use the CLI-managed root compose.yaml'
);
fyrst_assert(
    !str_contains($readme, 'dual CI, Compose, `deploy/`, `.shopware-project.yaml`'),
    'README does not list CLI-owned files as Flex copies'
);
fyrst_assert(
    !str_contains($readme, '## Compose layout (after Flex)'),
    'README does not treat root Compose as Flex-copied'
);

fyrst_assert(
    str_contains($create, 'The fyrst Flex recipe does **not** copy those'),
    'CREATE.md states Flex does not copy CLI-owned files'
);
fyrst_assert(
    str_contains($create, 'deploy/compose.yaml'),
    'CREATE.md names deploy/compose.yaml'
);
fyrst_assert(
    str_contains($create, 'deploy/sync-runtime.sh'),
    'CREATE.md names deploy/sync-runtime.sh as Flex overlay'
);
fyrst_assert(
    str_contains($create, '**No S3.**'),
    'CREATE.md states VPS runtime sync does not use S3'
);
fyrst_assert(
    str_contains($create, 'SSH + dump + volume archives'),
    'CREATE.md states runtime sync is SSH + dump + volume archives'
);
fyrst_assert(
    str_contains($create, 'live → staging/playground/dev'),
    'CREATE.md states sync direction live → staging/playground/dev'
);
fyrst_assert(
    str_contains($create, 'on the consumer'),
    'CREATE.md states cron runs on the consumer'
);
fyrst_assert(
    str_contains($create, '**out of git** and **out of the image**'),
    'CREATE.md states runtime DB/volumes stay out of git and the image'
);
fyrst_assert(
    str_contains($create, 'not the CLI-managed root `compose.yaml`'),
    'CREATE.md states VPS does not use root compose.yaml'
);
fyrst_assert(
    !str_contains($create, 'Flex copied `.github/workflows/cd.yaml`, `.gitlab-ci.yaml`, `compose.yaml`, `compose.prod.yaml`, `deploy/`'),
    'CREATE.md does not list root compose.yaml as Flex-copied'
);

fyrst_assert(
    str_contains((string) ($composer['description'] ?? ''), 'owned by shopware-cli'),
    'description states shopware-cli owns root compose.yaml'
);
fyrst_assert(
    str_contains((string) ($composer['description'] ?? ''), 'deploy Compose'),
    'description says deploy Compose not root Compose'
);
fyrst_assert(
    !str_contains((string) ($composer['description'] ?? ''), 'CI, Compose, deploy'),
    'description does not use old Flex-copies-Compose wording'
);

$docs = [
    'README.md' => $readme,
    'CREATE.md' => $create,
    'composer.json' => (string) file_get_contents($root . '/composer.json'),
];
foreach ($docs as $name => $text) {
    fyrst_assert(
        !preg_match('/fallback/i', $text),
        "{$name} has no Dockerfile fallback wording"
    );
    fyrst_assert(
        !preg_match('/prefer this/i', $text),
        "{$name} does not use dual-path prefer/fallback wording"
    );
    fyrst_assert(
        !preg_match('/root `?Dockerfile`? as fallback/i', $text),
        "{$name} does not describe a root Dockerfile fallback"
    );
    fyrst_assert(
        !preg_match('/Flex (loads|copied|copies) CI, Compose/', $text),
        "{$name} does not say Flex copies Compose at shop root"
    );
}

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "\nAll package tests passed.\n");
exit(0);
