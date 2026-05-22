<?php

declare(strict_types=1);

namespace Pkly\Tests\Service;

class ExtendedService
{
    public function __construct(
        private readonly BasicService $service
    ) {
    }

    public function work(
        string $input
    ): string|null {
        return $this->service->process($input);
    }
}
