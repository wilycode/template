<?php

declare(strict_types=1);

namespace WilyCode\Template\Compiler;

use RuntimeException;

final class IncludeCompiler implements CompilerPassInterface
{
    public function __construct(private readonly DirectiveParser $parser)
    {
    }

    public function compile(string $template, CompilerContext $context): string
    {
        return $this->parser->replaceParenthesizedDirective(
            $template,
            'include',
            function (string $arguments): string {
                $parts = $this->parser->splitTopLevelArguments($arguments);

                if ($parts === [] || count($parts) > 2) {
                    throw new RuntimeException('@include expects a template name and an optional data array.');
                }

                $data = $parts[1] ?? '[]';

                return sprintf(
                    '<?= $__engine->renderPartial(%s, array_merge(get_defined_vars(), %s)) ?>',
                    $parts[0],
                    $data,
                );
            },
        );
    }
}
