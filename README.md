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
single dependency would drown your test run in those notices, so the trait only creates what you ask for:

- a dependency you fetch with `getMockedService()` is a `MockObject`
- everything else is a plain `Stub`

To make that possible the returned service is a **lazy ghost** - it is only really constructed the first
time you use it. That means expectations have to be declared *before* you touch the service:

```php
public function testSomething(): void
{
    $service = $this->createRealMockedServiceInstance(AnyClass::class);

    // declare first ...
    $this->getMockedService(EntityManagerInterface::class)
        ->expects($this->once())
        ->method('flush');

    // ... then use the service, this is where it gets constructed
    $service->doSomething();
}
```

Asking for a mock after the service has been used throws a `LogicException`, because such a mock could
never end up inside the already constructed service.

If you only need to configure return values without setting any expectation, use `getStubbedService()`,
which returns the very same stub the service will receive.

### Several dependencies of the same type

Doubles are addressed by type, and a service depending on the same type twice simply receives the same
double for both parameters. Pass a parameter name as the second argument when you need them apart:

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
- internal classes (`\DateInterval` and friends) and enums, doubling those tends to break in confusing ways

A nullable dependency (`?Foo`) still receives a double, pass `null` explicitly if that is what your test needs.

### Partial objects?

Sure, works the same, just use `createRealPartialMockedServiceInstance` instead of `createRealMockedServiceInstance`, in that case you must
also specify the methods to override in your mock. Returned instance is `T&MockObject`.

A partial mock is a generated class and cannot be created lazily, so it is built immediately and all of its
dependencies are mocks, exactly like they used to be. Provide them explicitly or configure them if you want
to keep your test run notice free.

### Feature requests?

Sure, hit me up with an issue if you wish.
