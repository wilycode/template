<?php

declare(strict_types=1);

namespace WilyCode\Template\Compiler;

use RuntimeException;

final class StateCompiler implements CompilerPassInterface
{
    public function __construct(private readonly DirectiveParser $parser)
    {
    }

    public function compile(string $template, CompilerContext $context): string
    {
        $template = $this->parser->replaceParenthesizedDirective(
            $template,
            'set',
            function (string $arguments): string {
                $parts = $this->parser->splitTopLevelArguments($arguments);

                if (count($parts) !== 2 || !preg_match('/^([\'\"])([a-zA-Z_]\w*)\1$/', trim($parts[0]), $match)) {
                    throw new RuntimeException('@set expects a quoted variable name and a value.');
                }

                return sprintf('<?php $%s = %s; ?>', $match[2], $parts[1]);
            },
        );

        return $this->parser->replaceParenthesizedDirective(
            $template,
            'share',
            function (string $arguments): string {
                $parts = $this->parser->splitTopLevelArguments($arguments);

                if (count($parts) !== 2) {
                    throw new RuntimeException('@share expects a name and a value.');
                }

                return sprintf('<?php $__engine->share(%s, %s); ?>', $parts[0], $parts[1]);
            },
        );
    }
}
