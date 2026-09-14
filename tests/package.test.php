<?php

declare(strict_types=1);

/**
 * Smoke tests for the thin Packagist package (overlay/ is Flex copy-from-package).
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
    ($composer['support']['docs'] ?? '') === 'https://github.com/fyrst-dev/shopware-cd',
    'support.docs points at fyrst-dev/shopware-cd'
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
    str_contains((string) ($composer['description'] ?? ''), 'overlay/'),
    'description says overlay/ ships in this package'
);
fyrst_assert(
    str_contains((string) ($composer['description'] ?? ''), 'copy-from-package'),
    'description names Flex copy-from-package'
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
fyrst_assert(is_dir($root . '/src'), 'src/ contains the Symfony bundle and rewrite command');
fyrst_assert(
    is_file($root . '/src/FyrstShopwareCdBundle.php'),
    'FyrstShopwareCdBundle.php is present'
);
$bundleSrc = (string) file_get_contents($root . '/src/FyrstShopwareCdBundle.php');
fyrst_assert(
    str_contains($bundleSrc, 'Symfony\\Component\\HttpKernel\\Bundle\\Bundle'),
    'bundle extends Bundle so Flex can auto-discover it'
);
fyrst_assert(
    is_file($root . '/src/Command/RewriteSalesChannelUrlsCommand.php'),
    'RewriteSalesChannelUrlsCommand.php is present'
);
$commandSrc = (string) file_get_contents($root . '/src/Command/RewriteSalesChannelUrlsCommand.php');
fyrst_assert(
    str_contains($commandSrc, 'fyrst:sales-channel:rewrite-urls'),
    'command name is fyrst:sales-channel:rewrite-urls'
);
fyrst_assert(
    ($composer['autoload']['psr-4']['Fyrst\\ShopwareCd\\'] ?? '') === 'src/',
    'PSR-4 autoload Fyrst\\ShopwareCd\\ → src/'
);
fyrst_assert(
    ($composer['extra']['symfony']['bundle']['Fyrst\\ShopwareCd\\FyrstShopwareCdBundle'] ?? null) === ['all'],
    'extra.symfony.bundle registers FyrstShopwareCdBundle for all envs'
);
fyrst_assert(isset($composer['require']['symfony/console']), 'require.symfony/console is present');
fyrst_assert(isset($composer['require']['doctrine/dbal']), 'require.doctrine/dbal is present');
fyrst_assert(!isset($composer['extra']['shopware-plugin-class']), 'not a Shopware plugin (Symfony bundle)');
fyrst_assert(!is_dir($root . '/scripts'), 'scripts/ create wrapper removed');
fyrst_assert(is_dir($root . '/overlay'), 'overlay/ is the Flex copy-from-package source');
fyrst_assert(!is_dir($root . '/flex-recipe'), 'flex-recipe/ not in this package');
$gitattributes = is_file($root . '/.gitattributes')
    ? (string) file_get_contents($root . '/.gitattributes')
    : '';
fyrst_assert(
    !preg_match('/^overlay\\/?\\s+export-ignore/m', $gitattributes),
    'overlay/ is not export-ignored from the Composer dist'
);
$overlayFiles = [
    '.dockerignore',
    '.env.example',
    '.github/workflows/cd.yaml',
    '.gitlab-ci.yaml',
    'deploy/.gitignore',
    'deploy/README.md',
    'deploy/backup-runtime.md',
    'deploy/compose.yaml',
    'deploy/compose.prod.yaml',
    'deploy/compose.vps.yaml',
    'deploy/managed/README.md',
    'deploy/sync-runtime.md',
    'deploy/edge/Caddyfile',
    'deploy/edge/README.md',
];
foreach ($overlayFiles as $rel) {
    fyrst_assert(is_file($root . '/overlay/' . $rel), 'overlay/' . $rel . ' is present');
}
$forbiddenOverlay = [
    'deploy/init-env.sh',
    'deploy/vps-release.sh',
    'deploy/vps-rollback.sh',
    'deploy/sync-runtime.sh',
    'deploy/sync-runtime-local.sh',
    'deploy/backup-runtime.sh',
    'deploy/sync.env.example',
    'deploy/backup.env.example',
    'compose.yaml',
    '.gitignore',
    '.shopware-project.yml',
    '.shopware-project.yaml',
    'Dockerfile',
    'docker/Dockerfile',
];
foreach ($forbiddenOverlay as $rel) {
    fyrst_assert(!file_exists($root . '/overlay/' . $rel), 'overlay/' . $rel . ' is not shipped');
}
fyrst_assert(!is_dir($root . '/overlay/deploy/lib'), 'overlay/deploy/lib is not shipped');
$overlaySh = [];
$overlayIter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/overlay', FilesystemIterator::SKIP_DOTS)
);
foreach ($overlayIter as $fileInfo) {
    if ($fileInfo->isFile() && str_ends_with($fileInfo->getFilename(), '.sh')) {
        $overlaySh[] = substr($fileInfo->getPathname(), strlen($root) + 1);
    }
}
fyrst_assert($overlaySh === [], 'overlay/ has no bash wrappers: ' . implode(', ', $overlaySh));
fyrst_assert(
    is_file($root . '/.github/workflows/ci.yml'),
    'package CI stays at .github/workflows/ci.yml'
);
$packageCi = (string) file_get_contents($root . '/.github/workflows/ci.yml');
fyrst_assert(
    str_contains($packageCi, 'Package') && str_contains($packageCi, 'php tests/package.test.php'),
    'package CI still runs library tests only'
);
fyrst_assert(is_file($root . '/LICENSE'), 'LICENSE file present');
fyrst_assert(is_file($root . '/README.md'), 'README.md present');
fyrst_assert(is_file($root . '/CREATE.md'), 'CREATE.md present');
fyrst_assert(!is_file($root . '/.github/workflows/cd.yml'), 'shop CD is not at package .github');
fyrst_assert(!is_file($root . '/.github/workflows/cd.yaml'), 'shop CD yaml is not at package .github');
fyrst_assert(!is_file($root . '/.gitlab-ci.yaml'), 'shop GitLab CI is not at package root');
$overlayExample = (string) file_get_contents($root . '/overlay/.env.example');
foreach ([
    'SHOPWARE_SSH_HOST=',
    'SHOPWARE_SSH_USER=',
    'SHOPWARE_SSH_KEY=',
    'SHOPWARE_REMOTE_DATA_ROOT=',
    'BACKUP_TARGET=local',
    'BACKUP_KEEP_DAYS=14',
    'BACKUP_DB_DUMP=',
    'SHOPWARE_ALLOW_LIVE_RESTORE=1',
] as $needle) {
    fyrst_assert(
        str_contains($overlayExample, $needle),
        'overlay/.env.example comments ' . $needle
    );
}
$deployGitignore = (string) file_get_contents($root . '/overlay/deploy/.gitignore');
fyrst_assert(
    (bool) preg_match('/^\\*\\.env$/m', $deployGitignore),
    'overlay/deploy/.gitignore keeps leftover *.env out of git'
);
$deployReadme = (string) file_get_contents($root . '/overlay/deploy/README.md');
fyrst_assert(
    str_contains($deployReadme, 'fyrst-cli shopware sync pull --from live --data all')
        && str_contains($deployReadme, 'fyrst-cli shopware backup create')
        && !str_contains($deployReadme, 'bash ./deploy/vps-release.sh')
        && !str_contains($deployReadme, 'bash deploy/sync-runtime.sh'),
    'overlay/deploy/README.md uses fyrst-cli lifecycle verbs, not bash wrappers'
);
foreach (['overlay/.github/workflows/cd.yaml', 'overlay/.gitlab-ci.yaml'] as $rel) {
    $ci = (string) file_get_contents($root . '/' . $rel);
    fyrst_assert(
        !str_contains($ci, 'bash ./deploy/vps-release.sh'),
        $rel . ' does not invoke bash ./deploy/vps-release.sh'
    );
    fyrst_assert(
        str_contains($ci, 'fyrst-cli shopware deploy release')
            && str_contains($ci, 'IMAGE=')
            && str_contains($ci, 'IMAGE_TAG=')
            && str_contains($ci, 'COMPOSE_DIR='),
        $rel . ' SSH step uses fyrst-cli + IMAGE / IMAGE_TAG / COMPOSE_DIR'
    );
    fyrst_assert(
        str_contains($ci, 'fyrst-cli 0.1.0'),
        $rel . ' comments require fyrst-cli 0.1.0+'
    );
}
fyrst_assert(!is_file($root . '/compose.yaml'), 'shop compose.yaml is not in this package');
fyrst_assert(!is_file($root . '/Dockerfile'), 'shop-root Dockerfile is not in this package');
fyrst_assert(!is_file($root . '/docker/Dockerfile'), 'shop docker/Dockerfile is not in this package');

$readme = (string) file_get_contents($root . '/README.md');
fyrst_assert(
    !str_contains($readme, 'does **not** contain overlay files'),
    'README no longer says this package does not contain overlay files'
);
fyrst_assert(
    str_contains($readme, 'copy-from-package'),
    'README names Flex copy-from-package'
);
fyrst_assert(
    str_contains($readme, '`overlay/`'),
    'README names overlay/ as the shop file source'
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
    !str_contains($create, 'does **not** contain overlay files'),
    'CREATE.md no longer says this package does not contain overlay files'
);
fyrst_assert(
    str_contains($create, 'copy-from-package'),
    'CREATE.md names Flex copy-from-package'
);
fyrst_assert(
    str_contains($create, '`overlay/`'),
    'CREATE.md names overlay/ as the shop file source'
);
fyrst_assert(
    str_contains($readme, 'Do not keep a second `root/` copy'),
    'README forbids a second recipe root/ overlay'
);
fyrst_assert(
    str_contains($create, 'Do not keep a second copy in fyrst-dev/recipes `root/`'),
    'CREATE.md forbids a second recipe root/ overlay'
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
    str_contains($readme, '.shopware-project.yml'),
    'README names create’s .shopware-project.yml'
);
fyrst_assert(
    str_contains($readme, 'do not rename'),
    'README says do not rename create’s .yml'
);
fyrst_assert(
    str_contains($readme, 'COMPOSE_PROJECT_NAME=sw-shop'),
    'README warns about create’s COMPOSE_PROJECT_NAME=sw-shop-… line'
);
fyrst_assert(
    str_contains($readme, 'fyrst-cli shopware env init'),
    'README operator path is fyrst-cli shopware env init'
);
fyrst_assert(
    str_contains($readme, '###> fyrst/shopware-cd ###'),
    'README documents the Flex env marker block'
);
fyrst_assert(
    str_contains($readme, 'fyrst-cli shopware env init --vps'),
    'README documents fyrst-cli shopware env init --vps'
);
fyrst_assert(
    str_contains($readme, 'does **not** overwrite create’s whole `.env`'),
    'README states Flex does not overwrite create’s whole .env'
);
fyrst_assert(
    str_contains($readme, 'PULL_POLICY=never'),
    'README documents PULL_POLICY=never for same-host tag-and-load'
);
fyrst_assert(
    str_contains($readme, 'SKIP_PULL=1'),
    'README documents SKIP_PULL=1'
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
    str_contains($readme, 'fyrst-cli shopware sync'),
    'README operator path is fyrst-cli shopware sync'
);
fyrst_assert(
    str_contains($readme, 'fyrst-cli shopware sync local'),
    'README operator path is fyrst-cli shopware sync local'
);
fyrst_assert(
    str_contains($readme, 'fyrst:sales-channel:rewrite-urls'),
    'README names fyrst:sales-channel:rewrite-urls'
);
fyrst_assert(
    str_contains($readme, 'FyrstShopwareCdBundle'),
    'README names FyrstShopwareCdBundle'
);
fyrst_assert(
    str_contains($readme, 'composer update fyrst/shopware-cd'),
    'README tells shops to composer update fyrst/shopware-cd'
);
fyrst_assert(
    str_contains($readme, '--entrypoint php web bin/console fyrst:sales-channel:rewrite-urls'),
    'README shows docker compose run --entrypoint php web bin/console'
);
fyrst_assert(
    str_contains($readme, 'SHOPWARE_ALLOW_LIVE_RESTORE=1'),
    'README states SHOPWARE_ALLOW_LIVE_RESTORE does not bypass rewrite refuse'
);
fyrst_assert(
    str_contains($readme, 'Rewrite uses `APP_URL`'),
    'README rewrite target is APP_URL'
);
fyrst_assert(
    str_contains($readme, '`.env.local`'),
    'README documents shop-root .env.local'
);
fyrst_assert(
    str_contains($readme, '`.env.prod`'),
    'README documents shop-root .env.prod'
);
fyrst_assert(
    str_contains($readme, 'SHOPWARE_SSH_HOST'),
    'README documents SHOPWARE_SSH_HOST'
);
fyrst_assert(
    str_contains($readme, 'SHOPWARE_REMOTE_DATA_ROOT'),
    'README documents SHOPWARE_REMOTE_DATA_ROOT'
);
fyrst_assert(
    str_contains($readme, '**No S3.**'),
    'README states VPS runtime sync does not use S3'
);
fyrst_assert(
    str_contains($readme, 'shopware-cli project dump'),
    'README states dump stays shopware-cli project dump'
);
fyrst_assert(
    str_contains($readme, 'SSH + `shopware-cli project dump`')
        || str_contains($readme, 'SSH + dump'),
    'README states runtime sync is SSH + dump'
);
fyrst_assert(
    str_contains($readme, 'fyrst-cli'),
    'README names fyrst-cli as the operator CLI'
);
fyrst_assert(
    str_contains($readme, '0.1.0'),
    'README pins fyrst-cli 0.1.0'
);
fyrst_assert(
    str_contains($readme, 'fyrst-cli shopware deploy release'),
    'README operator path is fyrst-cli shopware deploy release'
);
fyrst_assert(
    str_contains($readme, '0.1.0+') && str_contains($readme, 'each VPS'),
    'README requires fyrst-cli 0.1.0+ on each VPS'
);
fyrst_assert(
    !str_contains($readme, 'bash deploy/')
        && !str_contains($readme, 'bash ./deploy/'),
    'README does not list bash deploy/*.sh as the operator path'
);
fyrst_assert(
    !str_contains($readme, 'thin stubs around one dispatcher')
        && !str_contains($readme, 'Operator commands stay `bash deploy/…`'),
    'README does not keep Phase 1 dispatcher/wrapper operator wording'
);
fyrst_assert(
    !str_contains($readme, 'Overlay bash currently rewrites via SQL'),
    'README does not describe overlay bash SQL rewrite as the operator path'
);
fyrst_assert(
    !str_contains($readme, 'companion recipes PR'),
    'README does not wait on a companion recipes PR for rewrite'
);
fyrst_assert(
    str_contains($readme, 'rsync of those host dirs'),
    'README states fyrst-cli shopware sync rsyncs SHOPWARE_DATA_ROOT host dirs'
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
    'README auto-derives sync local remote path from SHOPWARE_SHOP_ID + live'
);
fyrst_assert(
    str_contains($readme, 'paths from shop id + deploy env') || str_contains($readme, 'Paths use **shop id + deploy env**'),
    'README states fyrst-cli shopware sync uses shop id + deploy env'
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
    str_contains($readme, 'is **not** for local `shopware-cli project dev`'),
    'README states VPS sync is not for local project dev'
);
fyrst_assert(
    str_contains($readme, 'Do **not** run `fyrst-cli shopware sync {capture|apply|pull}` on a laptop'),
    'README forbids VPS fyrst-cli shopware sync on a laptop'
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
    str_contains($create, '.shopware-project.yml'),
    'CREATE.md names create’s .shopware-project.yml'
);
fyrst_assert(
    str_contains($create, 'do not rename'),
    'CREATE.md says do not rename create’s .yml'
);
fyrst_assert(
    str_contains($create, 'COMPOSE_PROJECT_NAME=sw-shop'),
    'CREATE.md warns about create’s COMPOSE_PROJECT_NAME=sw-shop-… line'
);
fyrst_assert(
    str_contains($create, 'fyrst-cli shopware env init'),
    'CREATE.md operator path is fyrst-cli shopware env init'
);
fyrst_assert(
    str_contains($create, '###> fyrst/shopware-cd ###'),
    'CREATE.md documents the Flex env marker block'
);
fyrst_assert(
    str_contains($create, 'fyrst-cli shopware env init --vps'),
    'CREATE.md documents fyrst-cli shopware env init --vps'
);
fyrst_assert(
    str_contains($create, 'does **not** overwrite create’s whole `.env`'),
    'CREATE.md states Flex does not overwrite create’s whole .env'
);
fyrst_assert(
    str_contains($create, 'PULL_POLICY=never'),
    'CREATE.md documents PULL_POLICY=never'
);
fyrst_assert(
    str_contains($create, 'deploy/compose.yaml'),
    'CREATE.md names deploy/compose.yaml'
);
fyrst_assert(
    str_contains($create, 'fyrst-cli shopware sync'),
    'CREATE.md operator path is fyrst-cli shopware sync'
);
fyrst_assert(
    str_contains($create, 'fyrst-cli shopware sync local'),
    'CREATE.md operator path is fyrst-cli shopware sync local'
);
fyrst_assert(
    str_contains($create, 'fyrst:sales-channel:rewrite-urls'),
    'CREATE.md names fyrst:sales-channel:rewrite-urls'
);
fyrst_assert(
    str_contains($create, 'Rewrite uses `APP_URL`'),
    'CREATE.md rewrite target is APP_URL'
);
fyrst_assert(
    str_contains($create, '`.env.local`'),
    'CREATE.md documents shop-root .env.local'
);
fyrst_assert(
    str_contains($create, '`.env.prod`'),
    'CREATE.md documents shop-root .env.prod'
);
fyrst_assert(
    str_contains($create, 'SHOPWARE_SSH_HOST'),
    'CREATE.md documents SHOPWARE_SSH_HOST'
);
fyrst_assert(
    str_contains($create, 'SHOPWARE_REMOTE_DATA_ROOT'),
    'CREATE.md documents SHOPWARE_REMOTE_DATA_ROOT'
);
fyrst_assert(
    str_contains($create, 'SHOPWARE_ALLOW_LIVE_RESTORE=1'),
    'CREATE.md states SHOPWARE_ALLOW_LIVE_RESTORE does not bypass rewrite refuse'
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
    str_contains($create, 'fyrst-cli'),
    'CREATE.md names fyrst-cli as the operator CLI'
);
fyrst_assert(
    str_contains($create, '0.1.0'),
    'CREATE.md pins fyrst-cli 0.1.0'
);
fyrst_assert(
    str_contains($create, 'shopware-cli project dump'),
    'CREATE.md states dump stays shopware-cli project dump'
);
fyrst_assert(
    str_contains($create, 'fyrst-cli shopware deploy release'),
    'CREATE.md operator path is fyrst-cli shopware deploy release'
);
fyrst_assert(
    str_contains($create, '0.1.0+') && str_contains($create, 'each VPS'),
    'CREATE.md requires fyrst-cli 0.1.0+ on each VPS'
);
fyrst_assert(
    !str_contains($create, 'bash deploy/')
        && !str_contains($create, 'bash ./deploy/'),
    'CREATE.md does not list bash deploy/*.sh as the operator path'
);
fyrst_assert(
    !str_contains($create, 'thin stubs around one dispatcher')
        && !str_contains($create, 'Operator commands stay `bash deploy/…`'),
    'CREATE.md does not keep Phase 1 dispatcher/wrapper operator wording'
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
    'CREATE.md auto-derives sync local remote path from SHOPWARE_SHOP_ID + live'
);
fyrst_assert(
    str_contains($create, 'paths from shop id + deploy env')
        || str_contains($create, 'shop id + deploy env'),
    'CREATE.md states fyrst-cli shopware sync uses shop id + deploy env'
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
    str_contains($create, 'Do **not** use VPS `fyrst-cli shopware sync pull` locally'),
    'CREATE.md forbids VPS fyrst-cli shopware sync on a laptop'
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
    str_contains($readme, 'planned / not implemented')
        && str_contains($create, 'not implemented'),
    'README/CREATE mark managed host as planned / not implemented'
);
fyrst_assert(
    !str_contains($readme, 'Gate with `DEPLOY_TARGET=managed`'),
    'README does not treat DEPLOY_TARGET=managed as a working switch'
);
fyrst_assert(
    str_contains($readme, 'COMPOSE_PROFILES=redis,worker,scheduler')
        && str_contains($create, 'COMPOSE_PROFILES=redis,worker,scheduler'),
    'README/CREATE recommend live COMPOSE_PROFILES'
);
fyrst_assert(
    str_contains($readme, 'recipes#11') && str_contains($readme, 'recipes#10'),
    'README links P0–P2 recipes epics'
);

fyrst_assert(
    str_contains((string) ($composer['description'] ?? ''), 'fyrst-cli'),
    'description names fyrst-cli as the operator CLI'
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
    fyrst_assert(
        !str_contains($text, 'deploy/sync.env')
            && !str_contains($text, 'sync.env.example'),
        "{$name} does not document deploy/sync.env"
    );
    fyrst_assert(
        !str_contains($text, 'SYNC_APP_URL')
            && !str_contains($text, 'SYNC_SSH_')
            && !str_contains($text, 'SYNC_ENV')
            && !str_contains($text, 'SYNC_REWRITE_')
            && !str_contains($text, 'SYNC_REMOTE_DATA_ROOT')
            && !str_contains($text, 'SYNC_ALLOW_LIVE_RESTORE')
            && !str_contains($text, 'SYNC_DATA_ROOT'),
        "{$name} has no SYNC_* tables or leftover SYNC_* names"
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
