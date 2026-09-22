<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Empty bundle. Auto-registered by Symfony Flex from extra.symfony.bundle
 * (and Flex's Bundle class heuristic) so existing config/bundles.php lines
 * still load. Shops do not hand-edit services.yaml.
 *
 * This package does not register a console command and does not shell
 * Shopware's sales-channel:update:domain. fyrst-cli calls that command.
 */
final class FyrstShopwareCdBundle extends Bundle
{
}
