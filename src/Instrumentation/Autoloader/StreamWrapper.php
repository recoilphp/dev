<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation\Autoloader;

use PhpParser\Error;
use Recoil\Dev\Instrumentation\Instrumentor;

/**
 * A PHP stream wrapper that instruments code.
 */
final class StreamWrapper
{
    /**
     * Install the stream wrapper, if it has not already been installed.
     *
     * @param Instrumentor|null $instrumentor The instrumentor to use to
     *                                        instrument the code.
     *
     * @return string The stream wrapper's scheme (aka protocol).
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
     * Create a stream wrapper instance.
     *
     * @param Instrumentor|null $instrumentor The instrumentor (null = find based on scheme).
     */
    public function __construct(Instrumentor $instrumentor = null)
    {
        $this->instrumentor = $instrumentor;
    }

    /**
     * Open the stream.
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
        if ($stream === null) {
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
     * @return resource|null
     */
    private function openInstrumentedStream(string $scheme, string $path)
    {
        $source = file_get_contents($path);

        if ($source === false) {
            return false;
        }

        // Find the appropriate instrumentor ...
        if ($this->instrumentor === null) {
            $instrumentor = self::$instrumentors[$scheme];
        } else {
            $instrumentor = $this->instrumentor;
        }

        // Instrument the source ...
        try {
            $source = $instrumentor->instrument($source);
        } catch (Error $e) {
            return null;
        }

        // Write the instrumented code to a temporary file ...
        $stream = tmpfile();

        if (fwrite($stream, $source) === false) {
            return null;
        }

        if (fseek($stream, 0) === false) {
            return null;
        }

        return $stream;
    }

    /**
     * Read from the stream.
     */
    public function stream_read(int $count) : string
    {
        return fread($this->stream, $count);
    }

    /**
     * Close the stream.
     */
    public function stream_close() : bool
    {
        return fclose($this->stream);
    }

    /**
     * Check if the stream has reached EOF.
     */
    public function stream_eof() : bool
    {
        return feof($this->stream);
    }

    /**
     * Perform a stat() operation on the stream.
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

    const SCHEME_PREFIX = 'recoil-instrumentation-';

    /**
     * @var array<string, Instrumentor> A map of scheme to instrumentor.
     */
    private static $instrumentors = [];

    /**
     * @var Instrumentor|null The instrumentor to use (null = find based on scheme).
     */
    private $instrumentor;

    /**
     * @var resource|null The underlying stream object, null unless stream_open()
     *                    has been called successfully.
     */
    private $stream;
}
