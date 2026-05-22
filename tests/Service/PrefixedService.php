<?php

declare(strict_types=1);

namespace Pkly\Tests\Service;

class PrefixedService
{
    public function __construct(
        private readonly BasicService $service,
        private readonly string $prefix = 'DEFAULT'
    ) {
    }

    public function format(
        string $input
    ): string|null {
        $result = $this->service->process($input);

        return null !== $result ? $this->prefix.'-'.$result : null;
    }
}