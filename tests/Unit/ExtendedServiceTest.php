<?php

declare(strict_types=1);

namespace Pkly\Tests\Unit;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Pkly\ServiceMockHelperTrait;
use Pkly\Tests\Service\BasicService;
use Pkly\Tests\Service\ExtendedService;

class ExtendedServiceTest extends TestCase
{
    use ServiceMockHelperTrait;

    private ExtendedService $service;

    #[\Override]
    protected function setUp(): void
    {
        $this->service = $this->createRealMockedServiceInstance(ExtendedService::class);
    }

    #[TestWith(['hello', 'NEW-hello'])]
    #[TestWith(['world', 'NEW-world'])]
    #[TestWith(['foo bar', 'NEW-foo bar'])]
    #[TestWith(['123', 'NEW-123'])]
    #[TestWith(['', null])]
    public function testWork(
        string $input,
        string|null $expected
    ): void {
        $this->getMockedService(BasicService::class)
            ->expects(static::once())
            ->method('process')
            ->with($input)
            ->willReturn($expected);

        static::assertSame($expected, $this->service->work($input));
    }
}
