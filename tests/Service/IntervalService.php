<?php

declare(strict_types=1);

namespace Pkly\Tests\Service;

class IntervalService
{
    public function __construct(
        private readonly \DateInterval $interval
    ) {
    }

    public function getInterval(): \DateInterval
    {
        return $this->interval;
    }
}
