<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\Tests;

use Fyrst\ShopwareCd\Command\RewriteSalesChannelUrlsCommand;
use Fyrst\ShopwareCd\FyrstShopwareCdBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class FyrstShopwareCdBundleTest extends TestCase
{
    public function testBuildRegistersRewriteCommand(): void
    {
        $container = new ContainerBuilder();
        (new FyrstShopwareCdBundle())->build($container);

        self::assertTrue($container->hasDefinition(RewriteSalesChannelUrlsCommand::class));
        $definition = $container->getDefinition(RewriteSalesChannelUrlsCommand::class);
        self::assertTrue($definition->isAutowired());
        self::assertTrue($definition->isAutoconfigured());
        self::assertTrue($definition->hasTag('console.command'));
    }
}
