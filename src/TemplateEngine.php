<?php

declare(strict_types=1);

namespace WilyCode\Template;

use InvalidArgumentException;
use RuntimeException;

class TemplateEngine
{
    private TemplateLoader $loader;
    private string $cachePath;
    private bool $autoReload;
    private TemplateCompilerInterface $compiler;
    private string $compilerCacheNamespace;

    private int $renderDepth = 0;
    private ?string $currentTemplateName = null;

    /** @var array<string, mixed> */
    private array $shared = [];

    /** @var array<string, callable> */
    private array $components = [];
    private ?string $componentAlias = null;
    /** @var list<array{name:string,positional:list<mixed>,named:array<string,mixed>}> */
    private array $componentBlockStack = [];

    private ?string $parentTemplate = null;
    /** @var array<string, string> */
    private array $sections = [];
    /** @var list<string> */
    private array $sectionStack = [];

    /** @var array<string, list<string>> */
    private array $stacks = [];
    /** @var list<string> */
    private array $stackStack = [];

    /** @var array<string, callable> */
    private array $functions = [];

    /** @var array<string, callable> */
    private array $filters = [];

    /** @var list<string> */
    private array $builtinDirectives = [
        'if',
        'elseif',
        'else',
        'endif',
        'foreach',
        'endforeach',
        'extends',
        'section',
        'endsection',
        'yield',
        'hasSection',
        'endhasSection',
        'include',
        'use',
        'component',
        'endcomponent',
        'push',
        'endpush',
        'stack',
        'set',
        'share',
        'verbatim',
        'endverbatim',
    ];

    public function __construct(
        TemplateLoader $loader,
        string $cachePath,
        bool $autoReload = true,
        ?TemplateCompilerInterface $compiler = null,
    ) {
        $this->loader = $loader;
        $this->cachePath = rtrim($cachePath, DIRECTORY_SEPARATOR);
        $this->autoReload = $autoReload;
        $this->compiler = $compiler ?? new TemplateCompiler();
        // A custom compiler may depend on arbitrary runtime configuration.
        // Do not reuse its cached output across unrelated engine instances.
        $this->compilerCacheNamespace = $compiler === null
            ? TemplateCompiler::class
            : 'custom-' . bin2hex(random_bytes(16));
    }

    /**
     * Register a template extension.
     *
     * Extensions can add functions, filters, shared variables and reserve directives.
     */
    public function addExtension(TemplateExtensionInterface $ext): self
    {
        $ext->register($this);

        return $this;
    }

    /**
     * Register multiple template extensions.
     *
     * @param iterable<TemplateExtensionInterface> $exts
     */
    public function addExtensions(iterable $exts): self
    {
        foreach ($exts as $ext) {
            $this->addExtension($ext);
        }

        return $this;
    }

    /**
     * Share a variable across all rendered views, for example $assets, $auth or $url.
     */
    public function share(string $name, mixed $value): void
    {
        $this->shared[$name] = $value;
    }

    public function addFunction(string $name, callable $fn): void
    {
        $this->functions[$name] = $fn;
    }

    /** @param array<int|string, mixed> $args */
    public function callFunction(string $name, array $args = []): mixed
    {
        if (!isset($this->functions[$name])) {
            throw new InvalidArgumentException("Template function '{$name}' is not defined.");
        }

        return call_user_func_array($this->functions[$name], $args);
    }

    public function reserveDirective(string $name): void
    {
        if (!in_array($name, $this->builtinDirectives, true)) {
            $this->builtinDirectives[] = $name;
        }
    }

    public function setAutoReload(bool $autoReload): self
    {
        $this->autoReload = $autoReload;

        return $this;
    }

    public function isAutoReload(): bool
    {
        return $this->autoReload;
    }

    public function clearCache(): void
    {
        if (!is_dir($this->cachePath)) {
            return;
        }

        foreach (['*.php', '*.php.sha256'] as $suffix) {
            $files = glob($this->cachePath . DIRECTORY_SEPARATOR . $suffix);

            if ($files === false) {
                throw new RuntimeException("Cannot read cache directory '{$this->cachePath}'.");
            }

            foreach ($files as $file) {
                if (is_file($file) && !unlink($file)) {
                    throw new RuntimeException("Cannot remove cache file '{$file}'.");
                }
            }
        }
    }

    private function resetState(): void
    {
        $this->parentTemplate = null;
        $this->sections = [];
        $this->sectionStack = [];
        $this->stacks = [];
        $this->stackStack = [];
        $this->componentBlockStack = [];
    }

    /** @param array<string, mixed> $data */
    public function render(string $name, array $data = []): string
    {
        $isRootRender = $this->renderDepth === 0;

        if ($isRootRender) {
            $this->resetState();
            $this->currentTemplateName = $name;
        }

        $this->renderDepth++;

        try {
            return $this->renderInternal($name, $data, true);
        } finally {
            $this->renderDepth--;

            if ($isRootRender) {
                $this->currentTemplateName = null;
            }
        }
    }

