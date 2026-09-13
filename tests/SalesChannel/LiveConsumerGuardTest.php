<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\Tests\SalesChannel;

use Fyrst\ShopwareCd\SalesChannel\LiveConsumerGuard;
use Fyrst\ShopwareCd\SalesChannel\LiveRewriteRefusedException;
use PHPUnit\Framework\TestCase;

final class LiveConsumerGuardTest extends TestCase
{
    public function testStagingConsumerIsAllowed(): void
    {
        self::assertFalse(LiveConsumerGuard::isLiveConsumer('staging', 'staging', 'acme-staging', 'vps-1'));
        LiveConsumerGuard::assertNotLive('staging', 'staging', 'acme-staging', 'vps-1');
        $this->addToAssertionCount(1);
    }

    public function testSyncEnvLiveIsRefused(): void
    {
        self::assertTrue(LiveConsumerGuard::isLiveConsumer('live', 'staging', 'acme-staging', 'vps-1'));
        $this->expectException(LiveRewriteRefusedException::class);
        $this->expectExceptionMessage('Refusing sales-channel domain rewrite on a live host');
        $this->expectExceptionMessage('SYNC_ALLOW_LIVE_RESTORE=1 does not bypass this');

        LiveConsumerGuard::assertNotLive('live', 'staging', 'acme-staging', 'vps-1');
    }

    public function testDeployEnvLiveIsRefused(): void
    {
        self::assertTrue(LiveConsumerGuard::isLiveConsumer('', 'LIVE', 'acme-staging', 'vps-1'));
        $this->expectException(LiveRewriteRefusedException::class);
        $this->expectExceptionMessage('SHOPWARE_DEPLOY_ENV=LIVE');

        LiveConsumerGuard::assertNotLive('', 'LIVE', 'acme-staging', 'vps-1');
    }

    public function testCheckoutBasenameLiveIsRefused(): void
    {
        self::assertTrue(LiveConsumerGuard::isLiveConsumer('staging', 'staging', 'live', 'vps-1'));
        $this->expectException(LiveRewriteRefusedException::class);

        LiveConsumerGuard::assertNotLive('staging', 'staging', 'live', 'vps-1');
    }

    public function testHostnameLiveIsRefused(): void
    {
        self::assertTrue(LiveConsumerGuard::isLiveConsumer('staging', 'staging', 'acme-staging', 'Live'));
        $this->expectException(LiveRewriteRefusedException::class);

        LiveConsumerGuard::assertNotLive('staging', 'staging', 'acme-staging', 'Live');
    }

    public function testRefuseMessageNeverMentionsBypass(): void
    {
        $message = LiveConsumerGuard::refuseMessage('live', 'live');

        self::assertStringContainsString('SYNC_ALLOW_LIVE_RESTORE=1 does not bypass this', $message);
        self::assertStringContainsString('SYNC_ENV=live', $message);
        self::assertStringContainsString('SHOPWARE_DEPLOY_ENV=live', $message);
    }
}
