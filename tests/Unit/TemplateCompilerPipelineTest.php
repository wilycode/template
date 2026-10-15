<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Template\Compiler\CompilerContext;
use WilyCode\Template\Compiler\CompilerPassInterface;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateCompiler;
use WilyCode\Template\TemplateCompilerInterface;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateCompilerPipelineTest extends TestCase
{
    private string $directory;

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            TempDir::remove($this->directory);
        }
    }

    public function testAdditionalCompilerPassRunsBeforeFunctionAndOutputCompilation(): void
    {
        $compiler = new TemplateCompiler([
            new class implements CompilerPassInterface {
                public function compile(string $template, CompilerContext $context): string
                {
                    return str_replace('@badge($title)', '{{ strtoupper($title) }}', $template);
                }
            },
        ]);

        $compiled = $compiler->compile('@badge($title)', [], []);

        self::assertSame('<?= $__engine->e(strtoupper($title)) ?>', $compiled);
    }

    public function testEngineAcceptsCompilerInterface(): void
    {
        $directory = $this->directory = TempDir::create();
        $templates = $directory . '/template';
        $cache = $directory . '/cache';
        mkdir($templates, 0777, true);
        file_put_contents($templates . '/page.tpl', 'ignored');

        $compiler = new class implements TemplateCompilerInterface {
            public function compile(string $template, array $functionNames, array $builtinDirectives): string
            {
                return 'compiled by injected compiler';
            }
        };

        $engine = new TemplateEngine(
            new TemplateLoader(new FileLocator(['' => $templates])),
            $cache,
            compiler: $compiler,
        );

        self::assertSame('compiled by injected compiler', $engine->render('page'));
    }

    public function testInjectedCompilersDoNotReuseAnotherEnginesCache(): void
    {
        $directory = $this->directory = TempDir::create();
        $views = $directory . '/views';
        $cache = $directory . '/cache';
        mkdir($views, 0777, true);
        file_put_contents($views . '/page.tpl', 'Original');
        $loader = new TemplateLoader(new FileLocator(['' => $views]));

        $compileA = new class implements TemplateCompilerInterface {
            public function compile(string $template, array $functionNames, array $builtinDirectives): string
            {
                return 'First compiler';
            }
        };
        $compileB = new class implements TemplateCompilerInterface {
            public function compile(string $template, array $functionNames, array $builtinDirectives): string
            {
                return 'Second compiler';
            }
        };

        $first = new TemplateEngine($loader, $cache, compiler: $compileA);
        $second = new TemplateEngine($loader, $cache, compiler: $compileB);

        self::assertSame('First compiler', $first->render('page'));
        self::assertSame('Second compiler', $second->render('page'));
    }

}
