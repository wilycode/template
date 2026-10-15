<?php

declare(strict_types=1);

namespace WilyCode\Template\Compiler;

use RuntimeException;

final class DirectiveParser
{
    /** @return list<string> */
    public function splitTopLevelArguments(string $arguments): array
    {
        $result = [];
        $current = '';
        $length = strlen($arguments);
        $level = 0;
        $inString = null;

        for ($i = 0; $i < $length; $i++) {
            $character = $arguments[$i];
            $previous = $i > 0 ? $arguments[$i - 1] : null;

            if ($inString !== null) {
                $current .= $character;

                if ($character === $inString && $previous !== '\\') {
                    $inString = null;
                }

                continue;
            }

            if ($character === "'" || $character === '"') {
                $inString = $character;
                $current .= $character;
                continue;
            }

            if ($character === '(' || $character === '[' || $character === '{') {
                $level++;
                $current .= $character;
                continue;
            }

            if ($character === ')' || $character === ']' || $character === '}') {
                if ($level > 0) {
                    $level--;
                }

                $current .= $character;
                continue;
            }

            if ($character === ',' && $level === 0) {
                $result[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $character;
        }

        if (trim($current) !== '') {
            $result[] = trim($current);
        }

        return $result;
    }

    /** @return array{positional:list<string>,named:list<string>} */
    public function parseNamedArguments(string $arguments, string $kind): array
    {
        $arguments = trim($arguments);

        if ($arguments === '') {
            return ['positional' => [], 'named' => []];
        }

        $parts = $this->splitTopLevelArguments($arguments);
        $positional = [];
        $named = [];
        $namedMode = false;

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $namedArgument = $this->parseNamedArgument($part);

            if ($namedArgument !== null) {
                $namedMode = true;
                [$argumentName, $expression] = $namedArgument;
                $named[] = var_export($argumentName, true) . ' => ' . $expression;
                continue;
            }

            if ($namedMode) {
                throw new RuntimeException(
                    "Positional {$kind} argument cannot appear after a named argument: {$part}",
                );
            }

            $positional[] = $part;
        }

        return ['positional' => $positional, 'named' => $named];
    }

    /** @return list<string> */
    public function splitTopLevelPipes(string $expression): array
    {
        $parts = [];
        $current = '';
        $length = strlen($expression);
        $level = 0;
        $inString = null;

        for ($i = 0; $i < $length; $i++) {
            $character = $expression[$i];
            $previous = $i > 0 ? $expression[$i - 1] : null;
            $next = $i + 1 < $length ? $expression[$i + 1] : null;

            if ($inString !== null) {
                $current .= $character;

                if ($character === $inString && $previous !== '\\') {
                    $inString = null;
                }

                continue;
            }

            if ($character === "'" || $character === '"') {
                $inString = $character;
                $current .= $character;
                continue;
            }

            if ($character === '(' || $character === '[' || $character === '{') {
                $level++;
                $current .= $character;
                continue;
            }

            if ($character === ')' || $character === ']' || $character === '}') {
                $level = max(0, $level - 1);
                $current .= $character;
                continue;
            }

            if ($character === '|' && $level === 0 && $previous !== '|' && $next !== '|') {
                $parts[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $character;
        }

        $parts[] = trim($current);

        return $parts;
    }

    public function replaceParenthesizedDirective(
        string $template,
        string $directive,
        callable $compiler,
    ): string {
        $result = '';
        $offset = 0;
        $length = strlen($template);
        $pattern = '/@' . preg_quote($directive, '/') . '\\s*\\(/';

        while ($offset < $length && preg_match($pattern, $template, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $match[0][1];
            $rawPrefix = $match[0][0];
            $openParenPosition = $start + strlen($rawPrefix) - 1;
            $closeParenPosition = $this->findMatchingDelimiter($template, $openParenPosition, '(', ')');

            if ($closeParenPosition === null) {
                throw new RuntimeException("Unclosed @{$directive}(...) directive.");
            }

            $result .= substr($template, $offset, $start - $offset);
            $arguments = substr($template, $openParenPosition + 1, $closeParenPosition - $openParenPosition - 1);
            $result .= $compiler($arguments);
            $offset = $closeParenPosition + 1;
        }

        return $result . substr($template, $offset);
    }

    public function findMatchingDelimiter(
        string $value,
        int $openPosition,
        string $openDelimiter,
        string $closeDelimiter,
    ): ?int {
        $length = strlen($value);
        $level = 0;
        $inString = null;

        for ($i = $openPosition; $i < $length; $i++) {
            $character = $value[$i];
            $previous = $i > 0 ? $value[$i - 1] : null;

            if ($inString !== null) {
                if ($character === $inString && $previous !== '\\') {
                    $inString = null;
                }

                continue;
            }

            if ($character === "'" || $character === '"') {
                $inString = $character;
                continue;
            }

            if ($character === $openDelimiter) {
                $level++;
                continue;
            }

            if ($character === $closeDelimiter) {
                $level--;

                if ($level === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /** @return array{0:string,1:string}|null */
    private function parseNamedArgument(string $part): ?array
    {
        if (!preg_match('/^([a-zA-Z_]\\w*)\\s*:\\s*(.+)$/s', $part, $matches)) {
            return null;
        }

        return [$matches[1], trim($matches[2])];
    }
}
