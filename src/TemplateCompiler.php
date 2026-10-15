<?php

declare(strict_types=1);

namespace WilyCode\Template;

use WilyCode\Template\Compiler\CommentCompiler;
use WilyCode\Template\Compiler\CompilerContext;
use WilyCode\Template\Compiler\CompilerPassInterface;
use WilyCode\Template\Compiler\ComponentCompiler;
use WilyCode\Template\Compiler\ControlFlowCompiler;
use WilyCode\Template\Compiler\DirectiveParser;
use WilyCode\Template\Compiler\FunctionCompiler;
use WilyCode\Template\Compiler\IncludeCompiler;
use WilyCode\Template\Compiler\LayoutCompiler;
use WilyCode\Template\Compiler\OutputCompiler;
use WilyCode\Template\Compiler\StateCompiler;
use WilyCode\Template\Compiler\VerbatimBlocks;

/**
 * Compiles WilyCode template syntax to executable PHP.
 *
 * Built-in syntax is implemented as small compiler passes. Additional passes
 * run after the built-in structural directives and before functions/output.
 */
final class TemplateCompiler implements TemplateCompilerInterface
{
    /** @var list<CompilerPassInterface> */
    private array $passes;

    private VerbatimBlocks $verbatimBlocks;

    /**
     * @param iterable<CompilerPassInterface> $additionalPasses
     */
    public function __construct(iterable $additionalPasses = [])
    {
        $parser = new DirectiveParser();
        $this->verbatimBlocks = new VerbatimBlocks();

        $passes = [
            new CommentCompiler(),
            new ComponentCompiler($parser),
            new StateCompiler($parser),
            new LayoutCompiler($parser),
            new IncludeCompiler($parser),
            new ControlFlowCompiler($parser),
        ];

        foreach ($additionalPasses as $pass) {
            $passes[] = $pass;
        }

        $passes[] = new FunctionCompiler($parser);
        $passes[] = new OutputCompiler($parser);

        $this->passes = $passes;
    }

    public function compile(string $template, array $functionNames, array $builtinDirectives): string
    {
        [$template, $verbatimBlocks] = $this->verbatimBlocks->protect($template);
        $context = new CompilerContext($this, $functionNames, $builtinDirectives);

        foreach ($this->passes as $pass) {
            $template = $pass->compile($template, $context);
        }

        return $this->verbatimBlocks->restore($template, $verbatimBlocks);
    }
}
