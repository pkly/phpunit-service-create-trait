<?php

declare(strict_types=1);

namespace Pkly\Tests\Service;

class NullableService
{
    public function __construct(
        private readonly BasicService|null $service
    ) {
    }

    public function getService(): BasicService|null
    {
        return $this->service;
    }
}
