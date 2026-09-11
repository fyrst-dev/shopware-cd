<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\Cli;

use Fyrst\ShopwareCd\OverlayApplier;
use Fyrst\ShopwareCd\OverlayLocator;

final class ProjectCreator
{
    public const DEFAULT_REPO = 'https://github.com/fyrst-dev/shopware-cd-template.git';

    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        $dryRun = false;
        $git = true;
        $phpVersion = '8.3';
        $repo = getenv('FYRST_SHOPWARE_CD_REPO') ?: self::DEFAULT_REPO;
        $passthru = [];
        $positional = [];

        while ($args !== []) {
            $arg = array_shift($args);
            if ($arg === '--') {
                $passthru = $args;
                break;
            }
            if ($arg === '--dry-run') {
                $dryRun = true;
                continue;
            }
            if ($arg === '--no-git') {
                $git = false;
                continue;
            }
            if ($arg === '--help' || $arg === '-h') {
                fwrite(STDOUT, "Usage: fyrst-shopware-cd create <shop-name> [version] [--dry-run] [--no-git] [--php-version=8.3] [--repo=URL]\n");

                return 0;
            }
            if (str_starts_with($arg, '--php-version=')) {
                $phpVersion = substr($arg, 14);
                continue;
            }
            if ($arg === '--php-version') {
                $phpVersion = (string) array_shift($args);
                continue;
            }
            if (str_starts_with($arg, '--repo=')) {
                $repo = substr($arg, 7);
                continue;
            }
            if ($arg === '--repo') {
                $repo = (string) array_shift($args);
                continue;
            }
            if (str_starts_with($arg, '-')) {
                fwrite(STDERR, "unknown create option: {$arg}\n");

                return 1;
            }
            $positional[] = $arg;
        }

        $name = $positional[0] ?? null;
        $version = $positional[1] ?? null;
        if ($name === null || $name === '') {
            fwrite(STDERR, "error: shop name required\n");
            fwrite(STDERR, "Usage: fyrst-shopware-cd create <shop-name> [version]\n");

            return 1;
        }

        $target = getcwd() . '/' . $name;
        if (file_exists($target)) {
            fwrite(STDERR, "error: {$target} already exists\n");

            return 1;
        }

        $cli = $this->shopwareCli();
        $create = array_merge($cli, ['--no-interaction', 'project', 'create', $name]);
        if (is_string($version) && $version !== '') {
            $create[] = $version;
        }
        $create[] = '--docker';
        $create[] = '--php-version';
        $create[] = $phpVersion;
        if ($git) {
            $create[] = '--git';
        }
        $create = array_merge($create, $passthru);

        if ($dryRun) {
            $this->printCommand($create, getcwd() ?: '.');
            $this->printComposerPlan($name, $repo);
            fwrite(STDOUT, "==> fyrst-shopware-cd apply --dir={$name}\n");

            return 0;
        }

        $code = $this->passthru($create, getcwd() ?: '.');
        if ($code !== 0) {
            return $code;
        }

        $shop = realpath($name) ?: $target;
        if (!is_dir($shop)) {
            fwrite(STDERR, "error: shopware-cli did not create {$name}\n");

            return 1;
        }

        $code = $this->configureAndRequire($shop, $repo);
        if ($code !== 0) {
            return $code;
        }

        $overlay = OverlayLocator::locate();
        $result = (new OverlayApplier($overlay, $shop, false, false))->apply();
        fwrite(STDOUT, $result->render());
        fwrite(STDOUT, "Done. cd {$name} and set CI secrets. Local: shopware-cli project dev\n");

