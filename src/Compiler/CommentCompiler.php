<?php

declare(strict_types=1);

namespace WilyCode\Template\Compiler;

final class CommentCompiler implements CompilerPassInterface
{
    public function compile(string $template, CompilerContext $context): string
    {
        return preg_replace('/\{#[\s\S]*?#\}/', '', $template);
    }
}
