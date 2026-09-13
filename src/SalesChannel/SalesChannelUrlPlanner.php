<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\SalesChannel;

/**
 * Pure URL planner matching fyrst-dev/recipes deploy/lib/sync-rewrite.sh.
 *
 * SYNC_REWRITE_APP_URL: strip trailing slashes; replace each row's origin, keep path/query/hash.
 * SYNC_REWRITE_URL_MAP: old=new,old=new prefix map; longest old prefix first; trailing slashes stripped.
 */
final class SalesChannelUrlPlanner
{
    /**
     * @param list<array{old: string, new: string}> $map longest-prefix-first
     */
    public function __construct(
        private readonly ?string $appUrl,
        private readonly array $map = [],
    ) {
    }

    public static function requested(?string $appUrl, ?string $mapSpec): bool
    {
        return self::nonEmpty($appUrl) !== null || self::nonEmpty($mapSpec) !== null;
    }

    public static function fromOptions(?string $appUrl, ?string $mapSpec): self
    {
        $appUrl = self::nonEmpty($appUrl);
        $mapSpec = self::nonEmpty($mapSpec);

        $normalizedAppUrl = null;
        if ($appUrl !== null) {
            self::assertNoNewline('SYNC_REWRITE_APP_URL', $appUrl);
            $normalizedAppUrl = self::stripTrailingSlashes(trim($appUrl));
            self::validateHttpUrl('SYNC_REWRITE_APP_URL', $normalizedAppUrl);
        }

        return new self($normalizedAppUrl, self::parseMap($mapSpec));
    }

    public static function stripTrailingSlashes(string $value): string
    {
        while (str_ends_with($value, '/') && !str_ends_with($value, '://')) {
            $value = substr($value, 0, -1);
        }

        return $value;
    }

    public static function origin(string $url): ?string
    {
        if (preg_match('~^(https?)://([^/?#]+)~', $url, $matches) !== 1) {
            return null;
        }

        return $matches[1] . '://' . $matches[2];
    }

    public static function pathAfterOrigin(string $url): string
    {
        $origin = self::origin($url);
        if ($origin === null) {
            return '';
        }

        return substr($url, strlen($origin));
    }

    public function apply(string $oldUrl): string
    {
        foreach ($this->map as $pair) {
            $prefix = $pair['old'];
            if ($oldUrl === $prefix || str_starts_with($oldUrl, $prefix . '/')) {
                return $pair['new'] . substr($oldUrl, strlen($prefix));
            }
        }

        if ($this->appUrl === null) {
            return $oldUrl;
        }

        $origin = self::origin($oldUrl);
        if ($origin === null) {
            return $oldUrl;
        }

        return $this->appUrl . self::pathAfterOrigin($oldUrl);
    }

    /**
     * @param list<string> $urls
     *
     * @return list<UrlRewriteChange>
     */
    public function plan(array $urls): array
    {
        $changes = [];
        $seenNew = [];
        $oldForNew = [];
        $collisions = [];

        foreach ($urls as $old) {
            $old = rtrim($old, "\r");
            if ($old === '') {
                continue;
            }

            $new = $this->apply($old);
            if ($new === $old) {
                continue;
            }

            if (isset($seenNew[$new])) {
                $collisions[] = sprintf(
                    'ERROR: rewrite collision: %s and %s both become %s (sales_channel_domain.url is unique)',
                    $oldForNew[$new],
                    $old,
                    $new,
                );
                continue;
            }

            $seenNew[$new] = true;
            $oldForNew[$new] = $old;
            $changes[] = new UrlRewriteChange($old, $new);
        }

        if ($collisions !== []) {
            throw new UrlRewriteCollisionException(implode("\n", $collisions));
        }

        return $changes;
    }

    /**
     * @return list<array{old: string, new: string}>
     */
    private static function parseMap(?string $spec): array
    {
        if ($spec === null) {
            return [];
        }

        $pairs = [];
        foreach (explode(',', $spec) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            if (!str_contains($item, '=')) {
                throw new InvalidRewriteOptionsException(
                    sprintf('ERROR: SYNC_REWRITE_URL_MAP entry %s is not old=new', $item),
                );
            }

            $old = substr($item, 0, (int) strpos($item, '='));
            $new = substr($item, (int) strpos($item, '=') + 1);
            self::assertNoNewline('SYNC_REWRITE_URL_MAP old', $old);
            self::assertNoNewline('SYNC_REWRITE_URL_MAP new', $new);
            $old = self::stripTrailingSlashes($old);
            $new = self::stripTrailingSlashes($new);
            self::validateHttpUrl('SYNC_REWRITE_URL_MAP old', $old);
            self::validateHttpUrl('SYNC_REWRITE_URL_MAP new', $new);
            $pairs[] = ['old' => $old, 'new' => $new];
        }

        usort(
            $pairs,
            static fn (array $a, array $b): int => strlen($b['old']) <=> strlen($a['old']),
        );

        return $pairs;
    }

    private static function validateHttpUrl(string $label, string $url): void
    {
        if ($url === '') {
            throw new InvalidRewriteOptionsException(sprintf('ERROR: %s is empty', $label));
        }
        self::assertNoNewline($label, $url);
        if (preg_match('~^https?://[^/?#]+~', $url) !== 1) {
            throw new InvalidRewriteOptionsException(
                sprintf('ERROR: %s must be an absolute http(s) URL (got %s)', $label, $url),
            );
        }
    }

    private static function assertNoNewline(string $label, string $url): void
    {
        if (str_contains($url, "\n") || str_contains($url, "\r")) {
            throw new InvalidRewriteOptionsException(sprintf('ERROR: %s contains a newline', $label));
        }
    }

    private static function nonEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (trim($value) === '') {
            return null;
        }

        return $value;
    }
}
