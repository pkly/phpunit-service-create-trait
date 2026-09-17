<?php

declare(strict_types=1);

namespace Pkly\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Pkly\ServiceMockHelperTrait;
use Pkly\Tests\Service\BasicService;
use Pkly\Tests\Service\DuplicateTypeService;
use Pkly\Tests\Service\ExtendedService;
use Pkly\Tests\Service\InterfaceService;
use Pkly\Tests\Service\MessengerInterface;
use Pkly\Tests\Service\RequiredService;
use Pkly\Tests\Service\ScalarService;

class PartialStubServiceTest extends TestCase
{
    use ServiceMockHelperTrait;

    public function testPartialStubOverridesTheGivenMethod(): void
    {
        $service = $this->createRealPartialStubbedServiceInstance(ExtendedService::class, ['work']);

        // a stub has no with(), so the arguments are checked inside the replacement itself
        $service->method('work')
            ->willReturnCallback(static fn (string $input): string => 'OVERRIDDEN-'.$input);

        static::assertSame('OVERRIDDEN-hello', $service->work('hello'));
    }

    public function testPartialStubIsAStubAndNotAMockObject(): void
    {
        $service = $this->createRealPartialStubbedServiceInstance(ExtendedService::class, ['work']);

        static::assertInstanceOf(ExtendedService::class, $service);
        static::assertInstanceOf(Stub::class, $service);
        // expects() is what separates the two, and a stub must not offer it
        static::assertNotInstanceOf(MockObject::class, $service);
    }

    /**
     * Nothing is configured on the stub on purpose: the TestCase never learns about it, so
     * failOnPhpunitNotice cannot trip over a double without expectations.
     */
    public function testPartialStubNeedsNoConfigurationAtAll(): void
    {
        $service = $this->createRealPartialStubbedServiceInstance(ExtendedService::class, ['work']);

        // the doubled method answers with a generated return value instead of the real code
        static::assertNull($service->work('hello'));
    }

    public function testPartialStubKeepsRunningRealCodeForUntouchedMethods(): void
    {
        $service = $this->createRealPartialStubbedServiceInstance(RequiredService::class, ['describe']);

        $service->method('describe')
            ->willReturn('STUBBED');

        $this->getStubbedService(BasicService::class)
            ->method('process')
            ->willReturn('NEW-hello');

        $this->getStubbedService(MessengerInterface::class)
            ->method('send')
            ->willReturn(true);

        static::assertSame('STUBBED', $service->describe());
        // notify() was not doubled, so it still runs and reaches the doubled dependencies
        static::assertTrue($service->notify('hello'));
    }

    /**
     * An empty list means "double nothing", the same way MockBuilder::onlyMethods([]) does; were
     * it passed straight through to the generator, every method would be doubled instead.
     */
    public function testPartialStubWithAnEmptyMethodListDoublesNothing(): void
    {
        $service = $this->createRealPartialStubbedServiceInstance(ExtendedService::class, []);

        $this->getStubbedService(BasicService::class)
            ->method('process')
            ->willReturn('NEW-hello');

        static::assertSame('NEW-hello', $service->work('hello'));
    }

    public function testPartialStubKeepsUsingDoublesForItsDependencies(): void
    {
        $service = $this->createRealPartialStubbedServiceInstance(InterfaceService::class, ['getMessenger']);

        $messenger = $this->getStubbedService(MessengerInterface::class);
        $basic = $this->getStubbedService(BasicService::class);

        static::assertInstanceOf(Stub::class, $messenger);
        static::assertInstanceOf(Stub::class, $basic);

        // getMessenger() is doubled, the untouched getService() still returns the injected double
        static::assertSame($basic, $service->getService());
    }

    public function testPartialStubDependenciesCanStillBeRegisteredAsMocks(): void
    {
        $service = $this->createRealPartialStubbedServiceInstance(InterfaceService::class, ['getMessenger']);

        $this->getMockedService(MessengerInterface::class)
            ->expects(static::once())
            ->method('send')
            ->with('NEW-hello')
            ->willReturn(true);

        $this->getStubbedService(BasicService::class)
            ->method('process')
            ->willReturn('NEW-hello');

        static::assertTrue($service->notify('hello'));
    }

    public function testPartialStubAcceptsProvidedConstructorParameters(): void
    {
        $service = $this->createRealPartialStubbedServiceInstance(
            ScalarService::class,
            ['getName'],
            ['name' => 'CUSTOM']
        );

        $service->method('getName')
            ->willReturn('STUBBED');

        $this->getStubbedService(BasicService::class)
            ->method('process')
            ->willReturn('NEW-hello');

        static::assertSame('STUBBED', $service->getName());
        // process() is untouched, so it reads the value the constructor actually received
        static::assertSame('CUSTOM:NEW-hello', $service->process('hello'));
    }

    public function testPartialStubProvidedConstructorParameterCannotBeMocked(): void
    {
        $basic = new BasicService();

        $this->createRealPartialStubbedServiceInstance(
            ExtendedService::class,
            ['work'],
            ['service' => $basic]
        );

        $this->expectException(\LogicException::class);

        $this->getStubbedService(BasicService::class);
    }

    public function testPartialStubCallsRequiredSettersWithDoubles(): void
    {
        $service = $this->createRealPartialStubbedServiceInstance(RequiredService::class, ['describe']);

        static::assertInstanceOf(MessengerInterface::class, $service->getMessenger());
        static::assertSame($this->getStubbedService(MessengerInterface::class), $service->getMessenger());
    }

    public function testPartialStubAcceptsProvidedRequiredParameters(): void
    {
        $messenger = new class implements MessengerInterface {
            public function send(
                string $message
            ): bool {
                return 'NEW-hello' === $message;
            }
        };

        $service = $this->createRealPartialStubbedServiceInstance(
            RequiredService::class,
            ['describe'],
            required: ['messenger' => $messenger]
        );

        $this->getStubbedService(BasicService::class)
            ->method('process')
            ->willReturn('NEW-hello');

        static::assertSame($messenger, $service->getMessenger());
        static::assertTrue($service->notify('hello'));
    }

    public function testPartialStubSameTypeDependenciesCanBeTargetedByName(): void
    {
        $service = $this->createRealPartialStubbedServiceInstance(DuplicateTypeService::class, ['getFirst']);

        $first = $this->getStubbedService(BasicService::class, 'first');
        $second = $this->getStubbedService(BasicService::class, 'second');

        static::assertNotSame($first, $second);

        $first->method('process')
            ->willReturn('FIRST');

        $second->method('process')
            ->willReturn('SECOND');

        static::assertSame('SECOND', $service->process('hello'));
    }

    public function testPartialStubBecomesTheServiceDoublesResolveAgainst(): void
    {
        $extended = $this->createRealMockedServiceInstance(ExtendedService::class);
        $interface = $this->createRealPartialStubbedServiceInstance(InterfaceService::class, ['notify']);

        // the partial stub was created last, so an unqualified lookup targets its dependency
        static::assertSame($this->getStubbedService(BasicService::class), $interface->getService());

        // the earlier service is still reachable by naming it explicitly
        $this->getStubbedService(BasicService::class, service: ExtendedService::class)
            ->method('process')
            ->willReturn('NEW-hello');

        static::assertSame('NEW-hello', $extended->work('hello'));
    }
}
