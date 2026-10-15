<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateExtensionInterface;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateEngineExtensionsTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = TempDir::create('tpl_ext_');
        @mkdir($this->tmp . '/views', 0777, true);
        @mkdir($this->tmp . '/cache', 0777, true);

        // A minimal view to keep the loader happy if needed.
        file_put_contents($this->tmp . '/views/home.tpl', 'HOME');
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->tmp);
    }

    public function testAddExtensionRegistersExtension(): void
    {
        $engine = $this->makeEngine();

        $ext = new class implements TemplateExtensionInterface {
            public bool $registered = false;

            public function register(TemplateEngine $engine): void
            {
                $this->registered = true;
                $engine->addFunction('hello', fn(string $name) => 'Hello ' . $name);
            }
        };

        $engine->addExtension($ext);

        $this->assertTrue($ext->registered);
        $this->assertSame('Hello Simon', $engine->callFunction('hello', ['Simon']));
    }

    public function testAddExtensionsRegistersMany(): void
    {
        $engine = $this->makeEngine();

        $ext1 = new class implements TemplateExtensionInterface {
            public function register(TemplateEngine $engine): void
            {
                $engine->addFunction('a', fn() => 'A');
            }
        };

        $ext2 = new class implements TemplateExtensionInterface {
            public function register(TemplateEngine $engine): void
            {
                $engine->addFunction('b', fn() => 'B');
            }
        };

        $engine->addExtensions([$ext1, $ext2]);

        $this->assertSame('A', $engine->callFunction('a'));
        $this->assertSame('B', $engine->callFunction('b'));
    }

    public function testAddExtensionsThrowsOnInvalidType(): void
    {
        $engine = $this->makeEngine();

        $this->expectException(\TypeError::class);
        $engine->addExtensions([new \stdClass()]);
    }

    private function makeEngine(): TemplateEngine
    {
        $locator = new FileLocator(['' => $this->tmp . '/views']);
        $loader  = new TemplateLoader($locator);

        return new TemplateEngine($loader, $this->tmp . '/cache');
    }
}
