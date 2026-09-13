<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\SalesChannel;

use Doctrine\DBAL\Connection;

/**
 * Reads and updates Shopware sales_channel_domain.url only (plus updated_at).
 */
final class SalesChannelDomainUrlRewriter
{
    public const TABLE = 'sales_channel_domain';

    public const SELECT_SQL = 'SELECT url FROM sales_channel_domain ORDER BY url';

    public const UPDATE_SQL = 'UPDATE sales_channel_domain SET url = ?, updated_at = NOW(3) WHERE url = ?';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<string>
     */
    public function fetchUrls(): array
    {
        /** @var list<string> $urls */
        $urls = $this->connection->fetchFirstColumn(self::SELECT_SQL);

        return $urls;
    }

    /**
     * @param list<UrlRewriteChange> $changes
     */
    public function apply(array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $this->connection->transactional(function (Connection $connection) use ($changes): void {
            foreach ($changes as $change) {
                $connection->executeStatement(self::UPDATE_SQL, [$change->new, $change->old]);
            }
        });
    }
}
