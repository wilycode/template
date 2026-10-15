<?php

declare(strict_types=1);

namespace WilyCode\Template\Compiler;

final class ControlFlowCompiler implements CompilerPassInterface
{
    public function __construct(private readonly DirectiveParser $parser)
    {
    }

    public function compile(string $template, CompilerContext $context): string
    {
        foreach (['if', 'elseif', 'foreach'] as $directive) {
            $template = $this->parser->replaceParenthesizedDirective(
                $template,
                $directive,
                static function (string $expression) use ($directive): string {
                    return sprintf('<?php %s (%s): ?>', $directive, trim($expression));
                },
            );
        }

        $template = preg_replace_callback(
            '/^(\h*)@if\h+(.+?)\h*$/m',
            static fn (array $match): string => $match[1] . '<?php if (' . trim($match[2]) . '): ?>',
            $template,
        );
        $template = preg_replace_callback(
            '/^(\h*)@elseif\h+(.+?)\h*$/m',
            static fn (array $match): string => $match[1] . '<?php elseif (' . trim($match[2]) . '): ?>',
            $template,
        );
        $template = preg_replace_callback(
            '/^(\h*)@foreach\h+(.+?)\h*$/m',
            static fn (array $match): string => $match[1] . '<?php foreach (' . trim($match[2]) . '): ?>',
            $template,
        );

        return preg_replace(
            ['/@else\b/', '/@endif\b/', '/@endforeach\b/'],
            ['<?php else: ?>', '<?php endif; ?>', '<?php endforeach; ?>'],
            $template,
        );
    }
}
