<?php

declare(strict_types=1);

namespace Pkly\Tests\Service;

class NoConstructorService
{
    private string $value = 'no-constructor';

    public function getValue(): string
    {
        return $this->value;
    }
}
