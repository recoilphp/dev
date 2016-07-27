<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use RuntimeException;

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
        $this->instrumentationMode = $extra['recoil']['instrumentation'] ?? self::MODE_ALL;
    }

    public static function getSubscribedEvents()
    {
        return [ScriptEvents::POST_AUTOLOAD_DUMP => 'onPostAutoloadDump'];
    }

    public function onPostAutoloadDump($devMode)
    {
        if ($this->instrumentationMode === self::MODE_NONE) {
            $this->io->write('Recoil code instrumentation is disabled (in composer.json)');
        } elseif (!$devMode) {
            $this->io->write('Recoil code instrumentation is disabled (installing with --no-dev)');
        } else {
            $this->io->write('Recoil code instrumentation is enabled');

            try {
                $this->installAutoloader();
            } catch (RuntimeException $e) {
                $this->io->writeError('Unable to install instrumenting autoloader.');
                $this->io->writeError($e->getMessage(), IOInterface::VERBOSE);
            }
        }
    }

    private function installAutoloader()
    {
        $config = $this->composer->getConfig();
        $vendorDir = $config->get('vendor-dir');

        // Create a copy of the original composer autoloader ...
        if (!@copy($vendorDir . '/autoload.php', $vendorDir . '/autoload.original.php')) {
            throw new RuntimeException(error_get_last());
        }

        // Read the instrumentation autoloader template ...
        $content = @file_get_contents(__DIR__ . '/../../res/autoload.php.tmpl');
        if ($content === false) {
            throw new RuntimeException(error_get_last());
        }

        // Replace the %mode% place-holder ...
        $content = strtr(
            $content,
            '%mode%',
            var_export($this->instrumentationMode, true)
        );

        // Write the autoload.php file over the original Composer autoloader ...
        if (!@file_put_contents($vendorDir . '/autoload.php', $content)) {
            throw new RuntimeException(error_get_last());
        }
    }

    const MODE_ALL = 'all';
    const MODE_NONE = 'all';

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
    private $instrumentationMode = self::MODE_ALL;
}