    /**
     * Return the logical name of the root template currently being rendered.
     *
     * Includes, components and parent layouts keep the root template context.
     * Outside render(), no template context is active and null is returned.
     */
    public function getCurrentTemplateName(): ?string
    {
        return $this->currentTemplateName;
    }

    /**
     * Render a template without resetting engine state and without processing @extends.
     * Used internally by @include and @use.
     */
    /** @param array<string, mixed> $data */
    public function renderPartial(string $name, array $data = []): string
    {
        $previousParent = $this->parentTemplate;

        $this->renderDepth++;

        try {
            return $this->renderInternal($name, $data, false);
        } finally {
            $this->parentTemplate = $previousParent;
            $this->renderDepth--;
        }
    }

    /** @param array<string, mixed> $data */
    private function renderInternal(string $name, array $data, bool $processExtends): string
    {
        [, $cacheFile] = $this->getCompiledPath($name);
        $content = $this->evaluateCompiledTemplate($cacheFile, $data);

        if (!$processExtends || $this->parentTemplate === null) {
            return $content;
        }

        if (!isset($this->sections['content'])) {
            $this->sections['content'] = $content;
        }

        // A parent may itself extend another layout. Evaluate each parent in
        // a fresh scope while retaining sections and shared values from children.
        // Track resolved paths so aliases to the same file cannot form a cycle.
        $rootFile = $this->loader->find($name);
        $visited = [(realpath($rootFile) ?: $rootFile) => true];

        while ($this->parentTemplate !== null) {
            $parent = $this->parentTemplate;
            $this->parentTemplate = null;
            $parentFile = $this->loader->find($parent);
            $parentFile = realpath($parentFile) ?: $parentFile;

            if (isset($visited[$parentFile])) {
                throw new RuntimeException("Circular template layout inheritance involving '{$parent}'.");
            }

            $visited[$parentFile] = true;
            [, $parentCacheFile] = $this->getCompiledPath($parent);
            $previousContentSection = $this->sections['content'] ?? null;
            $content = $this->evaluateCompiledTemplate($parentCacheFile, $data, $content);

            // If an intermediate layout supplies markup without defining a new
            // content section, make that rendered markup available to the next
            // parent. Explicit @section('content') remains authoritative.
            if ($this->parentTemplate !== null && ($this->sections['content'] ?? null) === $previousContentSection) {
                $this->sections['content'] = $content;
            }
        }

        return $content;
    }

    /** @param array<string, mixed> $__data */
    private function evaluateCompiledTemplate(string $__templateFile, array $__data, ?string $__childContent = null): string
    {
        $__bufferLevel = ob_get_level();
        $__engine = $this;
        $__templateFunctionCaller = static fn(string $name, ...$args) => $__engine->callFunction($name, $args);
        extract(array_merge($this->shared, ['fun' => $__templateFunctionCaller], $__data), EXTR_SKIP);

        try {
            ob_start();
            include $__templateFile;

            return ob_get_clean();
        } finally {
            // Includes, sections and components may have opened nested buffers.
            while (ob_get_level() > $__bufferLevel) {
                ob_end_clean();
            }
        }
    }

    /**
     * Return both the resolved template and its compiled cache path.
     *
     * Each source revision and compiler configuration gets an immutable
     * cache filename. This prevents a stale function/directive compilation
     * from leaking between engine instances that share a cache directory.
     *
     * @return array{0:string, 1:string}
     */
    protected function getCompiledPath(string $name): array
    {
        $templateFile = $this->loader->find($name);
        $real = realpath($templateFile) ?: $templateFile;

        $functionNames = array_keys($this->functions);
        $directives = $this->builtinDirectives;
        sort($functionNames);
        sort($directives);

        $context = hash('sha256', serialize([
            $this->compilerCacheNamespace,
            $functionNames,
            $directives,
            $this->autoReload,
        ]));
        $source = $this->autoReload ? $this->loader->getSource($name) : null;
        $suffix = $this->autoReload ? hash('sha256', $source) : 'static';
        $cacheFile = $this->cachePath . DIRECTORY_SEPARATOR
            . hash('sha256', $real . "\0" . $context . "\0" . $suffix) . '.php';

        if (!is_file($cacheFile)) {
            $source ??= $this->loader->getSource($name);
            $compiled = $this->compile($source);
            $this->writeCompiledCache($cacheFile, $compiled);
        }

        return [$templateFile, $cacheFile];
    }

