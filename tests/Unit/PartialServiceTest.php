<?php

declare(strict_types=1);

namespace Pkly\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pkly\ServiceMockHelperTrait;
use Pkly\Tests\Service\BasicService;
use Pkly\Tests\Service\ExtendedService;
use Pkly\Tests\Service\InterfaceService;
use Pkly\Tests\Service\MessengerInterface;

class PartialServiceTest extends TestCase
{
    use ServiceMockHelperTrait;

    public function testPartialMockOverridesTheGivenMethod(): void
    {
        $service = $this->createRealPartialMockedServiceInstance(ExtendedService::class, ['work']);

        $this->getMockedService(BasicService::class)
            ->expects(static::never())
            ->method('process');

        $service->expects(static::once())
            ->method('work')
            ->with('hello')
            ->willReturn('OVERRIDDEN');

        static::assertSame('OVERRIDDEN', $service->work('hello'));
    }

    public function testPartialMockKeepsUsingMocksForItsDependencies(): void
    {
        $service = $this->createRealPartialMockedServiceInstance(InterfaceService::class, ['notify']);

        $messenger = $this->getMockedService(MessengerInterface::class);
        $messenger->expects(static::never())
            ->method('send');

        // a partial mock is built eagerly, so all of its dependencies are mocks
        $this->getMockedService(BasicService::class)
            ->expects(static::never())
            ->method('process');

        static::assertInstanceOf(MockObject::class, $messenger);
        static::assertSame($messenger, $service->getMessenger());

        $service->expects(static::once())
            ->method('notify')
            ->with('hello')
            ->willReturn(true);

        static::assertTrue($service->notify('hello'));
    }

    public function testPartialMockAcceptsProvidedParameters(): void
    {
        $basic = new BasicService();
        $service = $this->createRealPartialMockedServiceInstance(
            ExtendedService::class,
            ['work'],
            ['service' => $basic]
        );

        $service->expects(static::once())
            ->method('work')
            ->with('hello')
            ->willReturn('OVERRIDDEN');

        static::assertSame('OVERRIDDEN', $service->work('hello'));
    }
}
