<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
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
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array The event names to listen to.
     */
    public static function getSubscribedEvents()
    {
        return [ScriptEvents::POST_AUTOLOAD_DUMP => 'onPostAutoloadDump'];
    }

    /**
     * Replaces the composer autoloader with an instrumenting autoloader, if
     * configuration allows.
     */
    public function onPostAutoloadDump($devMode)
    {
        assert($this->composer !== null, 'plugin not activated');

        if ($this->instrumentationMode === Mode::NONE) {
            $this->io->write('Recoil code instrumentation is disabled (in composer.json)');
        } elseif (!$devMode) {
            $this->io->write('Recoil code instrumentation is disabled (installing with --no-dev)');
        } else {
            $this->io->write('Recoil code instrumentation is enabled');
            $this->installAutoloader();
        }
    }

    /**
     * Replaces the composer autoloader with an instrumenting autoloader.
     */
    private function installAutoloader()
    {
        $vendorDir = $this
            ->composer
            ->getPackage()
            ->getConfig()
            ->get('vendor-dir');

        // Create a copy of the original composer autoloader ...
        copy($vendorDir . '/autoload.php', $vendorDir . '/autoload.original.php');

        // Read the instrumentation autoloader template and replace the %mode% place-holder ...
        $content = file_get_contents(__DIR__ . '/../../res/autoload.php.tmpl');
        $content = str_replace(
            '%mode%',
            var_export($this->instrumentationMode, true),
            $content
        );

        // Write the autoload.php file over the original Composer autoloader ...
        file_put_contents($vendorDir . '/autoload.php', $content);
    }

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
