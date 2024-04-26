<?php

namespace Pkly;

use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;

trait ServiceMockHelperTrait
{
    /**
     * @var array<class-string, array<class-string, MockObject>>
     */
    private array $mocks = [];

    /**
     * @param class-string<object> $class
     *
     * @return array{0: MockObject|mixed, 1: class-string|false}
     */
    private function __createMockedServiceParameter(
        string $class,
        \ReflectionParameter $parameter,
        \ReflectionMethod $method
    ): array {
        if (null === ($type = $parameter->getType())) {
            throw new \LogicException(
                sprintf(
                    'Cannot read type of parameter $%s in %s::%s',
                    $parameter->getName(),
                    $class,
                    $method->getName()
                )
            );
        }

        if (method_exists($type, 'getTypes')) {
            if (1 !== count($type->getTypes())) {
                throw new \LogicException(
                    sprintf(
                        'Creating mocks for more than one time at a time are not supported at this time for $%s in %s::%s',
                        $parameter->getName(),
                        $class,
                        $method->getName()
                    )
                );
            }

            $type = $type->getTypes()[0];
        }

        $defaultValue = false;

        if ($type->isBuiltin()) {
            if (!$parameter->isDefaultValueAvailable()) {
                throw new \LogicException(
                    sprintf(
                        'Specify parameter $%s in %s::%s',
                        $parameter->getName(),
                        $class,
                        $method->getName()
                    )
                );
            }

            $defaultValue = $parameter->isDefaultValueAvailable();
        }

        return [
            $defaultValue ? $parameter->getDefaultValue() : $this->createMock($type->getName()),
            $defaultValue ? false : $type->getName(),
        ];
    }

    /**
     * @param class-string $class
     * @param list<mixed> $definedParameters
     *
     * @return list<mixed>
     */
    private function __createAndGetMethodParams(
        string $class,
        \ReflectionMethod $method,
        array $definedParameters
    ): array {
        /** @var list<mixed> $params */
        $params = [];

        foreach ($method->getParameters() as $parameter) {
            if (array_key_exists($parameter->getName(), $definedParameters)) {
                $params[] = $definedParameters[$parameter->getName()];
                continue;
            }

            [$mocked, $type] = $this->__createMockedServiceParameter($class, $parameter, $method);
            $params[] = $mocked;

            if (false === $type) {
                continue;
            }

            $this->mocks[$class][$type] = $mocked;
        }

        return $params;
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|string $class
     * @param class-string<object>|null $service
     *
     * @return MockObject&T
     *
     * @phpstan-ignore-next-line
     */
    protected function getMockedService(
        string $class,
        string|null $service = null
    ): MockObject {
        if (null === $service) {
            reset($this->mocks);
            $service = key($this->mocks);
        }

        if (null === $service) {
            throw new \LogicException('No services have been mocked yet by the trait');
        }

        if (null === ($object = ($this->mocks[$service][$class] ?? null))) {
            throw new \LogicException(
                sprintf(
                    'Mocked class %s not found in %s',
                    $class,
                    $service
                )
            );
        }

        /** @var MockObject&T $object */
        return $object;
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     * @param array<string, mixed> $constructor
     * @param array<string, mixed> $required
     *
     * @return T
     */
    protected function createRealMockedServiceInstance(
        string $class,
        array $constructor = [],
        array $required = []
    ) {
        try {
            $reflection = new \ReflectionClass($class);
            /** @phpstan-ignore-next-line */
        } catch (\ReflectionException $e) {
            throw new \LogicException('Failed to read class reflection, specify proper FQCN', previous: $e);
        }

        // reset instance in memory
        $this->mocks[$class] = [];
        $params = [];

        if (null !== ($construct = $reflection->getConstructor())) {
            $params = $this->__createAndGetMethodParams($class, $construct, $constructor);
        }

        $service = new $class(...$params);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (empty($method->getAttributes(\Symfony\Contracts\Service\Attribute\Required::class))) {
                continue;
            }

            $service->{$method->getName()}(...$this->__createAndGetMethodParams($class, $method, $required));
        }

        return $service;
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     * @param list<string> $methods
     * @param array<string, mixed> $constructor
     * @param array<string, mixed> $required
     *
     * @return T&MockObject
     */
    protected function createRealPartialMockedServiceInstance(
        string $class,
        array $methods,
        array $constructor = [],
        array $required = []
    ) {
        try {
            $reflection = new \ReflectionClass($class);
            /** @phpstan-ignore-next-line */
        } catch (\ReflectionException $e) {
            throw new \LogicException('Failed to read class reflection, specify proper FQCN', previous: $e);
        }

        // reset instance in memory
        $this->mocks[$class] = [];
        $params = [];

        if (null !== ($construct = $reflection->getConstructor())) {
            $params = $this->__createAndGetMethodParams($class, $construct, $constructor);
        }

        $service = (new MockBuilder($this, $class))
            ->setConstructorArgs($params)
            ->disableOriginalClone()
            ->onlyMethods($methods)
            ->getMock();

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (empty($method->getAttributes(\Symfony\Contracts\Service\Attribute\Required::class))) {
                continue;
            }

            $service->{$method->getName()}(...$this->__createAndGetMethodParams($class, $method, $required));
        }

        \PHPUnit\Event\Facade::emitter()->testCreatedPartialMockObject(
            $class,
            ...$methods,
        );

        return $service;
    }
}
