<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Dev\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Recoil\Dev\Instrumentation\Mode;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * Activate the plugin.
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;

        $extra = $this->composer->getPackage()->getExtra();
        $this->instrumentationMode = $extra['recoil']['instrumentation'] ?? Mode::ALL;
    }

    /**
     * Deactivate the plugin.
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
        $this->instrumentationMode = Mode::NONE;
    }

    /**
     * Prepare the plugin to be uninstalled.
     *
     * This will be called after deactivate.
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array<string, array|string> The event names to listen to.
     */
    public static function getSubscribedEvents()
    {
        return [ScriptEvents::POST_AUTOLOAD_DUMP => 'onPostAutoloadDump'];
    }

    /**
     * Replaces the composer autoloader with an instrumenting autoloader, if
     * configuration allows.
     */
    public function onPostAutoloadDump(Event $event)
    {
        assert($this->composer !== null, 'plugin not activated');

        if ($this->instrumentationMode === Mode::NONE) {
            $this->io->write('Recoil code instrumentation is disabled (in composer.json)');
        } elseif (!$event->isDevMode()) {
            $this->io->write('Recoil code instrumentation is disabled (installing with --no-dev)');
        } elseif (!file_exists(self::TEMPLATE_PATH)) {
            $this->io->write('Recoil code instrumentation is disabled (uninstalling recoil/dev)');
        } else {
            $this->io->write('Recoil code instrumentation is enabled');
            $this->installAutoloader();

            return;
        }

        $this->removeAutoloader();
    }

    /**
     * Replaces the composer autoloader with an instrumenting autoloader.
     */
    private function installAutoloader()
    {
        $vendorDir = $this
            ->composer
            ->getConfig()
            ->get('vendor-dir');

        // Create a copy of the original composer autoloader ...
        copy($vendorDir . '/autoload.php', $vendorDir . '/' . self::COMPOSER_AUTOLOAD_FILE);

        // Read the instrumentation autoloader template and replace the %mode% place-holder ...
        $content = file_get_contents(self::TEMPLATE_PATH);
        $content = str_replace(
            '%mode%',
            var_export($this->instrumentationMode, true),
            $content
        );

        // Write the autoload.php file over the original Composer autoloader ...
        file_put_contents($vendorDir . '/autoload.php', $content);
    }

    /**
     * Remove the "original" autoloader file.
     */
    private function removeAutoloader()
    {
        $vendorDir = $this
            ->composer
            ->getConfig()
            ->get('vendor-dir');

        $filename = $vendorDir . '/' . self::COMPOSER_AUTOLOAD_FILE;

        if (file_exists($filename)) {
            unlink($filename);
        }
    }

    const TEMPLATE_PATH = __DIR__ . '/../../res/autoload.php.tmpl';
    const COMPOSER_AUTOLOAD_FILE = 'autoload.uninstrumented.php';

    /**
     * @var Composer|null The composer object (null = not yet activated).
     */
    private $composer;

    /**
     * @var IOInterface|null The composer IO interface (null = not yet activated).
     */
    private $io;

    /**
     * @var string The instrumentation mode ('all' or 'none').
     */
    private $instrumentationMode = Mode::ALL;
}
