# PHPUnit Service Create Trait
A helper trait for PHPUnit 12+ for easier creation of services with dependencies in unit testing

[![Packagist Downloads](https://img.shields.io/packagist/dt/pkly/phpunit-service-create-trait)](https://packagist.org/packages/pkly/phpunit-service-create-trait)

## Installation

Simply run

```
composer require --dev pkly/phpunit-service-create-trait
```

Currently compatible only with PHPUnit 12+

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

Any dependencies in the constructor as well as methods marked with Symfony's `#[Required]` attribute will be automatically plugged in with mocks.
This allows you to write complex tests without wasting time updating your construct calls each time you modify something.

### Okay, but what if I need to use something custom?

Simply assign the proper parameter name in either `$constructor` or `$required` in the appropriate methods.
That will use your object instead of creating one for you, keep in mind you cannot retrieve it via `$this->getMockedService()`.

### Partial objects?

Sure, works the same, just use `createRealPartialMockedServiceInstance` instead of `createRealMockedServiceInstance`, in that case you must
also specify the methods to override in your mock. Returned instance is `T&MockObject`.

### Feature requests?

Sure, hit me up with an issue if you wish.
