<?php

declare (strict_types = 1); // @codeCoverageIgnore

namespace Recoil\Dev\Instrumentation;

use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\FunctionLike;
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
    public function __construct()
    {
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

        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor(new NameResolver());
        $this->traverser->addVisitor($this);
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
        if (\strpos($source, '@recoil-coroutine') === false) {
            return $source;
        }

        $this->input = $source;
        $this->output = '';
        $this->position = 0;

        $ast = $this->parser->parse($source);
        $this->traverser->traverse($ast);

        try {
            return $this->output . \substr($this->input, $this->position);
        } finally {
            $this->input = '';
            $this->output = '';
        }
    }

    /**
     * Add instrumentation to a coroutine.
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
            'assert(!isset(%s) || %s->setFunction(__FILE__, __LINE__, __FUNCTION__, \func_get_args()) || true); ',
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
     * A function is considered a coroutine if it meets all of the following
     * criteria:
     *
     *  - Has a return type hint that resolves to \Generator.
     *  - Is annotated with @recoil-coroutine.
     *  - Has at least one statement (generators MUST have a yield in the body).
     */
    private function isCoroutine(Node $node) : bool
    {
        if (!$node instanceof FunctionLike) {
            return false;
        }

        $returnType = $node->getReturnType();

        if (!$returnType instanceof FullyQualified) {
            return false;
        } elseif ($returnType->toString() !== 'Generator') {
            return false;
        }

        $doc = $node->getDocComment();

        if ($doc === null) {
            return false;
        } elseif (!preg_match('/@recoil-coroutine\b/m', $doc->getText())) {
            return false;
        }

        return !empty($node->getStmts());
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
     * Visit the given node.
     *
     * @access private
     */
    public function enterNode(Node $node)
    {
        if ($this->isCoroutine($node)) {
            $this->instrumentCoroutine($node);
        }
    }

    const TRACE_VARIABLE_NAME = "\$\xe2\x9d\xb0trace\xe2\x9d\xb1";

    /**
     * @var Parser The PHP parser.
     */
    private $parser;

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
