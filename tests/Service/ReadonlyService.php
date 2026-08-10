<?php

declare(strict_types=1);

namespace Pkly\Tests\Service;

final readonly class ReadonlyService
{
    public function __construct(
        public BasicService $service,
        public string $prefix = 'READONLY'
    ) {
    }

    public function describe(
        string $input
    ): string|null {
        $result = $this->service->process($input);

        return null !== $result ? $this->prefix.'-'.$result : null;
    }
}
