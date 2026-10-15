# Changelog

All notable changes to WilyCode Template are documented here.

## [Unreleased]

### Added

- Composer distribution metadata, development script and GitHub Actions CI.
- README installation, extension and development guidance for an independent package.

### Fixed

- Isolated compiled cache entries by template source and compiler configuration, with atomic writes.
- Supported nested layout inheritance and reported circular `@extends` chains.
- Parsed nested parentheses in component definitions and layout directives.
- Evaluated untyped component default expressions at call time, including function calls.

### Documentation

- Clarified `@set` versus `@share` scope across `@extends`, `@include`, and `render()` data, including shared-state lifetime.
- Kept `AGENTS.md` brief, directing coding agents to README as the documentation source of truth.
- Recommended the Assets, String, and Translate template extensions in README.

### Refactored

- Removed redundant private helpers and simplified control-flow compilation.
- Kept existing public rendering, extension, and loader APIs unchanged.

<!-- Add a dated version section when a GitHub release is published. -->
