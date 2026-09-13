<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\SalesChannel;

/**
 * Hard refuse rewrite on a live consumer. SYNC_ALLOW_LIVE_RESTORE=1 does not bypass this.
 */
final class LiveConsumerGuard
{
    public static function isLiveToken(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return strtolower(trim($value)) === 'live';
    }

    public static function isLiveConsumer(
        ?string $syncEnv = null,
        ?string $deployEnv = null,
        ?string $checkoutBasename = null,
        ?string $hostname = null,
    ): bool {
        return self::isLiveToken($syncEnv)
            || self::isLiveToken($deployEnv)
            || self::isLiveToken($checkoutBasename)
            || self::isLiveToken($hostname);
    }

    public static function refuseMessage(?string $syncEnv, ?string $deployEnv): string
    {
        return sprintf(
            'ERROR: Refusing sales-channel domain rewrite on a live host (SYNC_ENV=%s, SHOPWARE_DEPLOY_ENV=%s). Unset SYNC_REWRITE_APP_URL / SYNC_REWRITE_URL_MAP. Rewrite is never allowed on live (SYNC_ALLOW_LIVE_RESTORE=1 does not bypass this).',
            self::display($syncEnv),
            self::display($deployEnv),
        );
    }

    public static function assertNotLive(
        ?string $syncEnv = null,
        ?string $deployEnv = null,
        ?string $checkoutBasename = null,
        ?string $hostname = null,
    ): void {
        if (self::isLiveConsumer($syncEnv, $deployEnv, $checkoutBasename, $hostname)) {
            throw new LiveRewriteRefusedException(self::refuseMessage($syncEnv, $deployEnv));
        }
    }

    private static function display(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return 'unset';
        }

        return $value;
    }
}
