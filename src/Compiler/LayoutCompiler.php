<?php

declare(strict_types=1);

namespace WilyCode\Template\Compiler;

final class LayoutCompiler implements CompilerPassInterface
{
    public function __construct(private readonly DirectiveParser $parser)
    {
    }

    public function compile(string $template, CompilerContext $context): string
    {
        foreach ([
            'extends' => 'setParent',
            'section' => 'startSection',
            'yield' => 'yieldSection',
            'hasSection' => 'hasSection',
            'push' => 'startPush',
            'stack' => 'yieldStack',
        ] as $directive => $method) {
            $template = $this->parser->replaceParenthesizedDirective(
                $template,
                $directive,
                static function (string $arguments) use ($directive, $method): string {
                    $expression = trim($arguments);

                    if ($directive === 'hasSection') {
                        return "<?php if (\$__engine->{$method}({$expression})): ?>";
                    }

                    if ($directive === 'yield' || $directive === 'stack') {
                        return "<?= \$__engine->{$method}({$expression}) ?>";
                    }

                    return "<?php \$__engine->{$method}({$expression}); ?>";
                },
            );
        }

        return preg_replace(
            ['/@endsection\b/', '/@endhasSection\b/', '/@endpush\b/'],
            ['<?php $__engine->endSection(); ?>', '<?php endif; ?>', '<?php $__engine->endPush(); ?>'],
            $template,
        );
    }
}