        return 0;
    }

    /**
     * @return list<string>
     */
    private function shopwareCli(): array
    {
        $env = getenv('SHOPWARE_CLI');
        if (is_string($env) && $env !== '') {
            return $this->splitCommand($env);
        }
        $which = $this->which('shopware-cli');
        if ($which !== null) {
            return [$which];
        }
        $npx = $this->which('npx');
        if ($npx === null) {
            throw new \RuntimeException('shopware-cli not found. Install it or npx (@shopware-ag/shopware-cli).');
        }

        return [$npx, '--yes', '@shopware-ag/shopware-cli'];
    }

    /**
     * @return list<string>
     */
    private function composerBinary(string $shop): array
    {
        if ($this->dockerWebRunning($shop)) {
            return ['docker', 'compose', 'exec', '-T', 'web', 'composer'];
        }
        $env = getenv('COMPOSER_BINARY');
        if (is_string($env) && $env !== '') {
            return [PHP_BINARY, $env];
        }
        $which = $this->which('composer');
        if ($which !== null) {
            return [$which];
        }
        throw new \RuntimeException('composer not found on PATH (and docker compose web is not running).');
    }

    private function dockerWebRunning(string $shop): bool
    {
        if ($this->which('docker') === null) {
            return false;
        }
        if (!is_file($shop . '/compose.yaml') && !is_file($shop . '/compose.yml')) {
            return false;
        }
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open(['docker', 'compose', 'ps', '--status', 'running', '--services'], $spec, $pipes, $shop);
        if (!is_resource($proc)) {
            return false;
        }
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        foreach (preg_split("/\R/", $out) ?: [] as $line) {
            if (trim($line) === 'web') {
                return true;
            }
        }

        return false;
    }

    private function configureAndRequire(string $shop, string $repo): int
    {
        $composer = $this->composerBinary($shop);
        $repoType = is_dir($repo) ? 'path' : 'vcs';
        $constraint = $repoType === 'path' ? 'fyrst/shopware-cd:@dev' : 'fyrst/shopware-cd:dev-main';

        $steps = [
            array_merge($composer, ['config', 'allow-plugins.fyrst/shopware-cd', 'true']),
            array_merge($composer, ['config', 'repositories.fyrst', $repoType, $repo]),
            array_merge($composer, ['require', '--no-interaction', 'shopware/docker', 'shopware/deployment-helper']),
            array_merge($composer, ['require', '--no-interaction', $constraint]),
        ];

        foreach ($steps as $cmd) {
            $code = $this->passthru($cmd, $shop);
            if ($code !== 0) {
                fwrite(STDERR, "hint: run Composer inside Docker if host PHP is under-provisioned:\n");
                fwrite(STDERR, "  docker compose exec web composer require shopware/docker shopware/deployment-helper\n");
                fwrite(STDERR, "  docker compose exec web composer require {$constraint}\n");

                return $code;
            }
        }

        return 0;
    }

    private function printComposerPlan(string $name, string $repo): void
    {
        $repoType = is_dir($repo) ? 'path' : 'vcs';
        $constraint = $repoType === 'path' ? 'fyrst/shopware-cd:@dev' : 'fyrst/shopware-cd:dev-main';
        fwrite(STDOUT, "==> cd {$name}\n");
        fwrite(STDOUT, "==> composer config allow-plugins.fyrst/shopware-cd true\n");
        fwrite(STDOUT, "==> composer config repositories.fyrst {$repoType} {$repo}\n");
        fwrite(STDOUT, "==> composer require --no-interaction shopware/docker shopware/deployment-helper\n");
        fwrite(STDOUT, "==> composer require --no-interaction {$constraint}\n");
    }

    /**
     * @param list<string> $command
     */
    private function passthru(array $command, string $cwd): int
    {
        $this->printCommand($command, $cwd);
        $proc = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $cwd);
        if (!is_resource($proc)) {
            throw new \RuntimeException('Failed to start: ' . implode(' ', $command));
        }

        return proc_close($proc);
    }

    /**
     * @param list<string> $command
     */
    private function printCommand(array $command, string $cwd): void
    {
        $parts = array_map(static fn (string $p): string => preg_match('/[\s]/', $p) ? escapeshellarg($p) : $p, $command);
        fwrite(STDOUT, '==> [' . $cwd . '] ' . implode(' ', $parts) . "\n");
    }

    /**
     * @return list<string>
     */
    private function splitCommand(string $command): array
    {
        $parts = preg_split('/\s+/', trim($command)) ?: [];

        return array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
    }

    private function which(string $binary): ?string
    {
        $out = [];
        $code = 0;
        exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null', $out, $code);
        $path = trim($out[0] ?? '');

        return $code === 0 && $path !== '' ? $path : null;
    }
}
