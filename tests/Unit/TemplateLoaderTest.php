<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateLoaderTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = TempDir::create('tpl_loader_');
        @mkdir($this->tmp . '/views', 0777, true);
        @mkdir($this->tmp . '/views/pages', 0777, true);

        file_put_contents($this->tmp . '/views/pages/about.tpl', 'ABOUT');
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->tmp);
    }

    public function testFindReturnsPathFromLocator(): void
    {
        $locator = new FileLocator(['' => $this->tmp . '/views']);
        $loader = new TemplateLoader($locator);

        $path = $loader->find('pages.about');

        $this->assertSame($this->tmp . '/views/pages/about.tpl', $path);
    }

    public function testGetSourceReturnsFileContents(): void
    {
        $locator = new FileLocator(['' => $this->tmp . '/views']);
        $loader = new TemplateLoader($locator);

        $this->assertSame('ABOUT', $loader->getSource('pages.about'));
    }

    public function testGetLastModifiedReturnsMtime(): void
    {
        $locator = new FileLocator(['' => $this->tmp . '/views']);
        $loader = new TemplateLoader($locator);

        $mtime = $loader->getLastModified('pages.about');

        $this->assertIsInt($mtime);
        $this->assertGreaterThan(0, $mtime);
    }

    public function testGetSourceThrowsWhenViewIsMissing(): void
    {
        $locator = new FileLocator(['' => $this->tmp . '/views']);
        $loader = new TemplateLoader($locator);

        $this->expectException(RuntimeException::class);
        $loader->getSource('missing');
    }
}
