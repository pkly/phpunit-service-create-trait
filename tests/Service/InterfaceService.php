<?php

declare(strict_types=1);

namespace Pkly\Tests\Service;

class InterfaceService
{
    public function __construct(
        private readonly MessengerInterface $messenger,
        private readonly BasicService $service
    ) {
    }

    public function getMessenger(): MessengerInterface
    {
        return $this->messenger;
    }

    public function getService(): BasicService
    {
        return $this->service;
    }

    public function notify(
        string $input
    ): bool {
        $result = $this->service->process($input);

        return null !== $result && $this->messenger->send($result);
    }
}
