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
    str_contains($readme, 'deploy/sync-runtime-local.sh'),
    'README names deploy/sync-runtime-local.sh as Flex overlay'
);
fyrst_assert(
    str_contains($readme, '**No S3.**'),
    'README states VPS runtime sync does not use S3'
);
fyrst_assert(
    str_contains($readme, 'SSH + `mysqldump`'),
    'README states runtime sync is SSH + dump'
);
fyrst_assert(
    str_contains($readme, 'rsync of those host dirs'),
    'README states deploy/sync-runtime.sh rsyncs SHOPWARE_DATA_ROOT host dirs'
);
fyrst_assert(
    str_contains($readme, 'SHOPWARE_DATA_ROOT'),
    'README names SHOPWARE_DATA_ROOT for VPS bind mounts'
);
fyrst_assert(
    str_contains($readme, 'SHOPWARE_SHOP_ID'),
    'README names SHOPWARE_SHOP_ID as the stable shop slug'
);
fyrst_assert(
    str_contains($readme, 'SHOPWARE_DEPLOY_ENV'),
    'README names SHOPWARE_DEPLOY_ENV'
);
fyrst_assert(
    str_contains($readme, 'COMPOSE_PROJECT_NAME=${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}'),
    'README sets COMPOSE_PROJECT_NAME from shop id + deploy env'
);
fyrst_assert(
    str_contains($readme, '`COMPOSE_PROJECT_NAME` and `SHOPWARE_DATA_ROOT` are **optional**'),
    'README marks COMPOSE_PROJECT_NAME and SHOPWARE_DATA_ROOT as optional'
);
fyrst_assert(
    str_contains($readme, 'SHOPWARE_DATA_BASE'),
    'README names optional SHOPWARE_DATA_BASE'
);
fyrst_assert(
    str_contains($readme, 'Compose uses those SoT vars directly')
        || str_contains($readme, 'interpolates `SHOPWARE_SHOP_ID`, `SHOPWARE_DEPLOY_ENV`, and `SHOPWARE_DATA_BASE` **directly**'),
    'README states Compose uses SHOPWARE_SHOP_ID / SHOPWARE_DEPLOY_ENV / SHOPWARE_DATA_BASE directly'
);
fyrst_assert(
    !str_contains($readme, 'set explicitly in `.env`'),
    'README does not require COMPOSE_PROJECT_NAME / SHOPWARE_DATA_ROOT to be set explicitly'
);
fyrst_assert(
    !str_contains($readme, 'Compose does not reliably nest'),
    'README does not say Compose cannot interpolate SoT vars'
);
fyrst_assert(
    str_contains($readme, '/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}'),
    'README documents default SHOPWARE_DATA_ROOT as shop/env-scoped'
);
fyrst_assert(
    str_contains($readme, '/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}/{files,media,thumbnail,theme,sitemap}'),
    'README documents default SHOPWARE_DATA_ROOT bind-mount paths under shop/env'
);
fyrst_assert(
    str_contains($readme, '/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live'),
    'README auto-derives sync-runtime-local remote path from SHOPWARE_SHOP_ID + live'
);
fyrst_assert(
    str_contains($readme, 'paths from shop id + deploy env') || str_contains($readme, 'Paths use **shop id + deploy env**'),
    'README states deploy/sync-runtime.sh uses shop id + deploy env'
);
fyrst_assert(
    str_contains($readme, 'No hardcoded Compose project name `shopware`')
        || str_contains($readme, 'Hardcoding Compose project name `shopware`'),
    'README forbids hardcoded Compose project name shopware'
);
fyrst_assert(
    !preg_match('#/var/lib/shopware/data/\{files#', $readme),
    'README does not document a global /var/lib/shopware/data/{files,…} without shop/env'
);
fyrst_assert(
    !preg_match('#COMPOSE_PROJECT_NAME=shopware(?!-)#', $readme),
    'README does not set COMPOSE_PROJECT_NAME=shopware'
);
fyrst_assert(
    str_contains($readme, '**bind mounts**'),
    'README states VPS runtime data uses bind mounts'
);
fyrst_assert(
    str_contains($readme, 'Named Docker volumes remain only for `mysql_data` / `redis_data`'),
    'README keeps named volumes only for mysql_data / redis_data'
);
fyrst_assert(
    !str_contains($readme, 'volume archives'),
    'README does not describe runtime sync as volume archives'
);
fyrst_assert(
    !str_contains($readme, 'named volumes for runtime media/files'),
    'README does not assign named volumes to runtime media/files'
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
    'README states runtime DB/bind-mount data stay out of git and the image'
);
fyrst_assert(
    str_contains($readme, 'rsync path remap'),
    'README states local sync uses rsync path remap'
);
fyrst_assert(
    str_contains($readme, 'public/media/'),
    'README remaps live media into local public/media/'
);
fyrst_assert(
    str_contains($readme, 'shopware-cli project dev'),
    'README names shopware-cli project dev as the local consumer'
);
fyrst_assert(
    str_contains($readme, 'This script is **not** for local `shopware-cli project dev`'),
    'README states deploy/sync-runtime.sh is not for local project dev'
);
fyrst_assert(
    str_contains($readme, 'Do **not** run `deploy/sync-runtime.sh` on a laptop'),
    'README forbids VPS sync script on a laptop'
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
    str_contains($create, 'deploy/sync-runtime-local.sh'),
    'CREATE.md names deploy/sync-runtime-local.sh as Flex overlay'
);
fyrst_assert(
    str_contains($create, '**No S3.**'),
    'CREATE.md states VPS runtime sync does not use S3'
);
fyrst_assert(
    str_contains($create, 'SSH + dump + rsync of those host dirs'),
    'CREATE.md states runtime sync is SSH + dump + rsync of host dirs'
);
fyrst_assert(
    str_contains($create, 'SHOPWARE_DATA_ROOT'),
    'CREATE.md names SHOPWARE_DATA_ROOT for VPS bind mounts'
);
fyrst_assert(
    str_contains($create, 'SHOPWARE_SHOP_ID'),
    'CREATE.md names SHOPWARE_SHOP_ID as the stable shop slug'
);
fyrst_assert(
    str_contains($create, 'SHOPWARE_DEPLOY_ENV'),
    'CREATE.md names SHOPWARE_DEPLOY_ENV'
);
fyrst_assert(
    str_contains($create, 'COMPOSE_PROJECT_NAME=${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}'),
    'CREATE.md sets COMPOSE_PROJECT_NAME from shop id + deploy env'
);
fyrst_assert(
    str_contains($create, '`COMPOSE_PROJECT_NAME` and `SHOPWARE_DATA_ROOT` are **optional**'),
    'CREATE.md marks COMPOSE_PROJECT_NAME and SHOPWARE_DATA_ROOT as optional'
);
fyrst_assert(
    str_contains($create, 'SHOPWARE_DATA_BASE'),
    'CREATE.md names optional SHOPWARE_DATA_BASE'
);
fyrst_assert(
    str_contains($create, 'Compose uses those SoT vars directly'),
    'CREATE.md states Compose uses SoT vars directly'
);
fyrst_assert(
    !str_contains($create, 'set explicitly in `.env`'),
    'CREATE.md does not require COMPOSE_PROJECT_NAME / SHOPWARE_DATA_ROOT to be set explicitly'
);
fyrst_assert(
    str_contains($create, '/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}'),
    'CREATE.md documents default SHOPWARE_DATA_ROOT as shop/env-scoped'
);
fyrst_assert(
    str_contains($create, '/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}/{files,media,thumbnail,theme,sitemap}'),
    'CREATE.md documents default SHOPWARE_DATA_ROOT bind-mount paths under shop/env'
);
fyrst_assert(
    str_contains($create, '/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live'),
    'CREATE.md auto-derives sync-runtime-local remote path from SHOPWARE_SHOP_ID + live'
);
fyrst_assert(
    str_contains($create, 'paths from shop id + deploy env')
        || str_contains($create, 'shop id + deploy env'),
    'CREATE.md states deploy/sync-runtime.sh uses shop id + deploy env'
);
fyrst_assert(
    str_contains($create, 'do not hardcode `shopware`')
        || str_contains($create, 'Do not hardcode Compose project name `shopware`'),
    'CREATE.md forbids hardcoded Compose project name shopware'
);
fyrst_assert(
    !preg_match('#/var/lib/shopware/data/\{files#', $create),
    'CREATE.md does not document a global /var/lib/shopware/data/{files,…} without shop/env'
);
fyrst_assert(
    !preg_match('#COMPOSE_PROJECT_NAME=shopware(?!-)#', $create),
    'CREATE.md does not set COMPOSE_PROJECT_NAME=shopware'
);
fyrst_assert(
    str_contains($create, '**bind mounts**'),
    'CREATE.md states VPS runtime data uses bind mounts'
);
fyrst_assert(
    str_contains($create, 'Named volumes remain only for `mysql_data` / `redis_data`'),
    'CREATE.md keeps named volumes only for mysql_data / redis_data'
);
fyrst_assert(
    !str_contains($create, 'volume archives'),
    'CREATE.md does not describe runtime sync as volume archives'
);
fyrst_assert(
    !str_contains($create, 'Docker named volumes on the VPS'),
    'CREATE.md does not assign named volumes to VPS media/files'
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
    'CREATE.md states runtime DB/bind-mount data stay out of git and the image'
);
fyrst_assert(
    str_contains($create, 'rsync path remap'),
    'CREATE.md states local sync uses rsync path remap'
);
fyrst_assert(
    str_contains($create, 'shopware-cli project dev'),
    'CREATE.md names shopware-cli project dev as the local consumer'
);
fyrst_assert(
    str_contains($create, 'Do **not** use `deploy/sync-runtime.sh` locally'),
    'CREATE.md forbids VPS sync script on a laptop'
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

foreach (['README.md' => $readme, 'CREATE.md' => $create] as $name => $text) {
    fyrst_assert(
        !preg_match('#mkdir -p /var/lib/shopware/data/\{#', $text),
        "{$name} does not mkdir a global /var/lib/shopware/data/{{files,…}}"
    );
    fyrst_assert(
        !preg_match('#chown -R 82:82 /var/lib/shopware/data\b#', $text),
        "{$name} does not chown a global /var/lib/shopware/data"
    );
    fyrst_assert(
        !preg_match('#SHOPWARE_DATA_ROOT[^\n]{0,160}default `/var/lib/shopware/data`(?!/)#', $text),
        "{$name} does not default SHOPWARE_DATA_ROOT to /var/lib/shopware/data without shop/env"
    );
    fyrst_assert(
        !preg_match('/^name:\s*shopware\s*$/m', $text),
        "{$name} does not hardcode Compose name: shopware"
    );
    fyrst_assert(
        !str_contains($text, 'Compose does not reliably nest'),
        "{$name} does not say Compose cannot interpolate SoT vars"
    );
    fyrst_assert(
        !str_contains($text, 'set explicitly in `.env`'),
        "{$name} does not require expanded COMPOSE_PROJECT_NAME / SHOPWARE_DATA_ROOT in .env"
    );

    preg_match_all('#/var/lib/shopware/data(?:/[^\s`\'")]+)?#', $text, $dataRoots);
    foreach ($dataRoots[0] as $path) {
        $scoped = str_contains($path, '${SHOPWARE_SHOP_ID}')
            || (bool) preg_match('#^/var/lib/shopware/data/[a-z0-9-]+/(live|staging|playground|dev)#', $path);
        if ($scoped) {
            continue;
        }
        fyrst_assert(
            $path === '/var/lib/shopware/data'
                && (
                    str_contains($text, 'single global `/var/lib/shopware/data`')
                    || str_contains($text, 'not a global `/var/lib/shopware/data`')
                    || str_contains($text, 'global `/var/lib/shopware/data` without')
                    || str_contains($text, 'SHOPWARE_DATA_BASE')
                ),
            "{$name} data path is shop/env-scoped (or DATA_BASE / anti-pattern): {$path}"
        );
    }
}

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "\nAll package tests passed.\n");
exit(0);
