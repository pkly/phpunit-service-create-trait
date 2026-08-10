<?php

declare(strict_types=1);

namespace Pkly\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pkly\ServiceMockHelperTrait;
use Pkly\Tests\Service\BasicService;
use Pkly\Tests\Service\DuplicateTypeService;
use Pkly\Tests\Service\EnumService;
use Pkly\Tests\Service\ExtendedService;
use Pkly\Tests\Service\InterfaceService;
use Pkly\Tests\Service\IntervalService;
use Pkly\Tests\Service\MessengerInterface;
use Pkly\Tests\Service\Mode;
use Pkly\Tests\Service\ScalarService;

class MockResolutionTest extends TestCase
{
    use ServiceMockHelperTrait;

    public function testSameTypeParametersShareOneDouble(): void
    {
        $service = $this->createRealMockedServiceInstance(DuplicateTypeService::class);

        $mock = $this->getMockedService(BasicService::class);
        $mock->expects(static::exactly(2))
            ->method('process')
            ->willReturn('SAME');

        static::assertSame('SAME', $service->process('hello'));
        static::assertSame($mock, $service->getFirst());
        static::assertSame($mock, $service->getSecond());
    }

    public function testSameTypeParametersCanBeTargetedByName(): void
    {
        $service = $this->createRealMockedServiceInstance(DuplicateTypeService::class);

        $first = $this->getMockedService(BasicService::class, 'first');
        $second = $this->getMockedService(BasicService::class, 'second');

        static::assertNotSame($first, $second);

        $first->expects(static::once())
            ->method('process')
            ->with('hello')
            ->willReturn('FIRST');

        $second->expects(static::once())
            ->method('process')
            ->with('FIRST')
            ->willReturn('SECOND');

        static::assertSame('SECOND', $service->process('hello'));
        static::assertSame($first, $service->getFirst());
        static::assertSame($second, $service->getSecond());
    }

    public function testMostRecentlyCreatedServiceIsUsedByDefault(): void
    {
        $extended = $this->createRealMockedServiceInstance(ExtendedService::class);
        $interface = $this->createRealMockedServiceInstance(InterfaceService::class);

        $mock = $this->getMockedService(BasicService::class);
        $mock->expects(static::never())
            ->method('process');

        static::assertSame($mock, $interface->getService());

        $other = $this->getMockedService(BasicService::class, service: ExtendedService::class);
        $other->expects(static::once())
            ->method('process')
            ->with('hello')
            ->willReturn('NEW-hello');

        static::assertNotSame($mock, $other);
        static::assertSame('NEW-hello', $extended->work('hello'));
    }

    public function testMockingAnUnknownDependencyThrows(): void
    {
        $this->createRealMockedServiceInstance(ExtendedService::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(MessengerInterface::class);

        $this->getMockedService(MessengerInterface::class);
    }

    public function testMockingBeforeAnyServiceIsCreatedThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No services have been mocked yet by the trait');

        $this->getMockedService(BasicService::class);
    }

    public function testMockingAProvidedParameterThrows(): void
    {
        $this->createRealMockedServiceInstance(ExtendedService::class, ['service' => new BasicService()]);

        $this->expectException(\LogicException::class);

        $this->getMockedService(BasicService::class);
    }

    public function testMockingAfterTheServiceHasBeenUsedThrows(): void
    {
        $service = $this->createRealMockedServiceInstance(ExtendedService::class);

        static::assertNull($service->work('hello'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has already been created');

        $this->getMockedService(BasicService::class);
    }

    public function testInternalDependencyThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is an internal class');

        $this->createRealMockedServiceInstance(IntervalService::class);
    }

    public function testInternalDependencyCanBeProvided(): void
    {
        $interval = new \DateInterval('P1D');
        $service = $this->createRealMockedServiceInstance(IntervalService::class, ['interval' => $interval]);

        static::assertSame($interval, $service->getInterval());
    }

    public function testEnumDependencyThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('is an enum');

        $this->createRealMockedServiceInstance(EnumService::class);
    }

    public function testEnumDependencyCanBeProvided(): void
    {
        $service = $this->createRealMockedServiceInstance(EnumService::class, ['mode' => Mode::Fast]);

        static::assertSame(Mode::Fast, $service->getMode());
    }

    public function testScalarWithoutDefaultValueThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Specify parameter $name');

        $this->createRealMockedServiceInstance(ScalarService::class);
    }

    public function testUnknownClassThrows(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Failed to read class reflection');

        // @phpstan-ignore-next-line
        $this->createRealMockedServiceInstance('Pkly\Tests\Service\ThisDoesNotExist');
    }
}
