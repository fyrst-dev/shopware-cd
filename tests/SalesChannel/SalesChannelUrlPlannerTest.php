<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\Tests\SalesChannel;

use Fyrst\ShopwareCd\SalesChannel\InvalidRewriteOptionsException;
use Fyrst\ShopwareCd\SalesChannel\SalesChannelUrlPlanner;
use Fyrst\ShopwareCd\SalesChannel\UrlRewriteCollisionException;
use PHPUnit\Framework\TestCase;

final class SalesChannelUrlPlannerTest extends TestCase
{
    public function testRequestedIsFalseWhenUnset(): void
    {
        self::assertFalse(SalesChannelUrlPlanner::requested(null, null));
        self::assertFalse(SalesChannelUrlPlanner::requested('', '  '));
    }

    public function testRequestedIsTrueWhenAppUrlOrMapSet(): void
    {
        self::assertTrue(SalesChannelUrlPlanner::requested('https://staging.example.com', null));
        self::assertTrue(SalesChannelUrlPlanner::requested(null, 'https://a.example.com=https://b.example.com'));
    }

    public function testOriginReplaceKeepsPath(): void
    {
        $planner = SalesChannelUrlPlanner::fromOptions('https://staging.example.com', null);

        self::assertSame(
            'https://staging.example.com/en',
            $planner->apply('https://shop.example.com/en'),
        );
        self::assertSame(
            'https://staging.example.com',
            $planner->apply('https://shop.example.com'),
        );
        self::assertSame(
            'https://staging.example.com/de/',
            $planner->apply('http://shop.example.com:8080/de/'),
        );
    }

    public function testOriginReplaceKeepsQueryAndHash(): void
    {
        $planner = SalesChannelUrlPlanner::fromOptions('https://staging.example.com/', null);

        self::assertSame(
            'https://staging.example.com/en?x=1#frag',
            $planner->apply('https://shop.example.com/en?x=1#frag'),
        );
    }

    public function testAppUrlTrailingSlashesAreStripped(): void
    {
        $planner = SalesChannelUrlPlanner::fromOptions('https://staging.example.com///', null);

        self::assertSame(
            'https://staging.example.com/en',
            $planner->apply('https://shop.example.com/en'),
        );
    }

    public function testMapLongestPrefixWins(): void
    {
        $planner = SalesChannelUrlPlanner::fromOptions(
            null,
            'https://shop.example.com=https://staging.example.com,https://shop.example.com/en=https://en.staging.example.com,https://b2b.example.com=https://b2b.staging.example.com',
        );

        self::assertSame(
            'https://en.staging.example.com/about',
            $planner->apply('https://shop.example.com/en/about'),
        );
        self::assertSame(
            'https://staging.example.com/de',
            $planner->apply('https://shop.example.com/de'),
        );
        self::assertSame(
            'https://other.example.com',
            $planner->apply('https://other.example.com'),
        );
    }

    public function testMapIsAppliedBeforeAppUrl(): void
    {
        $planner = SalesChannelUrlPlanner::fromOptions(
            'https://fallback.example.com',
            'https://b2b.example.com=https://b2b.staging.example.com',
        );

        self::assertSame(
            'https://b2b.staging.example.com/en',
            $planner->apply('https://b2b.example.com/en'),
        );
        self::assertSame(
            'https://fallback.example.com/en',
            $planner->apply('https://shop.example.com/en'),
        );
    }

    public function testNonHttpUrlLeftUnchangedWhenOnlyAppUrlSet(): void
    {
        $planner = SalesChannelUrlPlanner::fromOptions('https://staging.example.com', null);

        self::assertSame('not-a-url', $planner->apply('not-a-url'));
    }

    public function testPlanRewritesChangedRowsOnly(): void
    {
        $planner = SalesChannelUrlPlanner::fromOptions('https://staging.example.com', null);
        $changes = $planner->plan([
            'https://live.example.com',
            'https://live.example.com/en',
            'https://staging.example.com',
        ]);

        self::assertCount(2, $changes);
        self::assertSame('https://live.example.com', $changes[0]->old);
        self::assertSame('https://staging.example.com', $changes[0]->new);
        self::assertSame('https://live.example.com/en', $changes[1]->old);
        self::assertSame('https://staging.example.com/en', $changes[1]->new);
    }

    public function testCollisionOnUniqueUrlIsRefused(): void
    {
        $planner = SalesChannelUrlPlanner::fromOptions('https://staging.example.com', null);

        $this->expectException(UrlRewriteCollisionException::class);
        $this->expectExceptionMessage('rewrite collision');
        $this->expectExceptionMessage('https://staging.example.com/x');

        $planner->plan([
            'https://a.example.com/x',
            'https://b.example.com/x',
        ]);
    }

    public function testInvalidAppUrlIsRejected(): void
    {
        $this->expectException(InvalidRewriteOptionsException::class);
        $this->expectExceptionMessage('absolute http(s) URL');

        SalesChannelUrlPlanner::fromOptions('ftp://staging.example.com', null);
    }

    public function testNewlineInAppUrlIsRejected(): void
    {
        $this->expectException(InvalidRewriteOptionsException::class);
        $this->expectExceptionMessage('newline');

        SalesChannelUrlPlanner::fromOptions("https://staging.example.com\nhttps://evil.example.com", null);
    }

    public function testMapEntryMustBeOldEqualsNew(): void
    {
        $this->expectException(InvalidRewriteOptionsException::class);
        $this->expectExceptionMessage('is not old=new');

        SalesChannelUrlPlanner::fromOptions(null, 'https://shop.example.com');
    }

    public function testMapNewMayContainEqualsInQuery(): void
    {
        $planner = SalesChannelUrlPlanner::fromOptions(
            null,
            'https://shop.example.com=https://staging.example.com?x=1',
        );

        self::assertSame(
            'https://staging.example.com?x=1/en',
            $planner->apply('https://shop.example.com/en'),
        );
    }
}
