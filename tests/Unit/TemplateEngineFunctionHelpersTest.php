<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateEngineFunctionHelpersTest extends TestCase
{
    private string $dir;

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            TempDir::remove($this->dir);
        }
    }

    public function testNamedFunctionCompilationKeepsBuiltInDirectivesIntact(): void
    {
        $engine = $this->makeEngine();
        $engine->addFunction('route', fn(string $name, array $query = []): string => '/generated/' . $name . '?' . http_build_query($query));

        file_put_contents($this->dir . '/views/layout.tpl', '<html>@yield(\'content\')</html>');
        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
@extends('layout')
@section('content')
@route(name: 'article.list', query: ['aa' => 'ww'])
@endsection
TPL
        );

        $out = $engine->render('page');

        self::assertSame('<html>/generated/article.list?aa=ww</html>', trim($out));
    }

    public function testFunHelperCanCallTemplateFunctionsInsideExpressions(): void
    {
        $engine = $this->makeEngine();
        $engine->addFunction('route', fn(string $name, array $params = [], array $query = []): string => '/generated/' . $name . '?' . http_build_query(['params' => $params, 'query' => $query]));

        file_put_contents($this->dir . '/views/page.tpl', <<<'TPL'
<?php $url = $fun('route', 'article.list', ['id' => 5], query: ['aa' => 'ww']); ?>
{{ $url }}
TPL
        );

        $out = $engine->render('page');

        self::assertStringContainsString('/generated/article.list?', $out);
        self::assertStringContainsString('params%5Bid%5D=5', $out);
        self::assertStringContainsString('query%5Baa%5D=ww', $out);
    }

    private function makeEngine(): TemplateEngine
    {
        $this->dir = TempDir::create('tpl_function_helpers_');
        @mkdir($this->dir . '/views', 0777, true);
        @mkdir($this->dir . '/cache', 0777, true);

        return new TemplateEngine(
            new TemplateLoader(new FileLocator(['' => $this->dir . '/views'])),
            $this->dir . '/cache'
        );
    }
}
