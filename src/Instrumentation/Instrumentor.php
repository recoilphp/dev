<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation;

use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser\Php7;
use SplStack;

/**
 * Instruments PHP code to provide additional debugging / trace information to
 * the Recoil kernel.
 */
final class Instrumentor extends NodeVisitorAbstract
{
    /**
     * Create an instrumentor.
     *
     * @param Mode|null $mode The instrumentation mode (null = Mode::ALL).
     */
    public static function create(Mode $mode = null) : self
    {
        return new self($mode ?? Mode::ALL());
    }

    /**
     * Instrument the given source code and return the instrumented code.
     *
     * @param string $source The original source code.
     *
     * @return string The instrumented code.
     */
    public function instrument(string $source) : string
    {
        if ($this->mode === Mode::NONE()) {
            return $source;
        } elseif (\stripos($source, 'coroutine') === false) {
            return $source;
        }

        $this->input = $source;
        $this->output = '';
        $this->position = 0;

        $ast = $this->parser->parse($source);
        $this->traverser->traverse($ast);

        $output = $this->output . \substr($this->input, $this->position);
        $this->input = '';
        $this->output = '';

        return $output;
    }

    /**
     * Add instrumentation to a coroutine.
     *
     * @param FunctionLike $node The AST node representing the function.
     */
    private function instrumentCoroutine(FunctionLike $function)
    {
        $statements = $function->getStmts();

        if (empty($statements)) {
            return;
        }

        $firstStatement = $statements[0];
        $function->setAttribute('lastInstrumentedLine', $firstStatement->getLine());
        $this->consume($firstStatement->getAttribute('startFilePos'));

        $this->inject(
            '(%s = \class_exists(\\%s::class) ? yield \\%s::install() : null) && '
            . '%s->setCoroutine(__FILE__, __LINE__, __CLASS__, __FUNCTION__, %s, \func_get_args())',
            self::TRACE_VARIABLE_NAME,
            Trace::class,
            Trace::class,
            self::TRACE_VARIABLE_NAME,
            var_export($this->callType($function), true)
        );
    }

    /**
     * Add instrumentation to a yield statement inside a coroutine.
     *
     * @param Yield_ $node The AST node representing the yield statement.
     */
    private function instrumentYield(Yield_ $yield)
    {
        $function = $this->functionStack->top();
        $startLine = $yield->getLine();

        if ($startLine > $function->getAttribute('lastInstrumentedLine')) {
            $function->setAttribute('lastInstrumentedLine', $startLine);
            $this->consume($yield->getAttribute('startFilePos'));

            $this->inject(
                '%s && %s->setLine(__LINE__)',
                self::TRACE_VARIABLE_NAME,
                self::TRACE_VARIABLE_NAME
            );
        }
    }

    /**
     * Get the "type" of the call, as described debug_backtrace().
     */
    private function callType(FunctionLike $node) : string
    {
        if ($node instanceof Closure) {
            $isStatic = $node->static;
        } elseif ($node instanceof ClassMethod) {
            $isStatic = $node->type & Class_::MODIFIER_STATIC;
        } else {
            return '';
        }

        return $isStatic ? '::' : '->';
    }

    /**
     * Check if an AST node represents a function that is a coroutine.
     *
     * A function is considered a coroutine if it has a return type hint of
     * "Coroutine" which is aliases to the "\Generator" type.
     *
     * @param FunctionLike     $node           The AST node, after passing through the name resolver.
     * @param Name|string|null $hintReturnType The original return type, before name resolution.
     */
    private function isCoroutine(FunctionLike $node, $hintReturnType) : bool
    {
        $realReturnType = $node->getReturnType();

        return $realReturnType instanceof FullyQualified
            && $hintReturnType instanceof Name
            && \strcasecmp($realReturnType->toString(), 'Generator') === 0
            && \strcasecmp($hintReturnType->toString(), 'Coroutine') === 0;
    }

    /**
     * Include original source code from the current position up until the given
     * position.
     */
    private function consume(int $position)
    {
        $this->output .= \substr($this->input, $this->position, $position - $this->position);
        $this->position = $position;
    }

    /**
     * Injection instrumentation code inside an assertion.
     */
    private function inject(string $pattern, string ...$arguments)
    {
        $this->output .= 'assert((' . sprintf($pattern, ...$arguments) . ') || true); ';
    }

    /**
     * @access private
     */
    public function beforeTraverse(array $nodes)
    {
        return $this->nameResolver->beforeTraverse($nodes);
    }

    /**
     * @access private
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof FunctionLike) {
            $this->enterFunction($node);
        } else {
            $this->nameResolver->enterNode($node);

            if (
                $node instanceof Yield_ &&
                $this->functionStack->top()->hasAttribute('isCoroutine')
            ) {
                $this->instrumentYield($node);
            }
        }
    }

    private function enterFunction(FunctionLike $function)
    {
        // Take a copy of the "unresolved" return type before passing the node
        // through the name resolver, as both are required to determine if this
        // function is a coroutine ...
        $unresolvedReturnType = $function->getReturnType();
        $this->nameResolver->enterNode($function);

        $this->functionStack->push($function);

        if ($this->isCoroutine($function, $unresolvedReturnType)) {
            $function->setAttribute('isCoroutine', true);
            $this->instrumentCoroutine($function);
        }
    }

    /**
     * @access private
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof FunctionLike) {
            $this->functionStack->pop();
        }

        return $this->nameResolver->leaveNode($node);
    }

    /**
     * @access private
     */
    public function afterTraverse(array $nodes)
    {
        return $this->nameResolver->afterTraverse($nodes);
    }

    /**
     * Please note that this code is not part of the public API. It may be
     * changed or removed at any time without notice.
     *
     * @access private
     *
     * This constructor is public so that it may be used by auto-wiring
     * dependency injection containers. If you are explicitly constructing an
     * instance please use one of the static factory methods listed below.
     *
     * @see Instrumentor::create()
     *
     * @param Mode $mode The instrumenation mode.
     */
    public function __construct(Mode $mode)
    {
        $this->parser = new Php7(
            new Lexer(['usedAttributes' => [
                'comments',
                'startLine',
                'startFilePos',
                'endFilePos',
            ]])
        );

        $this->mode = $mode;
        $this->nameResolver = new NameResolver();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this);
        $this->functionStack = new SplStack();
    }

    const TRACE_VARIABLE_NAME = '$μ';

    /**
     * @var Mode The instrumentation mode.
     */
    private $mode;

    /**
     * @var Parser The PHP parser.
     */
    private $parser;

    /**
     * @var NameResolver The visitor used to resolve type aliases.
     */
    private $nameResolver;

    /**
     * @var NodeTraverser The object that traverses the AST.
     */
    private $traverser;

    /**
     * @var string The original PHP source code.
     */
    private $input;

    /**
     * @var string The instrumented PHP code.
     */
    private $output;

    /**
     * @var int An index into the original source code indicating the code that
     *          has already been processed.
     */
    private $position;

    /**
     * @var SplStack<FunctionLike> The stack of functions being traversed.
     */
    private $functionStack;
}
