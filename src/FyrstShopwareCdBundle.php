<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd;

use Fyrst\ShopwareCd\Command\RewriteSalesChannelUrlsCommand;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Auto-registered by Symfony Flex from extra.symfony.bundle (and Flex's
 * Bundle class heuristic). Shops do not hand-edit services.yaml.
 */
final class FyrstShopwareCdBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->register(RewriteSalesChannelUrlsCommand::class, RewriteSalesChannelUrlsCommand::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('console.command');
    }
}
