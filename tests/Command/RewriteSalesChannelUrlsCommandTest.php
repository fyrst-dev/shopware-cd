<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\Tests\Command;

use Doctrine\DBAL\Connection;
use Fyrst\ShopwareCd\Command\RewriteSalesChannelUrlsCommand;
use Fyrst\ShopwareCd\SalesChannel\SalesChannelDomainUrlRewriter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RewriteSalesChannelUrlsCommandTest extends TestCase
{
    /** @var Connection&MockObject */
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        putenv('SYNC_ALLOW_LIVE_RESTORE');
        putenv('SYNC_REWRITE_APP_URL');
        putenv('SYNC_REWRITE_URL_MAP');
        putenv('SHOPWARE_DEPLOY_ENV');
        putenv('SYNC_ENV');
        unset($_SERVER['SYNC_ALLOW_LIVE_RESTORE'], $_ENV['SYNC_ALLOW_LIVE_RESTORE']);
        unset($_SERVER['SYNC_REWRITE_APP_URL'], $_ENV['SYNC_REWRITE_APP_URL']);
        unset($_SERVER['SYNC_REWRITE_URL_MAP'], $_ENV['SYNC_REWRITE_URL_MAP']);
        unset($_SERVER['SHOPWARE_DEPLOY_ENV'], $_ENV['SHOPWARE_DEPLOY_ENV']);
        unset($_SERVER['SYNC_ENV'], $_ENV['SYNC_ENV']);
    }

    public function testMissingRewriteOptionsIsInvalid(): void
    {
        $this->connection->expects(self::never())->method('fetchFirstColumn');

        $tester = $this->tester();
        $status = $tester->execute([
            '--deploy-env' => 'staging',
            '--sync-env' => 'staging',
            '--checkout-basename' => 'acme-staging',
            '--hostname' => 'vps-1',
        ]);

        self::assertSame(Command::INVALID, $status);
        self::assertStringContainsString('Neither --app-url', $tester->getDisplay());
    }

    public function testLiveDeployEnvIsRefusedEvenWithAllowLiveRestore(): void
    {
        putenv('SYNC_ALLOW_LIVE_RESTORE=1');
        $_SERVER['SYNC_ALLOW_LIVE_RESTORE'] = '1';
        $_ENV['SYNC_ALLOW_LIVE_RESTORE'] = '1';

        $this->connection->expects(self::never())->method('fetchFirstColumn');
        $this->connection->expects(self::never())->method('executeStatement');

        $tester = $this->tester();
        $status = $tester->execute([
            '--app-url' => 'https://staging.example.com',
            '--deploy-env' => 'live',
            '--sync-env' => 'staging',
            '--checkout-basename' => 'acme-staging',
            '--hostname' => 'vps-1',
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Refusing sales-channel domain rewrite on a live host', $tester->getDisplay());
        self::assertStringContainsString('SYNC_ALLOW_LIVE_RESTORE=1 does not bypass this', $tester->getDisplay());
    }

    public function testDryRunPrintsPlanWithoutUpdate(): void
    {
        $this->connection->expects(self::once())
            ->method('fetchFirstColumn')
            ->with(SalesChannelDomainUrlRewriter::SELECT_SQL)
            ->willReturn([
                'https://live.example.com',
                'https://live.example.com/en',
            ]);
        $this->connection->expects(self::never())->method('executeStatement');
        $this->connection->expects(self::never())->method('transactional');

        $tester = $this->tester();
        $status = $tester->execute([
            '--app-url' => 'https://staging.example.com',
            '--dry-run' => true,
            '--deploy-env' => 'staging',
            '--sync-env' => 'staging',
            '--checkout-basename' => 'acme-staging',
            '--hostname' => 'vps-1',
        ]);

        $display = $tester->getDisplay();
        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('https://live.example.com → https://staging.example.com', $display);
        self::assertStringContainsString('https://live.example.com/en → https://staging.example.com/en', $display);
        self::assertStringContainsString('DRY-RUN', $display);
        self::assertStringNotContainsString('system_config', $display);
    }

    public function testCollisionAbortsWithoutUpdate(): void
    {
        $this->connection->expects(self::once())
            ->method('fetchFirstColumn')
            ->willReturn([
                'https://a.example.com/x',
                'https://b.example.com/x',
            ]);
        $this->connection->expects(self::never())->method('executeStatement');
        $this->connection->expects(self::never())->method('transactional');

        $tester = $this->tester();
        $status = $tester->execute([
            '--app-url' => 'https://staging.example.com',
            '--deploy-env' => 'staging',
            '--sync-env' => 'staging',
            '--checkout-basename' => 'acme-staging',
            '--hostname' => 'vps-1',
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('rewrite collision', $tester->getDisplay());
        self::assertStringContainsString('unique url collision', $tester->getDisplay());
    }

    public function testApplyUpdatesSalesChannelDomainOnly(): void
    {
        $this->connection->expects(self::once())
            ->method('fetchFirstColumn')
            ->with(SalesChannelDomainUrlRewriter::SELECT_SQL)
            ->willReturn(['https://live.example.com/en']);

        $this->connection->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(function (callable $callback) {
                return $callback($this->connection);
            });

        $this->connection->expects(self::once())
            ->method('executeStatement')
            ->with(
                SalesChannelDomainUrlRewriter::UPDATE_SQL,
                ['https://staging.example.com/en', 'https://live.example.com/en'],
            )
            ->willReturn(1);

        $tester = $this->tester();
        $status = $tester->execute([
            '--app-url' => 'https://staging.example.com',
            '--deploy-env' => 'staging',
            '--sync-env' => 'staging',
            '--checkout-basename' => 'acme-staging',
            '--hostname' => 'vps-1',
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('sales_channel_domain https://live.example.com/en → https://staging.example.com/en', $tester->getDisplay());
        self::assertStringContainsString('Payment/shipping webhooks', $tester->getDisplay());
        self::assertStringContainsString('NOW(3)', SalesChannelDomainUrlRewriter::UPDATE_SQL);
        self::assertStringContainsString('sales_channel_domain', SalesChannelDomainUrlRewriter::UPDATE_SQL);
        self::assertDoesNotMatchRegularExpression('/system_config|media|plugin/i', SalesChannelDomainUrlRewriter::UPDATE_SQL);
        self::assertDoesNotMatchRegularExpression('/system_config|media|plugin/i', SalesChannelDomainUrlRewriter::SELECT_SQL);
    }

    public function testReadsRewriteEnvWhenCliOptionsOmitted(): void
    {
        putenv('SYNC_REWRITE_APP_URL=https://staging.example.com');
        $_SERVER['SYNC_REWRITE_APP_URL'] = 'https://staging.example.com';
        $_ENV['SYNC_REWRITE_APP_URL'] = 'https://staging.example.com';

        $this->connection->expects(self::once())
            ->method('fetchFirstColumn')
            ->willReturn(['https://live.example.com']);
        $this->connection->expects(self::never())->method('executeStatement');
        $this->connection->expects(self::never())->method('transactional');

        $tester = $this->tester();
        $status = $tester->execute([
            '--dry-run' => true,
            '--deploy-env' => 'staging',
            '--sync-env' => 'staging',
            '--checkout-basename' => 'acme-staging',
            '--hostname' => 'vps-1',
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('https://live.example.com → https://staging.example.com', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new RewriteSalesChannelUrlsCommand($this->connection));
    }
}
