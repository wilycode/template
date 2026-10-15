# WilyCode Template — Agent Instructions

Before editing the library or writing `.tpl` files, read [README.md](README.md).
It is the main reference for the template syntax, public API, examples, and
variable scope (including `@set`, `@share`, and `@extends`).

- Keep this package independent of WilyApp and avoid unnecessary abstractions.
- Preserve supported directives and public APIs unless a change is explicitly requested.
- Add regression tests for behavior changes and run `composer test` when available.
- Update README.md and CHANGELOG.md when behavior changes.
