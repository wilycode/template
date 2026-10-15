# WilyCode Template

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4)](https://www.php.net/)
[![MIT License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://github.com/wilycode/template/actions/workflows/tests.yml/badge.svg)](https://github.com/wilycode/template/actions/workflows/tests.yml)

**Lightweight compiled PHP template engine using `.tpl` files, with layouts, components, extensions and source-aware caching.**

> ### WilyCode PHP Ecosystem
>
> This library is part of **[WilyCode](https://github.com/wilycode/wilycode)**, a collection of independent PHP components. Each package has its own repository, Composer installation and releases.
>
> **[Explore the WilyCode ecosystem →](https://github.com/wilycode/wilycode)**

## Requirements

- PHP 8.4 or newer.
- No mandatory runtime dependencies other than PHP.


`TemplateEngine` owns rendering state, layouts, sections, stacks, components and cache files. Template-language parsing and PHP generation are delegated through `TemplateCompilerInterface`. The default `TemplateCompiler` is a small pipeline of focused compiler passes, while `TemplateEngine` remains the rendering entry point.

## Installation

```bash
composer require wilycode/template
```

## Configuration

```php
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;

$locator = new FileLocator([
    '' => __DIR__ . '/template',
]);
$loader = new TemplateLoader($locator);
$engine = new TemplateEngine(
    loader: $loader,
    cachePath: __DIR__ . '/var/cache/template',
    autoReload: true,
);

echo $engine->render('article.list', [
    'articles' => $articles,
]);
```

The logical name `article.list` resolves to:

```text
template/article/list.tpl
```

## `.tpl` formatting

Use 4-space indentation. Closing directives align with their opening directives, and block contents are indented by another 4 spaces.

Every nested block adds another 4 spaces. `@else` and `@elseif` align with their matching `@if`.

```tpl
@section('content')
    @if($articles !== [])
        <ul>
            @foreach($articles as $article)
                <li>{{ $article['title'] }}</li>
            @endforeach
        </ul>
    @else
        <p>No articles.</p>
    @endif
@endsection
```

## Output

```tpl
{{ $title }}
{!! $trustedHtml !!}
```

`{{ ... }}` escapes HTML. `{!! ... !!}` is only for trusted or previously sanitized HTML.

For normal output, use `{{ $value }}` directly. Do not write `<?= $__engine->e(...) ?>` manually and do not add `(string)` only for rendering. Data should have the correct type before it reaches the view.

## Comments

```tpl
{# This comment is removed during compilation. #}

{#
    Multiline
    comment.
#}
```

HTML comments remain in the rendered document:

```html
<!-- Visible in page source. -->
```

## Layouts and sections

`template/layout/admin.tpl`:

```tpl
<!doctype html>
<html lang="en">
    <body>
        <main>
            @yield('content')
        </main>
    </body>
</html>
```

`template/article/list.tpl`:

```tpl
@extends('layout.admin')

@section('content')
    <h1>{{ $title }}</h1>
@endsection
```

Conditional section:

```tpl
@hasSection('page_actions')
    <div class="d-flex gap-2">
        @yield('page_actions')
    </div>
@endhasSection
```

## Include

```tpl
@include('article.filters')

@include('article.filters', [
    'filters' => $filters,
])
```

An include inherits the local variable scope. Data passed as the second argument applies only inside that partial.

## Variables and data scope

Use **`@set` for local template values** and **`@share` for values that a subsequently rendered layout or partial needs to read**. They are not interchangeable.

| How a value is provided | Available in the current template? | Available to a later `@include`? | Available to a parent layout via `@extends`? |
| --- | --- | --- | --- |
| `@set('name', $value)` | Yes, after `@set` | Yes, through the include's inherited scope | **No** |
| `@share('name', $value)` | **Not automatically** | Yes, unless the include inherits a value of the same name from its caller | Yes, unless explicit render data overrides it |
| `$engine->render('view', ['name' => $value])` | Yes | Yes | Yes |

### Local values with `@set`

```tpl
@set('heading', 'Latest articles')
<h1>{{ $heading }}</h1>
@include('article.summary')
```

`article/summary.tpl` can also use `{{ $heading }}`: `@include` inherits variables from the point at which it is called. You can override an inherited value for that partial:

```tpl
@include('article.summary', ['heading' => 'Featured articles'])
```

`@set` assigns a PHP variable **inside the currently executing template**. A layout named by `@extends` runs in a separate variable scope, so it does **not** receive that local assignment automatically.

### Passing a value to a layout with `@share`

`template/article/show.tpl`:

```tpl
@extends('layout.main')

@share('pageTitle', 'Article details')
@set('heading', 'Latest articles')

@section('content')
    <h1>{{ $heading }}</h1>
    @include('article.summary')
@endsection
```

`template/layout/main.tpl`:

```tpl
<!doctype html>
<html lang="en">
    <head>
        <title>{{ $pageTitle ?? 'Untitled' }}</title>
    </head>
    <body>
        @yield('content')
    </body>
</html>
```

The layout receives **`pageTitle`** through `@share`; the child template and its subsequent include receive **`heading`** through `@set`. Changing the child to `@set('pageTitle', 'Article details')` would **not** change the title in the parent layout.

**Important details:**

- `@share` changes the engine's shared state; it does not create or update a local PHP variable in the template where it appears. Do not assume `{{ $pageTitle }}` becomes available later in that same file solely because you called `@share`.
- Values passed explicitly to `render('view', ['pageTitle' => 'Explicit title'])` take precedence over shared values in the parent layout. Prefer render data for normal page inputs.
- **Shared values persist on the same `TemplateEngine` instance across separate `render()` calls.** They are not automatically cleared at the end of a render. Avoid sharing request-specific or sensitive values on an engine reused between requests; use per-request instances or pass page-specific values through `render()` instead.

For globally provided helpers or data, PHP code can call `$engine->share('name', $value)` before rendering. This uses the same shared state as `@share`.

## Conditions and loops

```tpl
@if($user !== null)
    <p>{{ $user['name'] }}</p>
@elseif($guestName !== '')
    <p>{{ $guestName }}</p>
@else
    <p>Guest</p>
@endif
```

```tpl
@foreach($users as $user)
    <span>{{ $user['name'] }}</span>
@endforeach
```

## Stacks

```tpl
@push('scripts')
    <script src="/assets/article.js"></script>
@endpush

@stack('scripts')
```

## Components

Definition in `template/component/ui.tpl`:

```tpl
@component button($label, $type = 'button')
    <button type="{{ $type }}" class="btn btn-primary">
        {{ $label }}
    </button>
@endcomponent

@component card($title = null)
    <section class="card">
        @if($title !== null)
            <header class="card-header">
                {{ $title }}
            </header>
        @endif

        <div class="card-body">
            {!! $slot !!}
        </div>
    </section>
@endcomponent
```

Usage:

```tpl
@use('component.ui', 'ui')

@ui.button('Save', type: 'submit')

@ui.card(title: 'Article')
    <p>Card content.</p>
@endui.card
```

## Extensions

Extensions implement `TemplateExtensionInterface` and register functions, filters,
shared values or reserved directives. They are separate Composer packages rather
than hard-coded features of this engine.

```php
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateExtensionInterface;

$extension = new class implements TemplateExtensionInterface {
    public function register(TemplateEngine $engine): void
    {
        $engine->addFilter('shout', static fn (string $value): string => strtoupper($value));
    }
};

$engine->addExtension($extension);
// Or register several extensions at once:
// $engine->addExtensions([$firstExtension, $secondExtension]);
```

A registered filter can be used as `{{ $title | shout }}`. The core engine does
not depend on the router, asset handling or translation packages.

### Recommended WilyCode extensions

Keep the template engine lightweight and add the features your application needs
through dedicated WilyCode packages:

| Extension | Purpose |
| --- | --- |
| **[Template Extension Assets](https://github.com/wilycode/template-extension-assets)** | Asset URL helpers for templates. |
| **[Template Extension String](https://github.com/wilycode/template-extension-string)** | Reusable string helpers and filters for template output. |
| **[Template Extension Translate](https://github.com/wilycode/template-extension-translate)** | Translation support for localized templates. |

Install the extensions you need with Composer:

```bash
composer require wilycode/template-extension-assets
composer require wilycode/template-extension-string
composer require wilycode/template-extension-translate
```

These extensions are separate packages. The core engine does not require any of
them; use the extension APIs described above to register integrations in your
application.

## Functions and filters

```php
$engine->addFunction('route', $routeGenerator);
$engine->addFilter('upper', static fn (mixed $value): string => mb_strtoupper((string) $value));
```

```tpl
<a href="@route('article.list')">
    {{ 'articles' | upper }}
</a>
```

Filters can be chained:

```tpl
{{ $title | trim | upper }}
```

## Verbatim

```tpl
@verbatim
    <script>
        const example = '{{ this stays literal }}';
    </script>
@endverbatim
```

Inside `@verbatim`, the compiler does not interpret directives or interpolations.

## Main template context

During `render('article.form')`, the engine exposes:

```php
$engine->getCurrentTemplateName(); // article.form
```

The context remains `article.form` while rendering:

- a layout through `@extends`,
- a partial through `@include`,
- a component through `@use` and `@alias.component`.

After the main `render()` finishes, the method returns `null`. Extensions can therefore bind behavior to the page view without depending on the router.

## Compiler architecture

The rendering engine depends on `TemplateCompilerInterface`, not on the concrete compiler:

```php
use WilyCode\Template\TemplateCompiler;
use WilyCode\Template\TemplateEngine;

$compiler = new TemplateCompiler();
$engine = new TemplateEngine(
    loader: $loader,
    cachePath: $cachePath,
    autoReload: true,
    compiler: $compiler,
);
```

The default compiler delegates syntax handling to small passes:

```text
TemplateCompiler
├── CommentCompiler
├── ComponentCompiler
├── StateCompiler
├── LayoutCompiler
├── IncludeCompiler
├── ControlFlowCompiler
├── custom CompilerPassInterface implementations
├── FunctionCompiler
└── OutputCompiler
```

This keeps the parsing responsibilities separate and allows application-specific syntax without subclassing `TemplateEngine` or editing the built-in compiler.

A custom pass implements `CompilerPassInterface`:

```php
use WilyCode\Template\Compiler\CompilerContext;
use WilyCode\Template\Compiler\CompilerPassInterface;
use WilyCode\Template\TemplateCompiler;

final class BadgeCompiler implements CompilerPassInterface
{
    public function compile(string $template, CompilerContext $context): string
    {
        return str_replace(
            '@badge($title)',
            '<span class="badge">{{ $title }}</span>',
            $template,
        );
    }
}

$compiler = new TemplateCompiler([
    new BadgeCompiler(),
]);
```

Additional passes run after the built-in structural directives and before registered functions and output interpolation. A pass can use `CompilerContext::compileNested()` when it introduces nested template source that should go through the same compiler pipeline.

For a completely different compiler implementation, implement `TemplateCompilerInterface` and inject it through the `TemplateEngine` constructor.

## Cache

```php
$engine = new TemplateEngine($loader, $cachePath, true);  // development
$engine = new TemplateEngine($loader, $cachePath, false); // production
```

```php
$engine->clearCache();
```

With `autoReload=true`, the compiled cache filename includes SHA-256 fingerprints of the template source and compiler configuration (registered function names, reserved directives, and compiler identity). Changing a template, registering a new function, or changing reserved directives automatically selects a fresh compilation even when the source modification time is unchanged. Different engine instances can safely share a cache directory without using each other's incompatible compiled PHP. Custom injected compilers receive an isolated cache namespace for each engine instance. With `autoReload=false`, source changes require `clearCache()`, but changes to compiler configuration are still detected.

Compiled PHP files are published through an atomic rename so another request cannot read a partially written file. Cache filenames are immutable; old source revisions remain on disk until you call `clearCache()`. On production deployments, clear the template cache when updating templates to reclaim space and ensure source changes are picked up when auto-reload is disabled.

## Layout inheritance and component defaults

A layout can extend another layout. The engine evaluates successive parents until it reaches the outermost layout, retaining named sections and shared variables. Intermediate markup is passed to the outer layout as the `content` section unless the intermediate layout explicitly defines its own `content` section. Cyclic `@extends` references raise `RuntimeException` instead of looping indefinitely. Layout directive arguments can contain nested parentheses.

Untyped component parameters can use expressions in their default values, including nested function calls such as `@component badge($label = strtoupper('hello'))`. These defaults are evaluated only when the argument is omitted; explicitly passing `null` does not trigger the default. Typed component parameters continue to support ordinary PHP constant-expression defaults.

## Rendering failures

Rendering restores the caller's output-buffer level even when an include, section,
component or layout throws. Partial output from the failed render is discarded,
and the original exception is propagated. The root template context is cleared
so the engine can be used for the next render.

Child views and parent layouts are evaluated in separate variable scopes.
A parent's variables are rebuilt from the current shared state and explicit
render data, so `@share` can replace a shared default set before the render.
Explicit render data retains precedence over shared values; `@set` remains local
to its view and does not overwrite the parent's variables.

## Development and testing

```bash
composer validate --strict
composer install
composer test
```

GitHub Actions runs PHPUnit on PHP 8.4 for pushes and pull requests. Tests and
other development files are kept in Git but excluded from Composer archives.

## Contributing

Issues and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md)
and the [issue tracker](https://github.com/wilycode/template/issues).

## License

MIT © 2026 Simon Piter. See [LICENSE](LICENSE).
