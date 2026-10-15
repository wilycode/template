<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateEngineContextAndCommentsTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = TempDir::create('tpl_context_');
        mkdir($this->tmp . '/views/article', 0777, true);
        mkdir($this->tmp . '/cache', 0777, true);
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->tmp);
    }

    public function testHashCommentsAreRemovedFromCompiledOutput(): void
    {
        file_put_contents(
            $this->tmp . '/views/page.tpl',
            "before{# single line #}middle{#\nmultiline\ncomment\n#}after"
        );

        self::assertSame('beforemiddleafter', $this->makeEngine()->render('page'));
    }

    public function testRootTemplateContextIsKeptForLayoutAndInclude(): void
    {
        file_put_contents(
            $this->tmp . '/views/layout.tpl',
            "layout={{ '' | template_name }};@yield('content')"
        );
        file_put_contents(
            $this->tmp . '/views/partial.tpl',
            "partial={{ '' | template_name }};"
        );
        file_put_contents(
            $this->tmp . '/views/article/form.tpl',
            "@extends('layout')\n@section('content')\n"
            . "child={{ '' | template_name }};@include('partial')\n"
            . "@endsection"
        );

        $engine = $this->makeEngine();
        $engine->addFilter(
            'template_name',
            static fn (mixed $value): string => $engine->getCurrentTemplateName() ?? 'none'
        );

        $output = $engine->render('article.form');

        self::assertStringContainsString('layout=article.form;', $output);
        self::assertStringContainsString('child=article.form;', $output);
        self::assertStringContainsString('partial=article.form;', $output);
        self::assertNull($engine->getCurrentTemplateName());
    }

    public function testFailedPartialCleansNestedBuffersAndAllowsAnotherRender(): void
    {
        file_put_contents($this->tmp . '/views/page.tpl', "prefix@include('partial')");
        file_put_contents($this->tmp . '/views/partial.tpl', "@section('content')partial{{ '' | fail }}@endsection");
        file_put_contents($this->tmp . '/views/ok.tpl', 'OK');
        $engine = $this->makeEngine();
        $engine->addFilter('fail', static function (mixed $value): never {
            throw new \RuntimeException('render failed');
        });

        $level = ob_get_level();
        ob_start();
        try {
            echo 'caller';
            try {
                $engine->render('page');
                self::fail('Expected render failure.');
            } catch (\RuntimeException $exception) {
                self::assertSame('render failed', $exception->getMessage());
            }
            self::assertSame($level + 1, ob_get_level());
            self::assertSame('caller', ob_get_contents());
            self::assertNull($engine->getCurrentTemplateName());
            self::assertSame('OK', $engine->render('ok'));
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    public function testFailedLayoutCleansItsBuffer(): void
    {
        file_put_contents($this->tmp . '/views/page.tpl', "@extends('layout')child");
        file_put_contents($this->tmp . '/views/layout.tpl', "layout{{ '' | fail }}");
        $engine = $this->makeEngine();
        $engine->addFilter('fail', static function (mixed $value): never {
            throw new \RuntimeException('layout failed');
        });
        $level = ob_get_level();
        try {
            $engine->render('page');
            self::fail('Expected layout failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('layout failed', $exception->getMessage());
            self::assertSame($level, ob_get_level());
            self::assertNull($engine->getCurrentTemplateName());
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    private function makeEngine(): TemplateEngine
    {
        return new TemplateEngine(
            new TemplateLoader(new FileLocator(['' => $this->tmp . '/views'])),
            $this->tmp . '/cache',
        );
    }
}
