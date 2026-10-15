<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateEngineNamedComponentArgumentsTest extends TestCase
{
    private string $dir;

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            TempDir::remove($this->dir);
        }
    }

    public function testBlockComponentUsesDefaultParametersWhenArgumentsAreOmitted(): void
    {
        $engine = $this->makeEngine();
        $this->writeCardComponent();

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@use('components.ui', 'ui')

@ui.card()
    body
@endui.card
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('<section class="card">', $out);
        self::assertStringContainsString('body', $out);
        self::assertStringNotContainsString('card-title', $out);
    }

    public function testInlineComponentSupportsNamedArguments(): void
    {
        $engine = $this->makeEngine();
        $this->writeBadgeComponent();

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@use('components.ui', 'ui')
@ui.badge(variant: 'success', text: 'Ready')
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('<span class="badge badge-success">Ready</span>', $out);
    }

    public function testBlockComponentSupportsNamedArguments(): void
    {
        $engine = $this->makeEngine();
        $this->writeCardComponent();

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@use('components.ui', 'ui')

@ui.card(title: 'Menu details', subtitle: 'Basic metadata and page position')
    body
@endui.card
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('Menu details', $out);
        self::assertStringContainsString('Basic metadata and page position', $out);
        self::assertStringContainsString('body', $out);
    }

    public function testBlockComponentSupportsMixedPositionalAndNamedArguments(): void
    {
        $engine = $this->makeEngine();
        $this->writeCardComponent();

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@use('components.ui', 'ui')

@ui.card('Menu details', subtitle: 'Basic metadata')
    body
@endui.card
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('Menu details', $out);
        self::assertStringContainsString('Basic metadata', $out);
    }

    public function testCompilerRejectsPositionalArgumentsAfterNamedArguments(): void
    {
        $engine = $this->makeEngine();
        $this->writeCardComponent();

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@use('components.ui', 'ui')
@ui.card(title: 'Menu details', 'Body')
TPL
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Positional component argument cannot appear after a named argument');

        $engine->render('page');
    }

    public function testComponentDefaultCanContainNestedFunctionCall(): void
    {
        $engine = $this->makeEngine();
        file_put_contents($this->dir . '/views/components/ui.tpl', <<<'TPL'
@component greeting($label = strtoupper('hello'))
<span>{{ $label }}</span>
@endcomponent
TPL
        );
        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@use('components.ui', 'ui')
@ui.greeting()
TPL
        );

        self::assertStringContainsString('<span>HELLO</span>', $engine->render('page'));
    }

    public function testComponentDefaultDoesNotOverrideExplicitNull(): void
    {
        $engine = $this->makeEngine();
        file_put_contents($this->dir . '/views/components/ui.tpl', <<<'TPL'
@component greeting($label = strtoupper('hello'))
<span>{{ $label }}</span>
@endcomponent
TPL
        );
        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@use('components.ui', 'ui')
@ui.greeting(label: null)
TPL
        );

        self::assertStringContainsString('<span></span>', $engine->render('page'));
        self::assertStringNotContainsString('HELLO', $engine->render('page'));
    }

    private function makeEngine(): TemplateEngine
    {
        $this->dir = TempDir::create('tpl_named_components_');
        @mkdir($this->dir . '/views/components', 0777, true);
        @mkdir($this->dir . '/cache', 0777, true);

        return new TemplateEngine(
            new TemplateLoader(new FileLocator(['' => $this->dir . '/views'])),
            $this->dir . '/cache'
        );
    }

    private function writeCardComponent(): void
    {
        file_put_contents($this->dir . '/views/components/ui.tpl', <<<'TPL'
@component card($title = null, $subtitle = null)
<section class="card">
    @if $title != null
    <div class="card-header">
        <h2 class="card-title">{{ $title }}</h2>
        @if $subtitle != null
        <p class="card-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @endif
    {!! $slot !!}
</section>
@endcomponent
TPL
        );
    }

    private function writeBadgeComponent(): void
    {
        file_put_contents($this->dir . '/views/components/ui.tpl', <<<'TPL'
@component badge($text, $variant = 'default')
<span class="badge badge-{{ $variant }}">{{ $text }}</span>
@endcomponent
TPL
        );
    }
}
