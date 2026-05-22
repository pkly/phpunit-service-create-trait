<?php

declare(strict_types=1);

namespace Pkly\Tests\Service;

class BasicService
{
    public function process(
        string $input
    ): string|null {
        return mb_strlen($input) ? 'NEW-'.$input : null;
    }
}
