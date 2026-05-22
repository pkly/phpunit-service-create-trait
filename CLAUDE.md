# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Static analysis (PHPStan level 9)
vendor/bin/phpstan --ansi

# Code style check (dry run)
vendor/bin/php-cs-fixer fix --config ./.php-cs-fixer.dist.php --dry-run --diff

# Code style fix (apply)
vendor/bin/php-cs-fixer fix --config ./.php-cs-fixer.dist.php

# Run tests
vendor/bin/phpunit
```

## Architecture

This is a single-file PHP library: `src/ServiceMockHelperTrait.php` (namespace `Pkly`).

The trait is designed to be used inside PHPUnit `TestCase` subclasses. It auto-wires constructor dependencies and Symfony `#[Required]`-attributed setter methods with `createMock()` instances, so tests don't need to manually maintain constructor argument lists.

**Internal state:** `$this->mocks[$serviceClass][$dependencyClass]` stores every auto-created mock, keyed first by the service being tested, then by the dependency's class name. Custom-provided parameters (via `$constructor`/`$required` arrays) are not stored in `$this->mocks` and cannot be retrieved via `getMockedService()`.

**Key methods:**
- `createRealMockedServiceInstance(class, constructor[], required[])` — instantiates a real object, injecting mocks for all constructor params and `#[Required]` methods.
- `createRealPartialMockedServiceInstance(class, methods[], constructor[], required[])` — same but returns a `MockObject&T` with specified methods overridden (uses `MockBuilder`).
- `getMockedService(DependencyClass::class, ?serviceClass)` — retrieves a previously auto-created mock; defaults to the first stored service if `$service` is omitted.

**Constraints:** Only supports single-type parameters (union types throw). Built-in typed parameters must have a default value or be supplied explicitly via `$constructor`/`$required`.

## Code style notes

The `.php-cs-fixer.dist.php` config uses `@Symfony` as a base with several overrides:
- `nullable_type_declaration` uses union syntax (`int|null`) not `?int`
- PHPUnit method calls use `static::` not `$this->`
- All method arguments on separate lines when exceeding one line (via `PedroTroller/line_break_between_method_arguments`)
- `declare_strict_types` is enforced