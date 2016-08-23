<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation;

use Error;
use Exception;
use Recoil\Awaitable;
use Recoil\Listener;
use Recoil\Strand;
use Recoil\StrandTrace;
use ReflectionProperty;
use Throwable;

final class Trace implements StrandTrace
{
    /**
     * Install a trace on the current strand.
     */
    public static function install() : Awaitable
    {
        return new class() implements Awaitable {
            public function await(Listener $listener)
            {
                assert($listener instanceof Strand);

                $trace = $listener->trace();

                if ($trace === null) {
                    $trace = new Trace();
                    $listener->setTrace($trace);
                }

                if ($trace instanceof Trace) {
                    $listener->send($trace);
                } else {
                    $listener->send(null);
                }
            }
        };
    }

    /**
     * Record information about the currently executing coroutine.
     */
    public function setCoroutine(
        string $file,
        int $line,
        string $class,
        string $function,
        string $type,
        array $arguments
    ) {
        assert($this->stackDepth > 0);

        $this->currentFile = $file;
        $this->currentLine = $line;

        $frame = &$this->stackFrames[$this->stackDepth - 1];
        $frame['function'] = $function;
        $frame['args'] = $arguments;

        if ($class !== '') {
            $frame['class'] = $class;

            if ($type !== '') {
                $frame['type'] = $type;
            }
        }
    }

    /**
     * Record the most recently executed line number.
     */
    public function setLine(int $line)
    {
        $this->currentLine = $line;
    }

    /**
     * Record a push to the call-stack.
     *
     * @param Strand $strand The strand being traced.
     * @param int    $depth  The depth of the call-stack BEFORE the push operation.
     */
    public function push(Strand $strand, int $depth)
    {
        $this->stackFrames[$this->stackDepth++] = [
            'recoil_synthesized' => true,
            'function' => '{uninstrumented coroutine}',
            'file' => $this->currentFile,
            'line' => $this->currentLine,
        ];

        $this->currentFile = 'Unknown';
        $this->currentLine = 0;
    }

    /**
     * Record a pop from the call-stack.
     *
     * @param Strand $strand The strand being traced.
     * @param int    $depth  The depth of the call-stack AFTER the pop operation.
     */
    public function pop(Strand $strand, int $depth)
    {
        $frame = &$this->stackFrames[--$this->stackDepth];
        $this->currentFile = $frame['file'];
        $this->currentLine = $frame['line'];
        $frame = null;
    }

    /**
     * Record keys and values yielded from the coroutine on the head of the stack.
     *
     * @param Strand $strand The strand being traced.
     * @param int    $depth  The current depth of the call-stack.
     * @param mixed  $key    The key yielded from the coroutine.
     * @param mixed  $value  The value yielded from the coroutine.
     */
    public function yield(Strand $strand, int $depth, $key, $value)
    {
    }

    /**
     * Record the action and value used to resume a yielded coroutine.
     *
     * @param Strand $strand The strand being traced.
     * @param int    $depth  The current depth of the call-stack.
     * @param string $action The resume action ('send' or 'throw').
     * @param mixed  $value  The resume value or exception.
     */
    public function resume(Strand $strand, int $depth, string $action, $value)
    {
        if ($action === 'throw') {
            $this->updateStackTrace($value);

        // Trapping the resume of the instrumentation code to setup the initial
        // number of stack frames ...
        } elseif ($value === $this) {
            while ($this->stackDepth < $depth) {
                $this->push($strand, $this->stackDepth);
            }
        }
    }

    /**
     * Record the suspension of a strand.
     *
     * @param Strand $strand The strand being traced.
     * @param int    $depth  The current depth of the call-stack.
     */
    public function suspend(Strand $strand, int $depth)
    {
    }

    /**
     * Record the action and value when a strand exits.
     *
     * @param Strand $strand The strand being traced.
     * @param int    $depth  The current depth of the call-stack.
     * @param string $action The final action performed on the strand's listener ('send' or 'throw').
     * @param mixed  $value  The strand result or exception.
     */
    public function exit(Strand $strand, int $depth, string $action, $value)
    {
        if ($action === 'throw') {
            $this->updateStackTrace($value);
        }
    }

    /**
     * Modify an exception's stack trace to match the strand, rather than the
     * native PHP stack trace.
     */
    private function updateStackTrace(Throwable $exception)
    {
        $updatedTrace = [];

        // Keep the original trace up until we find the internal generator code ...
        foreach ($exception->getTrace() as $index => $frame) {
            if ($frame['recoil_synthesized'] ?? false) {
                return;
            } elseif (($frame['class'] ?? '') === 'Generator') {
                $updatedTrace[$index - 1]['file'] = $this->currentFile;
                $updatedTrace[$index - 1]['line'] = $this->currentLine;

                break;
            }

            $frame['recoil_synthesized'] = true;
            $updatedTrace[] = $frame;
        }

        // Append the strand's stack frames onto the regular PHP stack frames ...
        for ($index = $this->stackDepth - 1; $index >= 0; --$index) {
            $updatedTrace[] = $this->stackFrames[$index];
        }

        // Replace the exception's trace property with the updated stack trace ...
        if ($exception instanceof Error) {
            self::$errorTraceProperty->setValue($exception, $updatedTrace);
        } else {
            self::$exceptionTraceProperty->setValue($exception, $updatedTrace);
        }
    }

    /**
     * @access private
     *
     * @see Trace::install()
     */
    public function __construct()
    {
        if (self::$exceptionTraceProperty === null) {
            self::$exceptionTraceProperty = new ReflectionProperty(Exception::class, 'trace');
            self::$exceptionTraceProperty->setAccessible(true);

            self::$errorTraceProperty = new ReflectionProperty(Error::class, 'trace');
            self::$errorTraceProperty->setAccessible(true);
        }
    }

    /**
     * @var ReflectionProperty The Exception::trace property.
     */
    private static $exceptionTraceProperty;

    /**
     * @var ReflectionProperty The Error::trace property.
     */
    private static $errorTraceProperty;

    /**
     * @var string The filename of the currently executing coroutine.
     */
    private $currentFile = 'Unknown';

    /**
     * @var int The most recently executed instrumented line number.
     */
    private $currentLine = 0;

    /**
     * @var array<array>
     */
    private $stackFrames = [];

    /**
     * @var int The current stack depth.
     */
    private $stackDepth = 0;
}
