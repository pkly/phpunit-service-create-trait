<?php

declare(strict_types=1);

namespace Pkly\Tests\Service;

interface MessengerInterface
{
    public function send(
        string $message
    ): bool;
}
