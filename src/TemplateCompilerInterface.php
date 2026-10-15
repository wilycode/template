<?php

declare(strict_types=1);

namespace WilyCode\Template;

interface TemplateCompilerInterface
{
    /**
     * @param list<string> $functionNames
     * @param list<string> $builtinDirectives
     */
    public function compile(string $template, array $functionNames, array $builtinDirectives): string;
}
