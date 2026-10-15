<?php

declare(strict_types=1);

namespace WilyCode\Template\Compiler;

use RuntimeException;

final class FunctionCompiler implements CompilerPassInterface
{
    public function __construct(private readonly DirectiveParser $parser)
    {
    }

    public function compile(string $template, CompilerContext $context): string
    {
        $result = '';
        $offset = 0;
        $length = strlen($template);

        while ($offset < $length && preg_match('/@([a-zA-Z_]\w*)\s*\(/', $template, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $rawStart = $match[0][1];
            $name = $match[1][0];
            $rawPrefix = $match[0][0];

            if (!$context->hasFunction($name) || $context->isBuiltinDirective($name)) {
                $result .= substr($template, $offset, $rawStart - $offset + 1);
                $offset = $rawStart + 1;
                continue;
            }

            $openParenPosition = $rawStart + strlen($rawPrefix) - 1;
            $closeParenPosition = $this->parser->findMatchingDelimiter($template, $openParenPosition, '(', ')');

            if ($closeParenPosition === null) {
                throw new RuntimeException("Unclosed template function call @{$name}(...). ");
            }

            $result .= substr($template, $offset, $rawStart - $offset);
            $arguments = substr($template, $openParenPosition + 1, $closeParenPosition - $openParenPosition - 1);
            $result .= $this->compileFunctionCall($name, $arguments);
            $offset = $closeParenPosition + 1;
        }

        return $result . substr($template, $offset);
    }

    private function compileFunctionCall(string $name, string $arguments): string
    {
        $arguments = trim($arguments);

        if ($arguments === '') {
            return "<?= \$__engine->callFunction('{$name}') ?>";
        }

        $parsed = $this->parser->parseNamedArguments($arguments, 'function');
        $compiled = array_merge($parsed['positional'], $parsed['named']);
        $argumentsPhp = $compiled === [] ? '[]' : '[' . implode(', ', $compiled) . ']';

        return "<?= \$__engine->callFunction('{$name}', {$argumentsPhp}) ?>";
    }
}
