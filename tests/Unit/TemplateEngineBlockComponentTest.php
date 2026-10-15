<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateEngineBlockComponentTest extends TestCase
{
    private string $dir;

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            TempDir::remove($this->dir);
        }
    }

    public function testBlockComponentRendersSlotContent(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/components/ui.tpl', <<<'TPL'
@component card($class = '')
<section class="card {{ $class }}">
    {!! $slot !!}
</section>
@endcomponent
TPL
        );

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@use('components.ui', 'ui')

@ui.card('stats-card menu-card')
    aaa
@endui.card
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('<section class="card stats-card menu-card">', $out);
        self::assertStringContainsString('aaa', $out);
        self::assertStringContainsString('</section>', $out);
    }

    public function testBlockComponentCanContainInlineComponent(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/components/ui.tpl', <<<'TPL'
@component card($class = '')
<section class="card {{ $class }}">
    {!! $slot !!}
</section>
@endcomponent

@component button($label)
<button>{{ $label }}</button>
@endcomponent
TPL
        );

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@use('components.ui', 'ui')

@ui.card('stats-card menu-card')
    @ui.button('Save')
@endui.card
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('<section class="card stats-card menu-card">', $out);
        self::assertStringContainsString('<button>Save</button>', $out);
    }

    public function testNestedBlockComponentsWork(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/components/ui.tpl', <<<'TPL'
@component card($class = '')
<section class="card {{ $class }}">
    {!! $slot !!}
</section>
@endcomponent
TPL
        );

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@use('components.ui', 'ui')

@ui.card('outer')
    OUTER START

    @ui.card('inner')
        INNER
    @endui.card

    OUTER END
@endui.card
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('<section class="card outer">', $out);
        self::assertStringContainsString('<section class="card inner">', $out);
        self::assertStringContainsString('INNER', $out);
        self::assertStringContainsString('OUTER END', $out);
    }

    private function makeEngine(): TemplateEngine
    {
        $this->dir = TempDir::create('tpl_block_components_');
        @mkdir($this->dir . '/views/components', 0777, true);
        @mkdir($this->dir . '/cache', 0777, true);

        return new TemplateEngine(
            new TemplateLoader(new FileLocator(['' => $this->dir . '/views'])),
            $this->dir . '/cache'
        );
    }
}
