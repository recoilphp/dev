<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation;

use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;

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
     * @param FunctionLike $node The original AST node, as parsed from the source code.
     */
    private function instrumentCoroutine(FunctionLike $node)
    {
        $statements = $node->getStmts();

        // Insert a 'coroutine trace' at the first statement of the coroutine ...
        $this->consume($statements[0]->getAttribute('startFilePos'));
        $this->lastLine = $statements[0]->getAttribute('startLine');

        $this->output .= \sprintf(
            'assert(!\class_exists(\\%s::class) || (%s = yield \\%s::install()) || true); ',
            Trace::class,
            self::TRACE_VARIABLE_NAME,
            Trace::class
        );

        $this->output .= \sprintf(
            'assert(!isset(%s) || %s->setCoroutine(__FILE__, __LINE__, __FUNCTION__, \func_get_args()) || true); ',
            self::TRACE_VARIABLE_NAME,
            self::TRACE_VARIABLE_NAME
        );

        // Search all statements for yields and insert 'yield traces' ...
        foreach ($statements as $statement) {
            if ($statement instanceof Yield_) {
                $lineNumber = $statement->getAttribute('startLine');

                if ($lineNumber > $this->lastLine) {
                    $this->lastLine = $lineNumber;
                    $this->consume($statement->getAttribute('startFilePos'));

                    $this->output .= \sprintf(
                        'assert(!isset(%s) || %s->setLine(__LINE__) || true); ',
                        self::TRACE_VARIABLE_NAME,
                        self::TRACE_VARIABLE_NAME
                    );
                }
            }
        }
    }

    /**
     * Check if an AST node represents a function that is a coroutine.
     *
     * A function is considered a coroutine if it has a return type hint of
     * "Coroutine" which is aliases to the "\Generator" type.
     *
     * @param FunctionLike $node         The original AST node, as parsed from the source code.
     * @param FunctionLike $resolvedNode The same AST node, after passing through the name resolver.
     */
    private function isCoroutine(FunctionLike $node, FunctionLike $resolvedNode) : bool
    {
        $hintReturnType = $node->getReturnType();
        $realReturnType = $resolvedNode->getReturnType();

        return $realReturnType instanceof FullyQualified
            && $hintReturnType instanceof Name
            && \strcasecmp($realReturnType->toString(), 'Generator') === 0
            && \strcasecmp($hintReturnType->toString(), 'Coroutine') === 0
            && !empty($node->getStmts());
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
            $originalNode = clone $node;
            $this->nameResolver->enterNode($node);
            if ($this->isCoroutine($originalNode, $node)) {
                $this->instrumentCoroutine($originalNode);
            }
        } else {
            $this->nameResolver->enterNode($node);
        }
    }

    /**
     * @access private
     */
    public function leaveNode(Node $node)
    {
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
        $this->mode = $mode;

        if ($this->mode === Mode::NONE()) {
            return;
        }

        $factory = new ParserFactory();
        $this->parser = $factory->create(
            ParserFactory::ONLY_PHP7,
            new Lexer(['usedAttributes' => [
                'comments',
                'startLine',
                'startFilePos',
                'endFilePos',
            ]])
        );

        $this->nameResolver = new NameResolver();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this);
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
     * @var int The most recent line number where instrumentation updated the
     *          strand trace.
     */
    private $lastLine;
}
