<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class OverlayApplier
{
    private const PROTECTED_FILES = [
        '.env',
        '.env.local',
        '.env.prod',
        'auth.json',
    ];

    private const PROTECTED_PREFIXES = [
        'custom/',
        'src/',
        'public/',
        'config/jwt/',
        'vendor/',
        'node_modules/',
    ];

    public function __construct(
        private readonly string $overlayDir,
        private readonly string $projectDir,
        private readonly bool $force = false,
        private readonly bool $dryRun = false,
    ) {
    }

    public function apply(): ApplyResult
    {
        $overlay = realpath($this->overlayDir);
        $project = realpath($this->projectDir) ?: $this->projectDir;
        if ($overlay === false || !is_dir($overlay)) {
            throw new \RuntimeException("Overlay directory not found: {$this->overlayDir}");
        }
        if (!is_dir($project)) {
            throw new \RuntimeException("Project directory not found: {$this->projectDir}");
        }

        $result = new ApplyResult();
        $result->overlayDir = $overlay;
        $result->projectDir = $project;
        $result->force = $this->force;
        $result->dryRun = $this->dryRun;

        $overlayNorm = rtrim(str_replace('\\', '/', $overlay), '/');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $overlay,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $files = [];
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            $abs = str_replace('\\', '/', $file->getPathname());
            $rel = substr($abs, strlen($overlayNorm) + 1);
            if (!is_string($rel) || $rel === '') {
                continue;
            }
            $files[$rel] = $file;
        }
        ksort($files, SORT_STRING);

        if ($files === []) {
            throw new \RuntimeException("Overlay is empty: {$overlay}");
        }

        foreach ($files as $rel => $file) {
            $this->applyFile($result, $project, $rel, $file);
        }

        return $result;
    }

    private function applyFile(ApplyResult $result, string $project, string $rel, SplFileInfo $file): void
    {
        $dest = $project . '/' . $rel;

        if ($this->isProtected($rel)) {
            $result->protected[] = $rel;
            return;
        }

        $src = $file->getPathname();
        $exists = is_file($dest);

        if ($exists && $this->sameContents($src, $dest)) {
            $result->identical[] = $rel;
            return;
        }

        if ($exists && !$this->force) {
            $result->skipped[] = $rel;
            return;
        }

        if (!$this->dryRun) {
            $dir = dirname($dest);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException("Unable to create directory: {$dir}");
            }
            if (!copy($src, $dest)) {
                throw new \RuntimeException("Unable to copy {$rel} to {$dest}");
            }
            $mode = $file->getPerms() & 0777;
            if ($mode !== 0) {
                chmod($dest, $mode);
            }
        }

        $result->written[] = $rel;
        if ($exists && $this->force) {
            $result->forced[] = $rel;
        }
    }

    private function isProtected(string $rel): bool
    {
        $base = basename($rel);
        if (in_array($rel, self::PROTECTED_FILES, true) || in_array($base, self::PROTECTED_FILES, true)) {
            return true;
        }
        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if (str_starts_with($rel, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function sameContents(string $src, string $dest): bool
    {
        $a = hash_file('sha256', $src);
        $b = hash_file('sha256', $dest);

        return is_string($a) && $a === $b;
    }
}
