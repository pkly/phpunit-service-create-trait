<?php

declare(strict_types=1);

namespace Pkly\Tests\Service;

class DuplicateTypeService
{
    public function __construct(
        private readonly BasicService $first,
        private readonly BasicService $second
    ) {
    }

    public function getFirst(): BasicService
    {
        return $this->first;
    }

    public function getSecond(): BasicService
    {
        return $this->second;
    }

    public function process(
        string $input
    ): string|null {
        return $this->second->process($this->first->process($input) ?? $input);
    }
}
