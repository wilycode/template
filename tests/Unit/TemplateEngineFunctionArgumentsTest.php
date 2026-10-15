<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateEngineFunctionArgumentsTest extends TestCase
{
    private string $dir;

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            TempDir::remove($this->dir);
        }
    }

    public function testFunctionSupportsPositionalAndNamedArguments(): void
    {
        $engine = $this->makeEngine();
        $engine->addFunction('route', fn(string $name, array $query = []): string => '/generated/' . $name . '?' . http_build_query($query));

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
<a href="@route('article.list', query: ['aa' => 'ww'])">Articles</a>
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('href="/generated/article.list?aa=ww"', $out);
    }

    public function testFunctionSupportsFullyNamedArgumentsIncludingName(): void
    {
        $engine = $this->makeEngine();
        $engine->addFunction('route', fn(string $name, array $query = []): string => '/generated/' . $name . '?' . http_build_query($query));

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
<a href="@route(name: 'article.list', query: ['aa' => 'ww'])">Articles</a>
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('href="/generated/article.list?aa=ww"', $out);
    }

    public function testCompilerRejectsPositionalFunctionArgumentsAfterNamedArguments(): void
    {
        $engine = $this->makeEngine();
        $engine->addFunction('route', fn(string $name, array $query = []): string => '/generated/' . $name . '?' . http_build_query($query));

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@route(query: ['aa' => 'ww'], 'article.list')
TPL
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Positional function argument cannot appear after a named argument');

        $engine->render('page');
    }

    private function makeEngine(): TemplateEngine
    {
        $this->dir = TempDir::create('tpl_function_arguments_');
        @mkdir($this->dir . '/views', 0777, true);
        @mkdir($this->dir . '/cache', 0777, true);

        return new TemplateEngine(
            new TemplateLoader(new FileLocator(['' => $this->dir . '/views'])),
            $this->dir . '/cache'
        );
    }
}
