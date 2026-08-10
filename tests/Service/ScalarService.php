<?php

declare(strict_types=1);

namespace Pkly\Tests\Service;

class ScalarService
{
    public function __construct(
        private readonly BasicService $service,
        private readonly string $name
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function process(
        string $input
    ): string|null {
        $result = $this->service->process($input);

        return null !== $result ? $this->name.':'.$result : null;
    }
}
