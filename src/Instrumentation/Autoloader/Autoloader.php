<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation\Autoloader;

use Composer\Autoload\ClassLoader;
use Recoil\Dev\Instrumentation\Instrumentor;
use Recoil\Dev\Instrumentation\Mode;

/**
 * An autoloader that instruments code.
 *
 * Mapping of class name to file is performed by a Composer autoloader.
 */
final class Autoloader
{
    /**
     * Install the autoloader.
     *
     * @param ClassLoader $composerLoader The Composer autoloader used to locate source files.
     * @param string      $mode           The instrumentation mode.
     *
     * @return ClassLoader The $composerLoader parameter.
     *
     * @see Mode
     */
    public static function install(ClassLoader $composerLoader, string $mode): ClassLoader
    {
        if (
            self::$instance !== null ||
            $mode === Mode::NONE ||
            ini_get('zend.assertions') <= 0
        ) {
            return $composerLoader;
        }

        $mode = Mode::memberByValue($mode);
        $scheme = StreamWrapper::install(Instrumentor::create($mode));

        self::$instance = new self($composerLoader, $scheme);
        self::$instance->register();

        return $composerLoader;
    }

    /**
     * Register this autoloader before all existing autoloaders.
     */
    public function register()
    {
        spl_autoload_register([$this, 'loadClass'], true, true);
    }

    /**
     * Unregister this autoloader.
     */
    public function unregister()
    {
        spl_autoload_unregister([$this, 'loadClass']);
    }

    /**
     * Load a class.
     */
    public function loadClass(string $className)
    {
        if (preg_match(self::EXCLUDE_PATTERN, $className)) {
            return;
        }

        $fileName = $this->composerLoader->findFile($className);

        if ($fileName !== false) {
            self::includeFile($this->scheme . '://' . $fileName);
        }
    }

    /**
     * @access private
     *
     * @see Autoloader::install()
     */
    public function __construct(ClassLoader $composerLoader, string $scheme)
    {
        $this->composerLoader = $composerLoader;
        $this->scheme = $scheme;
    }

    /**
     * Include a file in a scope with no variables defined.
     *
     * @param string $fileName
     */
    private static function includeFile()
    {
        include \func_get_arg(0);
    }

    /**
     * A regex pattern matching class names which are not to be instrumented.
     */
    const EXCLUDE_PATTERN = '/^(PhpParser|Recoil\\Dev)\\\\/';

    /**
     * @var self|null The installed autoloader (null = not installed).
     */
    private static $instance;

    /**
     * @var ClassLoader The underlying Composer autoloader.
     */
    private $composerLoader;

    /**
     * @var string The scheme to use for the instrumenting stream wrapper.
     */
    private $scheme;
}
