# Contributing to WilyCode Template

Thanks for improving WilyCode Template. Please report issues with a minimal `.tpl`
example, the expected output, the actual result, and the PHP version.

## Development

PHP 8.4 or newer and Composer are required.

```bash
composer validate --strict
composer install
composer test
```

When changing the compiler, add tests for both generated output and evaluated
rendering. Preserve existing template syntax unless an intentional breaking
change is discussed first. Follow the package-specific rules in [AGENTS.md](AGENTS.md).

## Pull requests

Keep changes focused, update English README documentation for public-facing APIs,
and ensure tests pass. Do not introduce dependencies on WilyApp, a specific
application theme, the router or the optional extension packages.

## WilyCode PHP Ecosystem

See the [WilyCode central repository](https://github.com/wilycode/wilycode)
for the full set of independent PHP packages.
