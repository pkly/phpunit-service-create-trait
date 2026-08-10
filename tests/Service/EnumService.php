<?php

declare(strict_types=1);

namespace Pkly\Tests\Service;

class EnumService
{
    public function __construct(
        private readonly Mode $mode
    ) {
    }

    public function getMode(): Mode
    {
        return $this->mode;
    }
}
