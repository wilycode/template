<?php

declare(strict_types=1);

namespace WilyCode\Template\Compiler;

use RuntimeException;

final class ComponentCompiler implements CompilerPassInterface
{
    public function __construct(private readonly DirectiveParser $parser)
    {
    }

    public function compile(string $template, CompilerContext $context): string
    {
        $template = $this->compileComponentDefinitions($template, $context);
        $template = $this->compileComponentCalls($template);

        return $this->compileUses($template);
    }

    private function compileComponentDefinitions(string $template, CompilerContext $context): string
    {
        $result = '';
        $offset = 0;
        $length = strlen($template);

        while ($offset < $length && preg_match(
            '/@component\s+([a-zA-Z_]\w*)\s*\(/',
            $template,
            $match,
            PREG_OFFSET_CAPTURE,
            $offset,
        )) {
            $start = $match[0][1];
            $name = $match[1][0];
            $openParenPosition = $start + strlen($match[0][0]) - 1;
            $closeParenPosition = $this->parser->findMatchingDelimiter($template, $openParenPosition, '(', ')');

            if ($closeParenPosition === null) {
                throw new RuntimeException("Unclosed parameters in component '{$name}'.");
            }

            $endPosition = strpos($template, '@endcomponent', $closeParenPosition + 1);

            if ($endPosition === false) {
                throw new RuntimeException("Missing @endcomponent for component '{$name}'.");
            }

            $parameters = trim(substr(
                $template,
                $openParenPosition + 1,
                $closeParenPosition - $openParenPosition - 1,
            ));
            $body = substr($template, $closeParenPosition + 1, $endPosition - $closeParenPosition - 1);
            $this->assertValidComponentParameterNames($name, $parameters);

            [$signature, $initializers] = $this->compileComponentSignature($parameters);
            $compiledBody = $context->compileNested($body);

            $result .= substr($template, $offset, $start - $offset);
            $result .= "<?php "
                . "\$__engine->registerComponent('{$name}', function({$signature}) use (\$__engine) { "
                . $initializers
                . "\$__context += ['slot' => null]; extract(\$__context, EXTR_SKIP); ob_start(); ?>"
                . $compiledBody
                . "<?php return ob_get_clean(); }); ?>";
            $offset = $endPosition + strlen('@endcomponent');
        }

        return $result . substr($template, $offset);
    }

    /** @return array{0:string,1:string} */
    private function compileComponentSignature(string $parameters): array
    {
        $signature = [];
        $initializers = '';

        foreach ($this->parser->splitTopLevelArguments($parameters) as $parameter) {
            // PHP parameter defaults cannot call arbitrary functions. For an
            // untyped component parameter, defer its default expression to
            // invocation time, preserving the distinction between omitted and
            // explicitly supplied null values (including named arguments).
            if (preg_match('/^\s*(\$[a-zA-Z_]\w*)\s*=\s*(.+)$/s', $parameter, $parts)) {
                $name = $parts[1];
                $default = trim($parts[2]);
                $signature[] = $name . ' = \WilyCode\Template\Compiler\UndefinedComponentArgument::Value';
                $initializers .= "if ({$name} === \WilyCode\Template\Compiler\UndefinedComponentArgument::Value) { {$name} = {$default}; } ";
            } else {
                $signature[] = $parameter;
            }
        }

        $signature[] = 'array $__context = []';

        return [implode(', ', $signature), $initializers];
    }

    private function compileComponentCalls(string $template): string
    {
        $tokens = $this->tokenizeComponentCalls($template);

        if ($tokens === []) {
            return $template;
        }

        $openStack = [];

        foreach ($tokens as $index => $token) {
            if ($token['type'] === 'start') {
                $openStack[] = $index;
                continue;
            }

            if ($token['type'] !== 'end') {
                continue;
            }

            for ($i = count($openStack) - 1; $i >= 0; $i--) {
                $openIndex = $openStack[$i];

                if ($tokens[$openIndex]['name'] === $token['name']) {
                    $tokens[$openIndex]['kind'] = 'block-start';
                    $tokens[$index]['kind'] = 'block-end';
                    array_splice($openStack, $i, 1);
                    continue 2;
                }
            }

            $tokens[$index]['kind'] = 'raw';
        }

        foreach ($openStack as $openIndex) {
            $tokens[$openIndex]['kind'] = 'inline';
        }

        $result = '';
        $offset = 0;

        foreach ($tokens as $token) {
            $result .= substr($template, $offset, $token['start'] - $offset);

            $result .= match ($token['kind']) {
                'block-start' => $this->compileBlockComponentStart($token['name'], $token['args']),
                'block-end' => '<?= $__engine->endComponentBlock() ?>',
                'inline' => $this->compileInlineComponentCall($token['name'], $token['args']),
                default => $token['raw'],
            };

            $offset = $token['end'];
        }

        $result .= substr($template, $offset);

        return $result;
    }

