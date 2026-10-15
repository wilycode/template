<?php

declare(strict_types=1);

namespace WilyCode\Template\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\Tests\Support\TempDir;

final class TemplateEngineCacheBehaviorTest extends TestCase
{
    private string $dir;

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            TempDir::remove($this->dir);
        }
    }

    public function testAutoReloadRecompilesTemplateWhenSourceChanges(): void
    {
        [$views, $cache] = $this->createDirs();

        $template = $views . '/page.tpl';
        file_put_contents($template, 'Version A');

        $engine = $this->createEngine($views, $cache, true);

        self::assertSame('Version A', trim($engine->render('page')));

        $cacheFile = $this->getSingleCacheFile($cache);
        $firstCompiled = file_get_contents($cacheFile);
        self::assertIsString($firstCompiled);
        self::assertStringContainsString('Version A', $firstCompiled);

        file_put_contents($template, 'Version B');
        touch($template, time() + 5);

        self::assertSame('Version B', trim($engine->render('page')));

        // Each revision receives an immutable cache path. Old compiled PHP is
        // never overwritten while another request may be executing it.
        $revisions = glob($cache . '/*.php') ?: [];
        self::assertCount(2, $revisions);
        $latestCache = $revisions[0] === $cacheFile ? $revisions[1] : $revisions[0];
        $secondCompiled = file_get_contents($latestCache);
        self::assertIsString($secondCompiled);
        self::assertStringContainsString('Version B', $secondCompiled);
        self::assertStringContainsString('Version A', (string) file_get_contents($cacheFile));
    }

    public function testAutoReloadRecompilesTemplateWhenCopiedSourceHasOlderMtimeThanCache(): void
    {
        [$views, $cache] = $this->createDirs();

        $template = $views . '/page.tpl';
        file_put_contents($template, 'Version A');

        $engine = $this->createEngine($views, $cache, true);

        self::assertSame('Version A', trim($engine->render('page')));

        $cacheFile = $this->getSingleCacheFile($cache);
        $cacheMtime = filemtime($cacheFile);
        self::assertIsInt($cacheMtime);

        file_put_contents($template, 'Version copied from archive');
        touch($template, $cacheMtime - 3600);
        clearstatcache(true, $template);

        self::assertSame('Version copied from archive', trim($engine->render('page')));
    }

    public function testDisabledAutoReloadKeepsCompiledTemplateUntilCacheIsCleared(): void
    {
        [$views, $cache] = $this->createDirs();

        $template = $views . '/page.tpl';
        file_put_contents($template, 'Version A');

        $engine = $this->createEngine($views, $cache, false);

        self::assertSame('Version A', trim($engine->render('page')));

        file_put_contents($template, 'Version B');
        touch($template, time() + 5);

        self::assertSame('Version A', trim($engine->render('page')));
    }

    public function testClearCacheRemovesCompiledFiles(): void
    {
        [$views, $cache] = $this->createDirs();

        file_put_contents($views . '/page.tpl', 'Hello');
        file_put_contents($views . '/other.tpl', 'World');

        $engine = $this->createEngine($views, $cache, true);
        $engine->render('page');
        $engine->render('other');

        self::assertCount(2, glob($cache . '/*.php') ?: []);

        $engine->clearCache();

        self::assertCount(0, glob($cache . '/*.php') ?: []);
        self::assertCount(0, glob($cache . '/*.php.sha256') ?: []);
    }

    public function testAutoReloadCanBeChangedAtRuntime(): void
    {
        [$views, $cache] = $this->createDirs();

        $template = $views . '/page.tpl';
        file_put_contents($template, 'Version A');

        $engine = $this->createEngine($views, $cache, false);

        self::assertFalse($engine->isAutoReload());
        self::assertSame('Version A', trim($engine->render('page')));

        file_put_contents($template, 'Version B');
        touch($template, time() + 5);

        self::assertSame('Version A', trim($engine->render('page')));

        $engine->setAutoReload(true);

        self::assertTrue($engine->isAutoReload());
        self::assertSame('Version B', trim($engine->render('page')));
    }

    public function testRegisteringAFunctionAfterFirstRenderUsesNewCompilation(): void
    {
        [$views, $cache] = $this->createDirs();
        file_put_contents($views . '/page.tpl', '@greet("Ada")');
        $engine = $this->createEngine($views, $cache, true);

        self::assertSame('@greet("Ada")', $engine->render('page'));
        $engine->addFunction('greet', static fn (string $name): string => 'Hello ' . $name);

        self::assertSame('Hello Ada', $engine->render('page'));
    }

    public function testSharingCacheBetweenDifferentFunctionRegistriesIsSafe(): void
    {
        [$views, $cache] = $this->createDirs();
        file_put_contents($views . '/page.tpl', '@greet("Ada")');
        $withFunction = $this->createEngine($views, $cache, true);
        $withoutFunction = $this->createEngine($views, $cache, true);
        $withFunction->addFunction('greet', static fn (string $name): string => 'Hello ' . $name);

        self::assertSame('Hello Ada', $withFunction->render('page'));
        self::assertSame('@greet("Ada")', $withoutFunction->render('page'));
        self::assertSame('Hello Ada', $withFunction->render('page'));
    }

    public function testCacheChangesWhenNewDirectiveIsReserved(): void
    {
        [$views, $cache] = $this->createDirs();
        file_put_contents($views . '/page.tpl', '@greet("Ada")');
        $engine = $this->createEngine($views, $cache, true);
        $engine->addFunction('greet', static fn (string $name): string => 'Hello ' . $name);
        self::assertSame('Hello Ada', $engine->render('page'));

        $engine->reserveDirective('greet');
        self::assertSame('@greet("Ada")', $engine->render('page'));
    }

    public function testCacheWritesLeaveNoTemporaryFiles(): void
    {
        [$views, $cache] = $this->createDirs();
        file_put_contents($views . '/page.tpl', 'Hello');
        $engine = $this->createEngine($views, $cache, true);
        self::assertSame('Hello', $engine->render('page'));
        self::assertSame([], glob($cache . '/.wily-tmp-*') ?: []);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function createDirs(): array
    {
        $this->dir = TempDir::create();
        $views = $this->dir . '/views';
        $cache = $this->dir . '/cache';

        @mkdir($views, 0777, true);
        @mkdir($cache, 0777, true);

        return [$views, $cache];
    }

    private function createEngine(string $views, string $cache, bool $autoReload): TemplateEngine
    {
        return new TemplateEngine(
            new TemplateLoader(new FileLocator(['' => $views])),
            $cache,
            $autoReload
        );
    }

    private function getSingleCacheFile(string $cache): string
    {
        $files = glob($cache . '/*.php') ?: [];
        self::assertCount(1, $files);

        return $files[0];
    }
}
