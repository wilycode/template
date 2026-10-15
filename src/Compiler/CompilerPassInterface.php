<?php

declare(strict_types=1);

namespace WilyCode\Template\Compiler;

interface CompilerPassInterface
{
    public function compile(string $template, CompilerContext $context): string;
}
