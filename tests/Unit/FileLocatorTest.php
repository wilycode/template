<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WilyCode\Template\FileLocator;
use WilyCode\Template\Tests\Support\TempDir;

final class FileLocatorTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = TempDir::create('tpl_locator_');

        @mkdir($this->tmp . '/views', 0777, true);
        @mkdir($this->tmp . '/admin', 0777, true);

        file_put_contents($this->tmp . '/views/home.tpl', 'HOME');
        @mkdir($this->tmp . '/views/pages', 0777, true);
        file_put_contents($this->tmp . '/views/pages/about.tpl', 'ABOUT');

        file_put_contents($this->tmp . '/admin/dashboard.tpl', 'ADMIN');
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->tmp);
    }

    public function testLocateMainNamespaceWithDotNotation(): void
    {
        $locator = new FileLocator(['' => $this->tmp . '/views']);

        $path = $locator->locate('pages.about');
        $this->assertSame($this->tmp . '/views/pages/about.tpl', $path);
    }

    public function testLocateExplicitNamespace(): void
    {
        $locator = new FileLocator([
            '' => $this->tmp . '/views',
            'admin' => $this->tmp . '/admin',
        ]);

        $path = $locator->locate('admin::dashboard');
        $this->assertSame($this->tmp . '/admin/dashboard.tpl', $path);
    }

    public function testUnregisteredNamespaceThrows(): void
    {
        $locator = new FileLocator(['' => $this->tmp . '/views']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("View namespace 'admin' is not registered.");

        $locator->locate('admin::dashboard');
    }

    public function testMissingViewThrows(): void
    {
        $locator = new FileLocator(['' => $this->tmp . '/views']);

        $this->expectException(RuntimeException::class);
        $locator->locate('missing');
    }
}