    private function compileInlineComponentCall(string $name, string $arguments): string
    {
        [$positionalArguments, $namedArguments] = $this->compileComponentArgumentLists($arguments);

        return "<?= \$__engine->callComponent('{$name}', {$positionalArguments}, {$namedArguments}) ?>";
    }

    private function compileBlockComponentStart(string $name, string $arguments): string
    {
        [$positionalArguments, $namedArguments] = $this->compileComponentArgumentLists($arguments);

        return "<?php \$__engine->startComponentBlock('{$name}', {$positionalArguments}, {$namedArguments}); ?>";
    }

    /** @return array{0:string,1:string} */
    private function compileComponentArgumentLists(string $arguments): array
    {
        $parsed = $this->parser->parseNamedArguments($arguments, 'component');

        $positionalPhp = $parsed['positional'] === []
            ? '[]'
            : '[' . implode(', ', $parsed['positional']) . ']';

        $namedPhp = $parsed['named'] === []
            ? '[]'
            : '[' . implode(', ', $parsed['named']) . ']';

        return [$positionalPhp, $namedPhp];
    }

    /**
     * @return list<array{
     *     type:string,
     *     kind:?string,
     *     name:string,
     *     args:string,
     *     raw:string,
     *     start:int,
     *     end:int
     * }>
     */
    private function tokenizeComponentCalls(string $template): array
    {
        $tokens = [];
        $length = strlen($template);
        $offset = 0;

        while ($offset < $length) {
            $atPosition = strpos($template, '@', $offset);

            if ($atPosition === false) {
                break;
            }

            if (preg_match('/\G@end([a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)+)/A', $template, $match, 0, $atPosition)) {
                $raw = $match[0];

                $tokens[] = [
                    'type' => 'end',
                    'kind' => null,
                    'name' => $match[1],
                    'args' => '',
                    'raw' => $raw,
                    'start' => $atPosition,
                    'end' => $atPosition + strlen($raw),
                ];

                $offset = $atPosition + strlen($raw);
                continue;
            }

            if (preg_match('/\G@([a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)+)\s*\(/A', $template, $match, 0, $atPosition)) {
                $name = $match[1];
                $openParenPosition = $atPosition + strlen($match[0]) - 1;
                $closeParenPosition = $this->parser->findMatchingDelimiter(
                    $template,
                    $openParenPosition,
                    '(',
                    ')',
                );

                if ($closeParenPosition === null) {
                    $offset = $atPosition + 1;
                    continue;
                }

                $raw = substr($template, $atPosition, $closeParenPosition - $atPosition + 1);
                $tokens[] = [
                    'type' => 'start',
                    'kind' => null,
                    'name' => $name,
                    'args' => substr(
                        $template,
                        $openParenPosition + 1,
                        $closeParenPosition - $openParenPosition - 1,
                    ),
                    'raw' => $raw,
                    'start' => $atPosition,
                    'end' => $closeParenPosition + 1,
                ];

                $offset = $closeParenPosition + 1;
                continue;
            }

            $offset = $atPosition + 1;
        }

        return $tokens;
    }

    private function compileUses(string $template): string
    {
        return $this->parser->replaceParenthesizedDirective(
            $template,
            'use',
            function (string $arguments): string {
                $parts = $this->parser->splitTopLevelArguments($arguments);

                if (count($parts) !== 2) {
                    throw new RuntimeException('@use expects a template name and a component alias.');
                }

                return sprintf(
                    '<?php $__engine->useComponents(%s, %s, get_defined_vars()); ?>',
                    $parts[0],
                    $parts[1],
                );
            },
        );
    }

    private function assertValidComponentParameterNames(string $componentName, string $parameters): void
    {
        if (preg_match('/\$__context\b/', $parameters)) {
            throw new RuntimeException(
                "Component '{$componentName}' cannot declare the reserved parameter \$__context.",
            );
        }
    }
}
