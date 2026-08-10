<?php

declare(strict_types=1);

namespace Pkly\Tests\Service;

use Symfony\Contracts\Service\Attribute\Required;

class RequiredService
{
    private MessengerInterface $messenger;

    public function __construct(
        private readonly BasicService $service
    ) {
    }

    #[Required]
    public function setMessenger(
        MessengerInterface $messenger
    ): void {
        $this->messenger = $messenger;
    }

    public function getMessenger(): MessengerInterface
    {
        return $this->messenger;
    }

    /**
     * Deliberately touches no properties, so calling it does not initialize a lazy instance.
     */
    public function describe(): string
    {
        return 'required-service';
    }

    public function notify(
        string $input
    ): bool {
        $result = $this->service->process($input);

        return null !== $result && $this->messenger->send($result);
    }
}
