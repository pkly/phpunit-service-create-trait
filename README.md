# PHPUnit Service Create Trait
A helper trait for PHPUnit 12+ for easier creation of services with dependencies in unit testing

[![Packagist Downloads](https://img.shields.io/packagist/dt/pkly/phpunit-service-create-trait)](https://packagist.org/packages/pkly/phpunit-service-create-trait)

## Installation

Simply run

```
composer require --dev pkly/phpunit-service-create-trait
```

Compatible with PHPUnit 12 and 13, requires PHP 8.4+

## Usage

In any of your PHPUnit test cases simply 

```php
class MyTestCase extends \PHPUnit\Framework\TestCase {
    use \Pkly\ServiceMockHelperTrait;
    
    private AnyClass $service;
    
    public function setUp(): void {
        $this->service = $this->createRealMockedServiceInstance(AnyClass::class);
    }

    public function testSomething(): void
    {
        $mock = $this->createMock(MyEntity::class);
    
        $this->getMockedService(EntityManagerInterface::class)
            ->expects($this->once())
            ->method('delete')
            ->with($mock);
            
        $this->service->deleteSomething($mock);
    }
}
```

Any dependencies in the constructor as well as methods marked with Symfony's `#[Required]` attribute will be automatically plugged in with test doubles.
This allows you to write complex tests without wasting time updating your construct calls each time you modify something.

### Stubs by default, mocks on demand

PHPUnit is separating mocks from stubs and complains about every mock object that has no expectations
configured (`No expectations were configured for the mock object for X ...`). Creating a mock for every
single dependency would drown your test run in those notices, so every dependency is created as a double
that PHPUnit does not know about - it is never verified and never complained about. Fetching one is what
hands it over to the test:

- `getMockedService()` registers the double with the test case and returns it as a `MockObject`, so
  `expects()` is verified for you (and PHPUnit does tell you off if you then configure nothing on it)
- `getStubbedService()` returns the very same object as a `Stub`, still unregistered, for when you only
  need to configure return values
- everything you never fetch stays invisible to PHPUnit

The service itself is constructed immediately, so the order does not matter - expectations can be declared
before or after you use it:

```php
public function testSomething(): void
{
    $service = $this->createRealMockedServiceInstance(AnyClass::class);

    $this->getMockedService(EntityManagerInterface::class)
        ->expects($this->once())
        ->method('flush');

    $service->doSomething();
}
```

### Several dependencies of the same type

Every parameter gets its own double, so a service depending on the same type twice receives two distinct
ones. Fetching such a type without saying which parameter you mean throws a `LogicException` - pass the
parameter name as the second argument:

```php
$first = $this->getMockedService(BasicService::class, 'first');
$second = $this->getMockedService(BasicService::class, 'second');
```

### Several services in one test

`getMockedService()` and `getStubbedService()` use the most recently created service by default. Pass the
service class as the third argument to address any other one:

```php
$this->getMockedService(BasicService::class, service: OtherService::class);
```

### Okay, but what if I need to use something custom?

Simply assign the proper parameter name in either `$constructor` or `$required` in the appropriate methods.
That will use your object instead of creating one for you, keep in mind you cannot retrieve it via `$this->getMockedService()`.

Some parameters always have to be provided that way:

- scalar parameters without a default value
- enums, those cannot be doubled at all

Internal classes (`\DateInterval` and friends) are handed to PHPUnit like any other type - most of them
double just fine, and the ones that do not produce a precise error from PHPUnit telling you to provide it.

A nullable dependency (`?Foo`) still receives a double, pass `null` explicitly if that is what your test needs.

### Partial objects?

Sure, works the same, just use `createRealPartialMockedServiceInstance` instead of `createRealMockedServiceInstance`, in that case you must
also specify the methods to override in your mock. Returned instance is `T&MockObject`.

Its dependencies behave exactly like the ones of a normal service - unregistered until you fetch them.
The partial mock itself is a regular PHPUnit mock though, so the methods you override are subject to the
usual expectation rules.

### Feature requests?

Sure, hit me up with an issue if you wish.
