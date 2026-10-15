<?php

declare(strict_types=1);

namespace WilyCode\Template\Compiler;

use RuntimeException;

final class OutputCompiler implements CompilerPassInterface
{
    public function __construct(private readonly DirectiveParser $parser)
    {
    }

    public function compile(string $template, CompilerContext $context): string
    {
        $template = preg_replace_callback(
            '/\{\{\s*(.+?)\s*\}\}/s',
            function (array $match): string {
                $expression = $this->compileFilterExpression(trim($match[1]));

                return '<?= $__engine->e(' . $expression . ') ?>';
            },
            $template,
        );

        return preg_replace('/\{\!\!\s*(.+?)\s*\!\!\}/s', '<?= $1 ?>', $template);
    }

    private function compileFilterExpression(string $expression): string
    {
        $parts = $this->parser->splitTopLevelPipes($expression);
        $compiled = trim(array_shift($parts) ?? '');

        if ($compiled === '') {
            throw new RuntimeException('Template interpolation cannot contain an empty expression.');
        }

        foreach ($parts as $filterExpression) {
            $filterExpression = trim($filterExpression);

            if (!preg_match('/^([a-zA-Z_]\w*)(?:\s*\(([\s\S]*)\))?$/', $filterExpression, $match)) {
                throw new RuntimeException("Invalid template filter expression: {$filterExpression}");
            }

            $name = $match[1];
            $arguments = isset($match[2]) ? trim($match[2]) : '';
            $compiledArguments = $arguments === '' ? '' : ', ' . $arguments;
            $compiled = sprintf(
                '$__engine->applyFilter(%s, %s%s)',
                var_export($name, true),
                $compiled,
                $compiledArguments,
            );
        }

        return $compiled;
    }
}
