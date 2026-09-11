<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd;

final class OverlayLocator
{
    public static function locate(?string $explicit = null): string
    {
        if (is_string($explicit) && $explicit !== '') {
            return self::mustDirectory($explicit);
        }

        $fromEnv = getenv('FYRST_SHOPWARE_CD_OVERLAY');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return self::mustDirectory($fromEnv);
        }

        $packageRoot = dirname(__DIR__);
        $overlay = $packageRoot . '/overlay';
        if (is_dir($overlay)) {
            return realpath($overlay) ?: $overlay;
        }

        throw new \RuntimeException(
            'Unable to locate overlay/. Expected it next to the fyrst/shopware-cd package (clone or vendor/fyrst/shopware-cd/overlay).'
        );
    }

    private static function mustDirectory(string $path): string
    {
        $real = realpath($path);
        if ($real === false || !is_dir($real)) {
            throw new \RuntimeException("Overlay directory not found: {$path}");
        }

        return $real;
    }
}