    /**
     * Compile to a temporary file before publishing it by atomic rename.
     * Readers never include a partially written PHP cache file.
     */
    private function writeCompiledCache(string $cacheFile, string $compiled): void
    {
        if (!is_dir($this->cachePath) && !mkdir($this->cachePath, 0777, true) && !is_dir($this->cachePath)) {
            throw new RuntimeException("Cannot create cache directory '{$this->cachePath}'.");
        }

        $temporary = tempnam($this->cachePath, '.wily-tmp-');

        if ($temporary === false) {
            throw new RuntimeException("Cannot create temporary template cache in '{$this->cachePath}'.");
        }

        try {
            if (file_put_contents($temporary, $compiled) === false) {
                throw new RuntimeException("Cannot write template cache temporary file '{$temporary}'.");
            }

            // tempnam() defaults to 0600. Use normal file permissions so PHP
            // workers using a shared cache directory can read compiled views.
            if (!chmod($temporary, 0666 & ~umask())) {
                throw new RuntimeException("Cannot set cache file permissions for '{$temporary}'.");
            }

            if (!rename($temporary, $cacheFile)) {
                throw new RuntimeException("Cannot publish compiled template '{$cacheFile}'.");
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    protected function compile(string $template): string
    {
        return $this->compiler->compile(
            template: $template,
            functionNames: array_keys($this->functions),
            builtinDirectives: $this->builtinDirectives,
        );
    }

    public function addFilter(string $name, callable $filter): void
    {
        $this->filters[$name] = $filter;
    }

    public function applyFilter(string $name, mixed $value, mixed ...$arguments): mixed
    {
        if (!isset($this->filters[$name])) {
            throw new InvalidArgumentException("Template filter '{$name}' is not defined.");
        }

        return ($this->filters[$name])($value, ...$arguments);
    }

    public function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof \UnitEnum) {
            $value = $value->name;
        }

        if (!is_scalar($value) && !$value instanceof \Stringable) {
            throw new InvalidArgumentException('Template output must be scalar, null or Stringable.');
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function setParent(string $template): void
    {
        $this->parentTemplate = $template;
    }

    public function startSection(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);

        if (!is_string($name)) {
            throw new RuntimeException('Cannot end a template section that was not started.');
        }

        $content = ob_get_clean();

        if ($content === false) {
            throw new RuntimeException("Cannot read template section '{$name}'.");
        }

        $this->sections[$name] = $content;
    }

    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name, bool $trim = true): bool
    {
        if (!array_key_exists($name, $this->sections)) {
            return false;
        }

        return !$trim || trim((string) $this->sections[$name]) !== '';
    }

    public function startPush(string $name): void
    {
        $this->stackStack[] = $name;
        ob_start();
    }

    public function endPush(): void
    {
        $name = array_pop($this->stackStack);

        if (!is_string($name)) {
            throw new RuntimeException('Cannot end a template stack push that was not started.');
        }

        $content = ob_get_clean();

        if ($content === false) {
            throw new RuntimeException("Cannot read template stack '{$name}'.");
        }

        $this->stacks[$name][] = $content;
    }

    public function yieldStack(string $name): string
    {
        return implode('', $this->stacks[$name] ?? []);
    }

    /** @param array<string,mixed> $context */
    public function useComponents(string $template, string $alias, array $context = []): void
    {
        $previousAlias = $this->componentAlias;
        $this->componentAlias = $alias;

        try {
            $this->renderPartial($template, $context);
        } finally {
            $this->componentAlias = $previousAlias;
        }
    }

    public function registerComponent(string $name, callable $component): void
    {
        $qualifiedName = $this->componentAlias === null
            ? $name
            : $this->componentAlias . '.' . $name;

        $this->components[$qualifiedName] = $component;
    }

    /**
     * @param list<mixed> $positionalArguments
     * @param array<string,mixed> $namedArguments
     * @param array<string,mixed> $context
     */
    public function callComponent(
        string $name,
        array $positionalArguments = [],
        array $namedArguments = [],
        array $context = [],
    ): string {
        if (!isset($this->components[$name])) {
            throw new RuntimeException("Template component '{$name}' is not registered.");
        }

        $arguments = $positionalArguments;

        foreach ($namedArguments as $argumentName => $argumentValue) {
            $arguments[$argumentName] = $argumentValue;
        }

        $arguments['__context'] = array_merge($this->shared, $context);
        $result = call_user_func_array($this->components[$name], $arguments);

        return (string) $result;
    }

    /**
     * @param list<mixed> $positionalArguments
     * @param array<string,mixed> $namedArguments
     */
    public function startComponentBlock(
        string $name,
        array $positionalArguments = [],
        array $namedArguments = [],
    ): void {
        $this->componentBlockStack[] = [
            'name' => $name,
            'positional' => $positionalArguments,
            'named' => $namedArguments,
        ];
        ob_start();
    }

    public function endComponentBlock(): string
    {
        $definition = array_pop($this->componentBlockStack);

        if (!is_array($definition)) {
            throw new RuntimeException('Cannot end a component block that was not started.');
        }

        $slot = ob_get_clean();

        if ($slot === false) {
            throw new RuntimeException("Cannot read slot for component '{$definition['name']}'.");
        }

        return $this->callComponent(
            $definition['name'],
            $definition['positional'],
            $definition['named'],
            ['slot' => $slot],
        );
    }
}
