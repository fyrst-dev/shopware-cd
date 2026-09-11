<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\Composer;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Fyrst\ShopwareCd\OverlayApplier;
use Fyrst\ShopwareCd\ShopDetector;

final class ApplyRunner
{
    private static bool $applied = false;

    public static function runFromComposer(Composer $composer, IOInterface $io): void
    {
        if (self::$applied) {
            return;
        }

        if (getenv('FYRST_SHOPWARE_CD_SKIP_APPLY') === '1') {
            $io->write('<info>fyrst/shopware-cd:</info> FYRST_SHOPWARE_CD_SKIP_APPLY=1, skipping overlay apply');
            self::$applied = true;
            return;
        }

        $root = $composer->getPackage();
        if ($root->getName() === ShopDetector::PACKAGE_NAME) {
            $io->write('<info>fyrst/shopware-cd:</info> installing as the package root, skipping overlay apply');
            self::$applied = true;
            return;
        }

        $extra = $root->getExtra()['fyrst-shopware-cd'] ?? [];
        if (is_array($extra) && ($extra['skip-apply'] ?? false) === true) {
            $io->write('<info>fyrst/shopware-cd:</info> extra.fyrst-shopware-cd.skip-apply, skipping');
            self::$applied = true;
            return;
        }

        $composerFile = Factory::getComposerFile();
        $projectDir = realpath(dirname($composerFile)) ?: dirname($composerFile);

        if (ShopDetector::isTemplateRoot($projectDir) || !ShopDetector::isShopwareProject($projectDir)) {
            $io->write('<info>fyrst/shopware-cd:</info> not a Shopware shop, skipping overlay apply');
            self::$applied = true;
            return;
        }

        $overlay = self::findOverlay($composer);
        if ($overlay === null) {
            $io->writeError('<error>fyrst/shopware-cd: overlay/ not found in the installed package</error>');
            return;
        }

        $force = getenv('FYRST_SHOPWARE_CD_FORCE') === '1';
        $result = (new OverlayApplier($overlay, $projectDir, $force, false))->apply();
        foreach (preg_split("/\n/", rtrim($result->render()), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
            $io->write($line);
        }

        self::$applied = true;
    }

    private static function findOverlay(Composer $composer): ?string
    {
        foreach ($composer->getRepositoryManager()->getLocalRepository()->getPackages() as $package) {
            if ($package->getName() !== ShopDetector::PACKAGE_NAME) {
                continue;
            }
            $path = $composer->getInstallationManager()->getInstallPath($package);
            if (is_string($path) && is_dir($path . '/overlay')) {
                return $path . '/overlay';
            }
        }

        $vendor = $composer->getConfig()->get('vendor-dir');
        if (is_string($vendor) && is_dir($vendor . '/fyrst/shopware-cd/overlay')) {
            return $vendor . '/fyrst/shopware-cd/overlay';
        }

        return null;
    }
}
