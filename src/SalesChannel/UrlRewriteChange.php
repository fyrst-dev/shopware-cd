<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\SalesChannel;

final readonly class UrlRewriteChange
{
    public function __construct(
        public string $old,
        public string $new,
    ) {
    }
}
