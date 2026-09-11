<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\Composer;

use Composer\Script\Event;

final class Scripts
{
    public static function applyIfShop(Event $event): void
    {
        ApplyRunner::runFromComposer($event->getComposer(), $event->getIO());
    }
}
