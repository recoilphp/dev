<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation\Autoloader;

use PhpParser\Error;
use Recoil\Dev\Instrumentation\Instrumentor;

/**
 * A PHP stream wrapper that instruments code.
 *
 * This stream wrapper is used by the autoloader to filter included source code
 * through the instrumentor without affecting the __FILE__ and __DIR__ constants
 * within the instrumented code.
 */
final class StreamWrapper
{
    /**
     * Install the stream wrapper for the given instrumentor and return the
     * scheme (aka protocol) used to filter file contents through that
     * instrumentor.
     *
     * @param Instrumentor $instrumentor The instrumentor used to instrument the
     *                                   source code.
     *
     * @return string The scheme.
     */
    public static function install(Instrumentor $instrumentor) : string
    {
        $scheme = self::SCHEME_PREFIX . \spl_object_hash($instrumentor);

        if (!isset(self::$instrumentors[$scheme])) {
            self::$instrumentors[$scheme] = $instrumentor;
            stream_wrapper_register($scheme, __CLASS__);
        }

        return $scheme;
    }

    /**
     * Open the stream.
     *
     * This method is part of the stream wrapper specification.
     * @see http://php.net/manual/en/class.streamwrapper.php
     *
     * The $openedPath variable is assigned the real path of the instrumented
     * file. This ensures that __FILE__ and __DIR__ constants are unchanged
     * when instrumentation is added.
     */
    public function stream_open(
        string $path,
        string $mode,
        int $options = 0,
        string &$openedPath = null
    ) : bool {
        if ($mode[0] !== 'r') {
            return false;
        }

        list($scheme, $path) = self::parse($path);

        $stream = $this->openInstrumentedStream($scheme, $path);

        // The code could not be instrumented, just load the original file ...
        if ($stream === false) {
            $stream = fopen($path, $mode);

            if ($stream === false) {
                return false;
            }
        }

        $this->stream = $stream;
        $openedPath = \realpath($path);

        return true;
    }

    /**
     * Read from the stream.
     *
     * This method is part of the stream wrapper specification.
     * @see http://php.net/manual/en/class.streamwrapper.php
     */
    public function stream_read(int $count) : string
    {
        return fread($this->stream, $count);
    }

    /**
     * Close the stream.
     *
     * This method is part of the stream wrapper specification.
     * @see http://php.net/manual/en/class.streamwrapper.php
     */
    public function stream_close() : bool
    {
        return fclose($this->stream);
    }

    /**
     * Check if the stream has reached EOF.
     *
     * This method is part of the stream wrapper specification.
     * @see http://php.net/manual/en/class.streamwrapper.php
     */
    public function stream_eof() : bool
    {
        return feof($this->stream);
    }

    /**
     * Perform a stat() operation on the stream.
     *
     * This method is part of the stream wrapper specification.
     * @see http://php.net/manual/en/class.streamwrapper.php
     *
     * @return array|bool
     */
    public function stream_stat()
    {
        return fstat($this->stream);
    }

    /**
     * Perform a stat() operation on a specific path.
     *
     * This method is part of the stream wrapper specification.
     * @see http://php.net/manual/en/class.streamwrapper.php
     *
     * @return array|bool
     */
    public static function url_stat(string $path, int $flags)
    {
        list(, $path) = self::parse($path);

        return @stat($path);
    }

    /**
     * Parse the scheme and original path from a stream wrapper path.
     *
     * @return tuple<string, string>
     */
    private static function parse(string $path) : array
    {
        $index = \strpos($path, '://');
        assert($index !== false);

        return [
            \substr($path, 0, $index),
            \substr($path, $index + 3),
        ];
    }

    /**
     * Open a stream that contains the instrumented code.
     *
     * @return resource|false The stream (false = unable to add instrumentation).
     */
    private function openInstrumentedStream(string $scheme, string $path)
    {
        $source = file_get_contents($path);

        if ($source === false) {
            return false;
        }

        // Find the appropriate instrumentor ...
        $instrumentor = self::$instrumentors[$scheme];

        // Instrument the source ...
        try {
            $source = $instrumentor->instrument($source);
        } catch (Error $e) {
            return false;
        }

        // Write the instrumented code to a temporary file ...
        $stream = tmpfile();

        if (fwrite($stream, $source) === false) {
            return false;
        }

        if (fseek($stream, 0) === false) {
            return false;
        }

        return $stream;
    }

    const SCHEME_PREFIX = 'recoil-instrumentation-';

    /**
     * @var array<string, Instrumentor> A map of scheme to instrumentor.
     */
    private static $instrumentors = [];

    /**
     * @var resource|false The underlying stream object, false unless stream_open()
     *                     has been called successfully.
     */
    private $stream = false;
}
