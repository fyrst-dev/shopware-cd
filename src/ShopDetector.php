<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd;

final class ShopDetector
{
    public const PACKAGE_NAME = 'fyrst/shopware-cd';

    public static function findProjectRoot(?string $start = null): ?string
    {
        $dir = $start ?? getcwd();
        if (!is_string($dir) || $dir === '') {
            return null;
        }
        $dir = realpath($dir) ?: $dir;

        while (true) {
            if (self::isShopwareProject($dir)) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                return null;
            }
            $dir = $parent;
        }
    }

    public static function isShopwareProject(string $dir): bool
    {
        $composerFile = $dir . '/composer.json';
        $hasConsole = is_file($dir . '/bin/console');

        if (!is_file($composerFile)) {
            return $hasConsole;
        }

        $json = json_decode((string) file_get_contents($composerFile), true);
        if (!is_array($json)) {
            return $hasConsole;
        }

        if (($json['name'] ?? '') === self::PACKAGE_NAME) {
            return false;
        }

        return self::requiresShopware($json) || $hasConsole;
    }

    public static function isTemplateRoot(string $dir): bool
    {
        $composerFile = $dir . '/composer.json';
        if (!is_file($composerFile) || !is_dir($dir . '/overlay')) {
            return false;
        }

        $json = json_decode((string) file_get_contents($composerFile), true);

        return is_array($json) && ($json['name'] ?? '') === self::PACKAGE_NAME;
    }

    /**
     * @param array<string, mixed> $json
     */
    public static function requiresShopware(array $json): bool
    {
        foreach (['require', 'require-dev'] as $section) {
            if (!isset($json[$section]) || !is_array($json[$section])) {
                continue;
            }
            foreach (array_keys($json[$section]) as $name) {
                if (is_string($name) && str_starts_with($name, 'shopware/')) {
                    return true;
                }
            }
        }

        return false;
    }
}
