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

**Unregistered doubles:** PHPUnit emits a notice for every `MockObject` without expectations, so every dependency is built through `MockGenerator::testDouble()` directly (`__createUnregisteredMock()`) instead of `createMock()`/`createStub()`. Such a double is a full `MockObject` — `expects()` works on it — but the `TestCase` never learns about it, so PHPUnit neither verifies it nor complains. `getMockedService()` is what registers it (`registerMockObject()` + a `testCreatedMockObject` event), handing ownership to the test; `getStubbedService()` returns the same object unregistered, narrowed to `Stub`. Services are constructed eagerly, so doubles can be configured before or after the service is used.

**Internal state:** `$this->serviceStates` is an `SplObjectStorage` keyed by the created service instance, holding its class, its mockable parameters (`name` + `type`), the doubles created for it, and which of them have been registered. `$this->serviceInstances` maps a class to its most recently created instance and `$this->currentServiceInstance` points at the last one created overall. Doubles are keyed `Type$parameterName` — one per parameter, so two parameters of the same type get two distinct doubles and must be addressed by name. Parameters supplied via `$constructor`/`$required` are not doubled and cannot be retrieved.

**Key methods:**
- `createRealMockedServiceInstance(class, constructor[], required[])` — returns a real instance with doubles injected into all constructor params and `#[Required]` methods.
- `createRealPartialMockedServiceInstance(class, methods[], constructor[], required[])` — returns a `MockObject&T` with specified methods overridden (uses `MockBuilder`, plus a manual `testCreatedPartialMockObject` event since `MockBuilder` emits none).
- `getMockedService(DependencyClass::class, ?parameterName, ?serviceClass)` — registers and returns the mock for a dependency. Defaults to the most recently created service, falling back to the other trait-created services when that one has no such dependency (throws when several match).
- `getStubbedService(DependencyClass::class, ?parameterName, ?serviceClass)` — same double, left unregistered, for configuring return values without expectations.

**Constraints:** Only supports single-type parameters (union types throw). Built-in typed parameters must have a default value or be supplied explicitly via `$constructor`/`$required`, as must enums. Internal classes are passed to PHPUnit like anything else and mostly double fine. Nullable class parameters (`?Foo`) still receive a double.

**Tests:** `tests/Service` holds the fixtures, `tests/Unit` the test cases. `phpunit.xml.dist` sets `failOnPhpunitNotice`, so a dependency that is needlessly mocked fails the suite.

## Code style notes

The `.php-cs-fixer.dist.php` config uses `@Symfony` as a base with several overrides:
- `nullable_type_declaration` uses union syntax (`int|null`) not `?int`
- PHPUnit method calls use `static::` not `$this->`
- All method arguments on separate lines when exceeding one line (via `PedroTroller/line_break_between_method_arguments`)
- `declare_strict_types` is enforced