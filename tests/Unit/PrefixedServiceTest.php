<?php

declare(strict_types=1);

namespace Pkly\Tests\Unit;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Pkly\ServiceMockHelperTrait;
use Pkly\Tests\Service\BasicService;
use Pkly\Tests\Service\PrefixedService;

class PrefixedServiceTest extends TestCase
{
    use ServiceMockHelperTrait;

    #[TestWith(['hello', 'NEW-hello', 'CUSTOM-NEW-hello'])]
    #[TestWith(['world', 'NEW-world', 'CUSTOM-NEW-world'])]
    #[TestWith(['foo', 'NEW-foo', 'CUSTOM-NEW-foo'])]
    public function testFormatWithCustomPrefix(
        string $input,
        string $processed,
        string $expected
    ): void {
        $service = $this->createRealMockedServiceInstance(PrefixedService::class, ['prefix' => 'CUSTOM']);

        $this->getMockedService(BasicService::class)
            ->expects(static::once())
            ->method('process')
            ->with($input)
            ->willReturn($processed);

        static::assertSame($expected, $service->format($input));
    }

    public function testFormatUsesDefaultPrefix(): void
    {
        $service = $this->createRealMockedServiceInstance(PrefixedService::class);

        $this->getMockedService(BasicService::class)
            ->expects(static::once())
            ->method('process')
            ->with('hello')
            ->willReturn('NEW-hello');

        static::assertSame('DEFAULT-NEW-hello', $service->format('hello'));
    }

    public function testFormatReturnsNullWhenProcessReturnsNull(): void
    {
        $service = $this->createRealMockedServiceInstance(PrefixedService::class);

        $this->getMockedService(BasicService::class)
            ->expects(static::once())
            ->method('process')
            ->with('')
            ->willReturn(null);

        static::assertNull($service->format(''));
    }
}
