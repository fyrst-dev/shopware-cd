<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\Command;

use Doctrine\DBAL\Connection;
use Fyrst\ShopwareCd\SalesChannel\InvalidRewriteOptionsException;
use Fyrst\ShopwareCd\SalesChannel\LiveConsumerGuard;
use Fyrst\ShopwareCd\SalesChannel\LiveRewriteRefusedException;
use Fyrst\ShopwareCd\SalesChannel\SalesChannelDomainUrlRewriter;
use Fyrst\ShopwareCd\SalesChannel\SalesChannelUrlPlanner;
use Fyrst\ShopwareCd\SalesChannel\UrlRewriteCollisionException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fyrst:sales-channel:rewrite-urls',
    description: 'Rewrite sales_channel_domain.url after a non-live DB restore/sync',
)]
final class RewriteSalesChannelUrlsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                <<<'HELP'
Opt-in rewrite of Shopware <info>sales_channel_domain.url</info> after a VPS DB restore/sync.

Requires at least one of <info>--app-url</info> / <info>SYNC_REWRITE_APP_URL</info> or
<info>--map</info> / <info>SYNC_REWRITE_URL_MAP</info>. Overlay bash should only invoke this
command when rewrite was requested.

Updates that column (and <info>updated_at = NOW(3)</info>) only. Does <comment>not</comment> touch
media CDN, plugin configs, APP_URL in .env, or payment/shipping webhooks.

Hard-refused when <info>SHOPWARE_DEPLOY_ENV</info>, <info>SYNC_ENV</info>,
<info>--checkout-basename</info>, or hostname is <comment>live</info>.
<info>SYNC_ALLOW_LIVE_RESTORE=1</info> does not bypass this.
HELP
            )
            ->addOption(
                'app-url',
                null,
                InputOption::VALUE_REQUIRED,
                'New origin (or SYNC_REWRITE_APP_URL). Absolute http(s) URL; trailing slashes stripped; path/query/hash of each row kept',
            )
            ->addOption(
                'map',
                null,
                InputOption::VALUE_REQUIRED,
                'old=new,old=new prefix map (or SYNC_REWRITE_URL_MAP); longest old prefix first',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Plan and print old→new without UPDATE',
            )
            ->addOption(
                'deploy-env',
                null,
                InputOption::VALUE_REQUIRED,
                'This stack’s role (or SHOPWARE_DEPLOY_ENV). live is refused',
            )
            ->addOption(
                'sync-env',
                null,
                InputOption::VALUE_REQUIRED,
                'Sync consumer role (or SYNC_ENV). live is refused',
            )
            ->addOption(
                'checkout-basename',
                null,
                InputOption::VALUE_REQUIRED,
                'Checkout directory basename for the live guard (e.g. acme-staging). Recipes should pass basename of the shop checkout',
            )
            ->addOption(
                'hostname',
                null,
                InputOption::VALUE_REQUIRED,
                'Hostname for the live guard (default: gethostname())',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $appUrl = self::optionOrEnv($input, 'app-url', 'SYNC_REWRITE_APP_URL');
        $map = self::optionOrEnv($input, 'map', 'SYNC_REWRITE_URL_MAP');
        $deployEnv = self::optionOrEnv($input, 'deploy-env', 'SHOPWARE_DEPLOY_ENV');
        $syncEnv = self::optionOrEnv($input, 'sync-env', 'SYNC_ENV');
        $checkoutBasename = self::optionOrDefault(
            $input,
            'checkout-basename',
            basename((string) getcwd()),
        );
        $hostname = self::optionOrDefault(
            $input,
            'hostname',
            self::defaultHostname(),
        );
        $dryRun = (bool) $input->getOption('dry-run');

        if (!SalesChannelUrlPlanner::requested($appUrl, $map)) {
            $io->error(
                'Neither --app-url / SYNC_REWRITE_APP_URL nor --map / SYNC_REWRITE_URL_MAP is set. This command rewrites only when rewrite is requested.',
            );

            return Command::INVALID;
        }

        try {
            LiveConsumerGuard::assertNotLive($syncEnv, $deployEnv, $checkoutBasename, $hostname);
        } catch (LiveRewriteRefusedException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        try {
            $planner = SalesChannelUrlPlanner::fromOptions($appUrl, $map);
        } catch (InvalidRewriteOptionsException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $io->writeln('Opt-in sales_channel_domain rewrite (sales channel domains only; media CDN / plugin configs / payment webhooks are not updated)');
        if ($appUrl !== null) {
            $io->writeln(sprintf(
                'SYNC_REWRITE_APP_URL=%s (origin replace, path kept)',
                SalesChannelUrlPlanner::stripTrailingSlashes($appUrl),
            ));
        }
        if ($map !== null) {
            $io->writeln(sprintf('SYNC_REWRITE_URL_MAP=%s', $map));
        }

        $rewriter = new SalesChannelDomainUrlRewriter($this->connection);

        try {
            $urls = $rewriter->fetchUrls();
            $changes = $planner->plan($urls);
        } catch (UrlRewriteCollisionException $exception) {
            $io->error($exception->getMessage());
            $io->error('sales_channel_domain rewrite aborted (unique url collision). Use --map / SYNC_REWRITE_URL_MAP for a 1:1 prefix map.');

            return Command::FAILURE;
        }

        if ($changes === []) {
            $io->writeln('No sales_channel_domain.url rows needed rewriting');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->writeln(sprintf(
                'DRY-RUN would UPDATE %d sales_channel_domain.url row(s) (no UPDATE):',
                count($changes),
            ));
            foreach ($changes as $change) {
                $io->writeln(sprintf('  %s → %s', $change->old, $change->new));
            }

            return Command::SUCCESS;
        }

        foreach ($changes as $change) {
            $io->writeln(sprintf('sales_channel_domain %s → %s', $change->old, $change->new));
        }

        $rewriter->apply($changes);
        $io->writeln('sales_channel_domain rewrite finished. Payment/shipping webhooks and plugin URL configs may still need manual review.');

        return Command::SUCCESS;
    }

    private static function optionOrEnv(InputInterface $input, string $option, string $envName): ?string
    {
        $optionValue = $input->getOption($option);
        if (is_string($optionValue) && trim($optionValue) !== '') {
            return $optionValue;
        }

        return self::env($envName);
    }

    private static function optionOrDefault(InputInterface $input, string $option, ?string $default): ?string
    {
        $optionValue = $input->getOption($option);
        if (is_string($optionValue) && trim($optionValue) !== '') {
            return trim($optionValue);
        }

        if ($default === null || trim($default) === '') {
            return null;
        }

        return $default;
    }

    private static function env(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if ($value === false || $value === null) {
            return null;
        }
        $value = (string) $value;
        if (trim($value) === '') {
            return null;
        }

        return $value;
    }

    private static function defaultHostname(): ?string
    {
        $hostname = gethostname();
        if ($hostname === false || trim($hostname) === '') {
            return null;
        }

        return $hostname;
    }
}
