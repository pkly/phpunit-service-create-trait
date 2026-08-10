<?php

declare(strict_types=1);

namespace Pkly\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Pkly\ServiceMockHelperTrait;
use Pkly\Tests\Service\BasicService;
use Pkly\Tests\Service\InterfaceService;
use Pkly\Tests\Service\MessengerInterface;
use Pkly\Tests\Service\NoConstructorService;
use Pkly\Tests\Service\NullableService;
use Pkly\Tests\Service\ReadonlyService;
use Pkly\Tests\Service\ScalarService;

class StubByDefaultTest extends TestCase
{
    use ServiceMockHelperTrait;

    public function testDependenciesNobodyConfiguresAreStubs(): void
    {
        $service = $this->createRealMockedServiceInstance(InterfaceService::class);

        static::assertInstanceOf(Stub::class, $service->getMessenger());
        static::assertNotInstanceOf(MockObject::class, $service->getMessenger());

        static::assertInstanceOf(Stub::class, $service->getService());
        static::assertNotInstanceOf(MockObject::class, $service->getService());
    }

    public function testOnlyRequestedDependenciesBecomeMocks(): void
    {
        $service = $this->createRealMockedServiceInstance(InterfaceService::class);

        $this->getMockedService(MessengerInterface::class)
            ->expects(static::once())
            ->method('send')
            ->with('NEW-hello')
            ->willReturn(true);

        $this->getStubbedService(BasicService::class)
            ->method('process')
            ->willReturn('NEW-hello');

        static::assertTrue($service->notify('hello'));

        static::assertInstanceOf(MockObject::class, $service->getMessenger());
        static::assertNotInstanceOf(MockObject::class, $service->getService());
    }

    public function testInterfaceDependencyIsDoubled(): void
    {
        $service = $this->createRealMockedServiceInstance(InterfaceService::class);

        static::assertInstanceOf(MessengerInterface::class, $service->getMessenger());
    }

    public function testReadonlyServiceWithPromotedProperties(): void
    {
        $service = $this->createRealMockedServiceInstance(ReadonlyService::class);

        $this->getMockedService(BasicService::class)
            ->expects(static::once())
            ->method('process')
            ->with('hello')
            ->willReturn('NEW-hello');

        static::assertSame('READONLY-NEW-hello', $service->describe('hello'));
        static::assertInstanceOf(BasicService::class, $service->service);
        static::assertSame('READONLY', $service->prefix);
    }

    public function testScalarParameterUsesItsDefaultValue(): void
    {
        $service = $this->createRealMockedServiceInstance(ReadonlyService::class);

        static::assertSame('READONLY', $service->prefix);
    }

    public function testScalarParameterCanBeProvided(): void
    {
        $service = $this->createRealMockedServiceInstance(ScalarService::class, ['name' => 'CUSTOM']);

        $this->getStubbedService(BasicService::class)
            ->method('process')
            ->willReturn('NEW-hello');

        static::assertSame('CUSTOM', $service->getName());
        static::assertSame('CUSTOM:NEW-hello', $service->process('hello'));
    }

    public function testServiceWithoutConstructor(): void
    {
        $service = $this->createRealMockedServiceInstance(NoConstructorService::class);

        static::assertSame('no-constructor', $service->getValue());
    }

    public function testNullableDependencyIsStillDoubled(): void
    {
        $service = $this->createRealMockedServiceInstance(NullableService::class);

        static::assertInstanceOf(Stub::class, $service->getService());
    }

    public function testNullableDependencyCanBeSetToNull(): void
    {
        $service = $this->createRealMockedServiceInstance(NullableService::class, ['service' => null]);

        static::assertNull($service->getService());
    }
}
