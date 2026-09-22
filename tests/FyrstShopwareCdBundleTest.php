<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\Tests;

use Fyrst\ShopwareCd\FyrstShopwareCdBundle;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class FyrstShopwareCdBundleTest extends TestCase
{
    public function testBundleLoadsWithoutRegisteringCommands(): void
    {
        $bundle = new FyrstShopwareCdBundle();

        self::assertInstanceOf(Bundle::class, $bundle);
        self::assertSame('FyrstShopwareCdBundle', $bundle->getName());

        $build = (new ReflectionClass($bundle))->getMethod('build');
        self::assertSame(Bundle::class, $build->getDeclaringClass()->getName());
    }
}
