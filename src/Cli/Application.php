<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\Cli;

use Fyrst\ShopwareCd\OverlayApplier;
use Fyrst\ShopwareCd\OverlayLocator;
use Fyrst\ShopwareCd\ShopDetector;

final class Application
{
    public function run(array $argv): int
    {
        $args = array_values(array_slice($argv, 1));
        $command = 'help';
        if ($args !== [] && !str_starts_with($args[0], '-')) {
            $command = array_shift($args);
        }

        try {
            return match ($command) {
                'apply' => $this->apply($args),
                'create' => (new ProjectCreator())->run($args),
                'help', '--help', '-h' => $this->help(),
                'version', '--version', '-V' => $this->version(),
                default => $this->unknown($command),
            };
        } catch (\Throwable $e) {
            fwrite(STDERR, 'error: ' . $e->getMessage() . "\n");

            return 1;
        }
    }

    /**
     * @param list<string> $args
     */
    private function apply(array $args): int
    {
        $force = false;
        $dryRun = false;
        $quiet = false;
        $dir = null;
        $overlay = null;

        while ($args !== []) {
            $arg = array_shift($args);
            if ($arg === '--') {
                break;
            }
            if ($arg === '--force' || $arg === '-f') {
                $force = true;
                continue;
            }
            if ($arg === '--dry-run') {
                $dryRun = true;
                continue;
            }
            if ($arg === '--quiet' || $arg === '-q') {
                $quiet = true;
                continue;
            }
            if ($arg === '--help' || $arg === '-h') {
                return $this->help();
            }
            if (str_starts_with($arg, '--dir=')) {
                $dir = substr($arg, 6);
                continue;
            }
            if ($arg === '--dir' || $arg === '--project-dir') {
                $dir = array_shift($args);
                continue;
            }
            if (str_starts_with($arg, '--overlay=')) {
                $overlay = substr($arg, 10);
                continue;
            }
            if ($arg === '--overlay') {
                $overlay = array_shift($args);
                continue;
            }

            fwrite(STDERR, "unknown apply option: {$arg}\n");

            return 1;
        }

        $overlayDir = OverlayLocator::locate($overlay);
        $projectDir = $dir !== null && $dir !== ''
            ? (realpath($dir) ?: $dir)
            : ShopDetector::findProjectRoot();

        if ($projectDir === null) {
            fwrite(STDERR, "error: not a Shopware project (need composer.json with shopware/* or bin/console).\n");
            fwrite(STDERR, "Pass --dir=/path/to/shop. Do not apply into the fyrst/shopware-cd package root.\n");

            return 2;
        }

        if (ShopDetector::isTemplateRoot($projectDir)) {
            fwrite(STDERR, "error: {$projectDir} is the fyrst/shopware-cd package, not a shop. Overlay apply skipped.\n");

            return 2;
        }

        if (!ShopDetector::isShopwareProject($projectDir)) {
            fwrite(STDERR, "error: {$projectDir} is not a Shopware project.\n");

            return 2;
        }

        $result = (new OverlayApplier($overlayDir, $projectDir, $force, $dryRun))->apply();
        fwrite(STDOUT, $result->render($quiet));

        if (is_file($projectDir . '/docker/Dockerfile')) {
            fwrite(STDOUT, "  note    : docker/Dockerfile exists (shopware/docker Flex). Prefer DOCKERFILE=docker/Dockerfile in CI.\n");
        }

        return 0;
    }

    private function help(): int
    {
        fwrite(STDOUT, <<<'HELP'
fyrst-shopware-cd — apply the fyrst Shopware CD overlay (no git submodules)

Usage:
  fyrst-shopware-cd create <shop-name> [version]
  fyrst-shopware-cd apply [--force] [--dry-run] [--dir=PATH] [--overlay=PATH]

create
  Runs shopware-cli (or npx @shopware-ag/shopware-cli) project create --docker,
  then composer require shopware/docker shopware/deployment-helper and
  fyrst/shopware-cd (private VCS repo). Overlay apply is automatic.

apply
  Copies overlay/ files into a Shopware project root.
  Existing files are skipped unless --force (template-managed paths only).
  Never writes .env, auth.json, or shop app code (custom/, src/, …).

Environment:
  FYRST_SHOPWARE_CD_SKIP_APPLY=1   skip Composer auto-apply
  FYRST_SHOPWARE_CD_FORCE=1         treat Composer auto-apply as --force
  FYRST_SHOPWARE_CD_REPO            VCS/path URL for create (private GitHub)
  FYRST_SHOPWARE_CD_OVERLAY          overlay directory override

HELP);

        return 0;
    }

    private function version(): int
    {
        fwrite(STDOUT, "fyrst/shopware-cd overlay applier (dev-main)\n");

        return 0;
    }

    private function unknown(string $command): int
    {
        fwrite(STDERR, "unknown command: {$command}\n");
        $this->help();

        return 1;
    }
}
