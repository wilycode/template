<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateEngineStateDirectivesTest extends TestCase
{
    private string $dir;

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            TempDir::remove($this->dir);
        }
    }

    public function testSetCreatesLocalVariableInCurrentTemplate(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@set('themes', ['aaa', 'bbb'])
<ul>
@foreach($themes as $theme)
  <li>{{ $theme }}</li>
@endforeach
</ul>
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('<li>aaa</li>', $out);
        self::assertStringContainsString('<li>bbb</li>', $out);
    }

    public function testShareMakesValueAvailableToFollowingInclude(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/partials/value.tpl', <<<'TPL'
Shared: {{ $sharedTheme }}
TPL
        );

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@share('sharedTheme', 'ocean')
@include('partials.value')
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('Shared: ocean', $out);
    }

    public function testShareAcceptsNestedExpressionsWithMethodCalls(): void
    {
        $engine = $this->makeEngine();

        @mkdir($this->dir . '/views/layouts', 0777, true);

        file_put_contents($this->dir . '/views/layouts/main.tpl', <<<'TPL'
<title>{{ $pageTitle }}</title>
@yield('content')
TPL
        );

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@extends('layouts.main')
@share('pageTitle', $article ? ($article->getTitle() ?? 'Article') : 'Article not found')
@section('content')
<p>Page</p>
@endsection
TPL
        );

        $article = new class {
            public function getTitle(): ?string
            {
                return 'Nested title';
            }
        };

        $out = $engine->render('page', ['article' => $article]);

        self::assertStringContainsString('Nested title', $out);
    }

    public function testSetValueIsAvailableToFollowingIncludeThroughCurrentScope(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/partials/value.tpl', <<<'TPL'
Local: {{ $localTheme ?? 'missing' }}
TPL
        );

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@set('localTheme', 'forest')
@include('partials.value')
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('Local: forest', $out);
    }


    public function testShareMakesValueAvailableToParentLayoutDuringExtends(): void
    {
        $engine = $this->makeEngine();

        @mkdir($this->dir . '/views/layouts', 0777, true);

        file_put_contents($this->dir . '/views/layouts/main.tpl', <<<'TPL'
<html>
<body>
<header>{{ $groupKey ?? 'missing' }} / {{ $sidebarKey ?? 'missing' }}</header>
@yield('content')
</body>
</html>
TPL
        );

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@extends('layouts.main')

@share('groupKey', 'admin.taxonomy')
@share('sidebarKey', 'admin.categories')

@section('content')
<p>Page</p>
@endsection
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('admin.taxonomy / admin.categories', $out);
        self::assertStringContainsString('<p>Page</p>', $out);
    }

    public function testShareReplacesExistingSharedValueInParentLayout(): void
    {
        $engine = $this->makeEngine();
        $engine->share('pageTitle', 'Default title');
        file_put_contents($this->dir . '/views/layout.tpl', '<title>{{ $pageTitle }}</title>');
        file_put_contents($this->dir . '/views/page.tpl', "@extends('layout')@share('pageTitle', 'Page title')");
        self::assertSame('<title>Page title</title>', $engine->render('page'));
    }

    public function testParentKeepsExplicitRenderDataAheadOfSharedValues(): void
    {
        $engine = $this->makeEngine();
        file_put_contents($this->dir . '/views/layout.tpl', '<title>{{ $pageTitle }}</title>');
        file_put_contents($this->dir . '/views/page.tpl', "@extends('layout')@share('pageTitle', 'Shared title')");
        self::assertSame('<title>Explicit title</title>', $engine->render('page', ['pageTitle' => 'Explicit title']));
    }

    public function testChildLocalVariableDoesNotReplaceParentRenderData(): void
    {
        $engine = $this->makeEngine();
        file_put_contents($this->dir . '/views/layout.tpl', '<title>{{ $pageTitle }}</title>');
        file_put_contents($this->dir . '/views/page.tpl', "@extends('layout')@set('pageTitle', 'Local title')");
        self::assertSame('<title>Explicit title</title>', $engine->render('page', ['pageTitle' => 'Explicit title']));
    }

    private function makeEngine(): TemplateEngine
    {
        $this->dir = TempDir::create('tpl_state_directives_');
        @mkdir($this->dir . '/views/partials', 0777, true);
        @mkdir($this->dir . '/cache', 0777, true);

        return new TemplateEngine(
            new TemplateLoader(new FileLocator(['' => $this->dir . '/views'])),
            $this->dir . '/cache'
        );
    }
}
