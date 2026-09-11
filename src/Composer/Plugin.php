<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => ['onPostCmd', 0],
            ScriptEvents::POST_UPDATE_CMD => ['onPostCmd', 0],
        ];
    }

    public function onPostCmd(Event $event): void
    {
        ApplyRunner::runFromComposer($event->getComposer(), $event->getIO());
    }
}
