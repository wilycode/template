<?php

declare(strict_types=1);

namespace WilyCode\Template\Compiler;

use WilyCode\Template\TemplateCompilerInterface;

final class CompilerContext
{
    /** @var array<string, true> */
    private array $functions;

    /** @var array<string, true> */
    private array $builtinDirectives;

    /**
     * @param list<string> $functionNames
     * @param list<string> $builtinDirectives
     */
    public function __construct(
        private readonly TemplateCompilerInterface $compiler,
        array $functionNames,
        array $builtinDirectives,
    ) {
        $this->functions = array_fill_keys($functionNames, true);
        $this->builtinDirectives = array_fill_keys($builtinDirectives, true);
    }

    public function hasFunction(string $name): bool
    {
        return isset($this->functions[$name]);
    }

    public function isBuiltinDirective(string $name): bool
    {
        return isset($this->builtinDirectives[$name]);
    }

    public function compileNested(string $template): string
    {
        return $this->compiler->compile(
            $template,
            array_keys($this->functions),
            array_keys($this->builtinDirectives),
        );
    }
}
