<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateEngineSectionsTest extends TestCase
{
    private string $dir;

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            TempDir::remove($this->dir);
        }
    }

    public function testHasSectionReturnsTrueForExistingSectionWithContent(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@section('page_actions')
    <button>Save</button>
@endsection
TPL
        );

        $engine->render('page');

        self::assertTrue($engine->hasSection('page_actions'));
    }

    public function testHasSectionReturnsFalseForMissingSection(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/page.tpl', 'Hello');

        $engine->render('page');

        self::assertFalse($engine->hasSection('page_actions'));
    }

    public function testHasSectionReturnsFalseForWhitespaceOnlySectionByDefault(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@section('page_actions')
    
@endsection
TPL
        );

        $engine->render('page');

        self::assertFalse($engine->hasSection('page_actions'));
    }

    public function testHasSectionCanTreatWhitespaceOnlySectionAsExisting(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@section('page_actions')
    
@endsection
TPL
        );

        $engine->render('page');

        self::assertTrue($engine->hasSection('page_actions', false));
    }

    public function testHasSectionDirectiveRendersBlockOnlyWhenSectionExists(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/layouts/main.tpl', <<<'TPL'
<html>
<body>
@hasSection('page_actions')
<div class="page-actions">
    @yield('page_actions')
</div>
@endhasSection

@yield('content')
</body>
</html>
TPL
        );

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@extends('layouts.main')

@section('page_actions')
    <button>Save article</button>
@endsection

@section('content')
    <p>Content</p>
@endsection
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('<div class="page-actions">', $out);
        self::assertStringContainsString('<button>Save article</button>', $out);
        self::assertStringContainsString('<p>Content</p>', $out);
    }

    public function testHasSectionDirectiveSkipsBlockWhenSectionIsMissing(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/layouts/main.tpl', <<<'TPL'
<html>
<body>
@hasSection('page_actions')
<div class="page-actions">
    @yield('page_actions')
</div>
@endhasSection

@yield('content')
</body>
</html>
TPL
        );

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@extends('layouts.main')

@section('content')
    <p>Content</p>
@endsection
TPL
        );

        $out = $engine->render('page');

        self::assertStringNotContainsString('<div class="page-actions">', $out);
        self::assertStringContainsString('<p>Content</p>', $out);
    }

    public function testNestedLayoutsRenderAllLevelsAndPreserveSections(): void
    {
        $engine = $this->makeEngine();
        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@extends('layouts.middle')
@section('content')Child@endsection
TPL
        );
        file_put_contents($this->dir . '/views/layouts/middle.tpl', <<<'TPL'
@extends('layouts.outer')
@section('inner')Middle(@yield('content'))@endsection
TPL
        );
        file_put_contents($this->dir . '/views/layouts/outer.tpl', "Outer(@yield('inner'))");

        self::assertSame('Outer(Middle(Child))', trim($engine->render('page')));
    }

    public function testNestedLayoutsPropagateIntermediateMarkupToOuterLayout(): void
    {
        $engine = $this->makeEngine();
        file_put_contents($this->dir . '/views/page.tpl', "@extends('layouts.middle')@section('content')CHILD@endsection");
        file_put_contents($this->dir . '/views/layouts/middle.tpl', "@extends('layouts.outer')MIDDLE(@yield('content'))");
        file_put_contents($this->dir . '/views/layouts/outer.tpl', "OUTER(@yield('content'))");

        self::assertSame('OUTER(MIDDLE(CHILD))', $engine->render('page'));
    }

    public function testLayoutCyclesAreRejectedAndDoNotPoisonNextRender(): void
    {
        $engine = $this->makeEngine();
        file_put_contents($this->dir . '/views/first.tpl', "@extends('second')");
        file_put_contents($this->dir . '/views/second.tpl', "@extends('first')");
        file_put_contents($this->dir . '/views/ok.tpl', 'OK');

        try {
            $engine->render('first');
            self::fail('Expected a layout cycle exception.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Circular template layout inheritance', $exception->getMessage());
        }

        self::assertSame('OK', $engine->render('ok'));
    }

    public function testLayoutExpressionCanContainNestedParentheses(): void
    {
        $engine = $this->makeEngine();
        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@extends(strtolower('LAYOUTS.MAIN'))
@section('content')Child@endsection
TPL
        );
        file_put_contents($this->dir . '/views/layouts/main.tpl', "Outer(@yield('content'))");

        self::assertSame('Outer(Child)', trim($engine->render('page')));
    }

    private function makeEngine(): TemplateEngine
    {
        $this->dir = TempDir::create('tpl_sections_');
        @mkdir($this->dir . '/views/layouts', 0777, true);
        @mkdir($this->dir . '/cache', 0777, true);

        return new TemplateEngine(
            new TemplateLoader(new FileLocator(['' => $this->dir . '/views'])),
            $this->dir . '/cache'
        );
    }
}
