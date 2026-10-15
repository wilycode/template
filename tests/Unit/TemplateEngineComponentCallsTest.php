<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateEngineComponentCallsTest extends TestCase
{
    private string $dir;

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            TempDir::remove($this->dir);
        }
    }

    public function testComponentAliasSyntaxRendersComponentOutput(): void
    {
        $engine = $this->makeEngine();

        file_put_contents($this->dir . '/views/components/ui.tpl', <<<'TPL'
@component button($text)
<button>{{ $text }}</button>
@endcomponent

@component spacer()
<hr>
@endcomponent
TPL
        );

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@use('components.ui', 'ui')
@ui.button(strtoupper($label))
@ui.spacer()
TPL
        );

        $out = $engine->render('page', ['label' => 'ok']);

        self::assertStringContainsString('<button>OK</button>', $out);
        self::assertStringContainsString('<hr>', $out);
    }

    public function testComponentAliasSyntaxDoesNotConflictWithFunctions(): void
    {
        $engine = $this->makeEngine();
        $engine->addFunction('js', fn(string $path): string => '<script src="' . $path . '"></script>');

        file_put_contents($this->dir . '/views/components/ui.tpl', <<<'TPL'
@component button($text)
<button>{{ $text }}</button>
@endcomponent
TPL
        );

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@use('components.ui', 'ui')
@js('/assets/app.js')
@ui.button($label)
TPL
        );

        $out = $engine->render('page', ['label' => 'Save']);

        self::assertStringContainsString('<script src="/assets/app.js"></script>', $out);
        self::assertStringContainsString('<button>Save</button>', $out);
    }

    private function makeEngine(): TemplateEngine
    {
        $this->dir = TempDir::create('tpl_components_');
        @mkdir($this->dir . '/views/components', 0777, true);
        @mkdir($this->dir . '/cache', 0777, true);

        return new TemplateEngine(
            new TemplateLoader(new FileLocator(['' => $this->dir . '/views'])),
            $this->dir . '/cache'
        );
    }
}
