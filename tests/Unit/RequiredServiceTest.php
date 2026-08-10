<?php

declare(strict_types=1);

namespace Pkly\Tests\Unit;

use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Pkly\ServiceMockHelperTrait;
use Pkly\Tests\Service\BasicService;
use Pkly\Tests\Service\MessengerInterface;
use Pkly\Tests\Service\RequiredService;

class RequiredServiceTest extends TestCase
{
    use ServiceMockHelperTrait;

    public function testRequiredSetterIsCalledWithADouble(): void
    {
        $service = $this->createRealMockedServiceInstance(RequiredService::class);

        static::assertInstanceOf(MessengerInterface::class, $service->getMessenger());
        static::assertInstanceOf(Stub::class, $service->getMessenger());
    }

    public function testRequiredSetterDoublesCanBeConfiguredAfterCreation(): void
    {
        $service = $this->createRealMockedServiceInstance(RequiredService::class);

        static::assertSame('required-service', $service->describe());

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

    public function testRequiredSetterAcceptsProvidedParameters(): void
    {
        $messenger = new class implements MessengerInterface {
            public function send(
                string $message
            ): bool {
                return 'NEW-hello' === $message;
            }
        };

        $service = $this->createRealMockedServiceInstance(
            RequiredService::class,
            required: ['messenger' => $messenger]
        );

        $this->getStubbedService(BasicService::class)
            ->method('process')
            ->willReturn('NEW-hello');

        static::assertTrue($service->notify('hello'));
        static::assertSame($messenger, $service->getMessenger());
    }

    public function testProvidedRequiredParameterCannotBeMocked(): void
    {
        $messenger = new class implements MessengerInterface {
            public function send(
                string $message
            ): bool {
                return true;
            }
        };

        $this->createRealMockedServiceInstance(
            RequiredService::class,
            required: ['messenger' => $messenger]
        );

        $this->expectException(\LogicException::class);

        $this->getMockedService(MessengerInterface::class);
    }
}
