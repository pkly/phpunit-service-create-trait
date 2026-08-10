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

The trait is designed to be used inside PHPUnit `TestCase` subclasses. It auto-wires constructor dependencies and Symfony `#[Required]`-attributed setter methods with test doubles, so tests don't need to manually maintain constructor argument lists.

**Stubs by default, mocks on demand:** PHPUnit emits a notice for every `MockObject` without expectations, so dependencies are `createStub()` instances unless the test asks for them via `getMockedService()`, which returns a `createMock()` instance. Mock-vs-stub is baked into the generated double class and cannot be changed afterwards, so `createRealMockedServiceInstance()` returns a **lazy ghost** (`ReflectionClass::newLazyGhost()`): the service is constructed the first time it is touched, by which point the test has declared which dependencies it wants as mocks. `#[Required]` setters run inside the initializer, since calling them from the outside would write a property and initialize the ghost early.

**Internal state:** `$this->serviceStates` is an `SplObjectStorage` keyed by the created service instance, holding its class, its mockable parameters (`name` + `type`), the mocks and stubs created for it, and whether it has been initialized. `$this->serviceInstances` maps a class to its most recently created instance and `$this->currentServiceInstance` points at the last one created overall. Doubles are keyed by dependency type, or by `Type$parameterName` when a specific parameter is targeted; parameters supplied via `$constructor`/`$required` are not doubled and cannot be retrieved.

**Key methods:**
- `createRealMockedServiceInstance(class, constructor[], required[])` — returns a lazy ghost of a real object; on first use it injects doubles for all constructor params and `#[Required]` methods.
- `createRealPartialMockedServiceInstance(class, methods[], constructor[], required[])` — returns a `MockObject&T` with specified methods overridden (uses `MockBuilder`). A generated class cannot be a ghost, so this stays eager and its dependencies are all mocks.
- `getMockedService(DependencyClass::class, ?parameterName, ?serviceClass)` — declares (or returns) a mock for a dependency; throws once the service has been initialized. Defaults to the most recently created service.
- `getStubbedService(DependencyClass::class, ?parameterName, ?serviceClass)` — same for the stub a dependency would get anyway, for configuring return values without expectations.

**Constraints:** Only supports single-type parameters (union types throw). Built-in typed parameters must have a default value or be supplied explicitly via `$constructor`/`$required`, as must internal classes and enums. Nullable class parameters (`?Foo`) still receive a double.

**Tests:** `tests/Service` holds the fixtures, `tests/Unit` the test cases. `phpunit.xml.dist` sets `failOnPhpunitNotice`, so a dependency that is needlessly mocked fails the suite.

## Code style notes

The `.php-cs-fixer.dist.php` config uses `@Symfony` as a base with several overrides:
- `nullable_type_declaration` uses union syntax (`int|null`) not `?int`
- PHPUnit method calls use `static::` not `$this->`
- All method arguments on separate lines when exceeding one line (via `PedroTroller/line_break_between_method_arguments`)
- `declare_strict_types` is enforced