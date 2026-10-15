<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateEngineControlDirectivesTest extends TestCase
{
    private string $dir;

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            TempDir::remove($this->dir);
        }
    }

    public function testIfWithNestedParenthesesCompilesAndRenders(): void
    {
        $this->dir = TempDir::create();
        $views = $this->dir . '/views';
        $cache = $this->dir . '/cache';

        @mkdir($views . '/layout', 0777, true);
        @mkdir($cache, 0777, true);

        file_put_contents($views . '/layout/default.tpl', "<html><body>\n@yield('content')\n</body></html>");

        file_put_contents($views . '/page.tpl', <<<'TPL'
@extends('layout.default')

@section('content')
@if(isset($title))
<h1>{{ $title }}</h1>ff
@endif
@endsection
TPL
        );

        $engine = new TemplateEngine(new TemplateLoader(new FileLocator(['' => $views])), $cache);

        $out = $engine->render('page', ['title' => 'Hello']);
        self::assertStringContainsString('<h1>Hello</h1>ff', $out);

        $out2 = $engine->render('page', []);
        self::assertStringNotContainsString('<h1>', $out2);
    }

    public function testIfInlineSyntaxCompilesAndRenders(): void
    {
        $this->dir = TempDir::create();
        $views = $this->dir . '/views';
        $cache = $this->dir . '/cache';

        @mkdir($views . '/layout', 0777, true);
        @mkdir($cache, 0777, true);

        file_put_contents($views . '/layout/default.tpl', "@yield('content')");

        file_put_contents($views . '/page.tpl', <<<'TPL'
@extends('layout.default')

@section('content')
@if isset($title)
<h1>{{ $title }}</h1>
@endif
@endsection
TPL
        );

        $engine = new TemplateEngine(new TemplateLoader(new FileLocator(['' => $views])), $cache);

        $out = $engine->render('page', ['title' => 'X']);
        self::assertStringContainsString('<h1>X</h1>', $out);

        $out2 = $engine->render('page', []);
        self::assertStringNotContainsString('<h1>', $out2);
    }
}
