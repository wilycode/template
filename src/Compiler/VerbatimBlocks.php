<?php

declare(strict_types=1);

namespace WilyCode\Template\Compiler;

final class VerbatimBlocks
{
    /** @return array{0:string,1:array<string,string>} */
    public function protect(string $template): array
    {
        $blocks = [];
        $index = 0;

        $protected = preg_replace_callback(
            '/@verbatim\b([\s\S]*?)@endverbatim\b/',
            static function (array $match) use (&$blocks, &$index): string {
                $placeholder = sprintf('__WILYCODE_VERBATIM_%d_%s__', $index++, bin2hex(random_bytes(8)));
                $blocks[$placeholder] = $match[1];

                return $placeholder;
            },
            $template,
        );

        return [$protected, $blocks];
    }

    /** @param array<string,string> $blocks */
    public function restore(string $template, array $blocks): string
    {
        return $blocks === [] ? $template : strtr($template, $blocks);
    }
}
