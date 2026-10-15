<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateEngineVerbatimTest extends TestCase
{
    private string $dir;

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            TempDir::remove($this->dir);
        }
    }

    public function testVerbatimKeepsCssAtRulesUntouched(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@verbatim
<style>
    @media (min-width: 992px){
        .main { margin-left: 280px; }
    }
</style>
@endverbatim
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('@media (min-width: 992px)', $out);
        self::assertStringContainsString('.main { margin-left: 280px; }', $out);
    }

    public function testVerbatimPreventsInterpolationAndFunctionCompilationInsideBlock(): void
    {
        $engine = $this->makeEngine();
        $engine->addFunction('js', fn(string $path): string => '<script src="' . $path . '"></script>');

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@verbatim
<div>{{ $title }}</div>
@js('/assets/app.js')
@endverbatim
TPL
        );

        $out = $engine->render('page', ['title' => 'Hello']);

        self::assertStringContainsString('{{ $title }}', $out);
        self::assertStringContainsString("@js('/assets/app.js')", $out);
        self::assertStringNotContainsString('<script src="/assets/app.js"></script>', $out);
    }

    public function testCompilationStillWorksOutsideVerbatim(): void
    {
        $engine = $this->makeEngine();
        $engine->addFunction('route', fn(string $name): string => '/generated/' . $name);

        file_put_contents($this->dir . '/views/components/ui.tpl', <<<'TPL'
@component button($text)
<button>{{ $text }}</button>
@endcomponent
TPL
        );

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@verbatim
<style>
    @media (min-width: 992px){
        .main { margin-left: 280px; }
    }
</style>
@endverbatim

@use('components.ui', 'ui')

<a href="@route('users.list')">Users</a>
@ui.button($label)
TPL
        );

        $out = $engine->render('page', ['label' => 'Save']);

        self::assertStringContainsString('@media (min-width: 992px)', $out);
        self::assertStringContainsString('href="/generated/users.list"', $out);
        self::assertStringContainsString('<button>Save</button>', $out);
    }

    private function makeEngine(): TemplateEngine
    {
        $this->dir = TempDir::create('tpl_verbatim_');
        @mkdir($this->dir . '/views/components', 0777, true);
        @mkdir($this->dir . '/cache', 0777, true);

        return new TemplateEngine(
            new TemplateLoader(new FileLocator(['' => $this->dir . '/views'])),
            $this->dir . '/cache'
        );
    }
}