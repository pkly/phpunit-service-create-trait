<?php

declare(strict_types=1);

namespace Pkly;

use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type ServiceState array{
 *     class: class-string,
 *     parameters: list<array{name: string, type: class-string}>,
 *     mocks: array<string, MockObject>,
 *     stubs: array<string, Stub>,
 *     initialized: bool
 * }
 */
trait ServiceMockHelperTrait
{
    /**
     * State of every service instance created by the trait, keyed by the instance itself.
     *
     * @var \SplObjectStorage<object, ServiceState>|null
     */
    private \SplObjectStorage|null $serviceStates = null;

    /**
     * Last created instance of any given service class.
     *
     * @var array<class-string, object>
     */
    private array $serviceInstances = [];

    private object|null $currentServiceInstance = null;

    /**
     * @return \SplObjectStorage<object, ServiceState>
     */
    private function __serviceStates(): \SplObjectStorage
    {
        /** @var \SplObjectStorage<object, ServiceState> $states */
        $states = $this->serviceStates ??= new \SplObjectStorage();

        return $states;
    }

    /**
     * @param class-string $class
     * @param list<array{name: string, type: class-string}> $parameters
     * @param array<string, MockObject> $mocks
     * @param array<string, Stub> $stubs
     */
    private function __registerService(
        object $service,
        string $class,
        array $parameters,
        array $mocks = [],
        array $stubs = [],
        bool $initialized = false
    ): void {
        $this->__serviceStates()->offsetSet($service, [
            'class' => $class,
            'parameters' => $parameters,
            'mocks' => $mocks,
            'stubs' => $stubs,
            'initialized' => $initialized,
        ]);

        $this->serviceInstances[$class] = $service;
        $this->currentServiceInstance = $service;
    }

    /**
     * @return ServiceState
     */
    private function __getServiceState(
        object $service
    ): array {
        $states = $this->__serviceStates();

        if (!$states->offsetExists($service)) {
            throw new \LogicException('The given instance has not been created by the trait');
        }

        return $states->offsetGet($service);
    }

    /**
     * @param ServiceState $state
     */
    private function __setServiceState(
        object $service,
        array $state
    ): void {
        $this->__serviceStates()->offsetSet($service, $state);
    }

    /**
     * @param class-string|null $service
     *
     * @return array{0: object, 1: ServiceState}
     */
    private function __resolveServiceInstance(
        string|null $service
    ): array {
        if (null !== $service) {
            if (!isset($this->serviceInstances[$service])) {
                throw new \LogicException(
                    sprintf(
                        'Service %s has not been created by the trait yet',
                        $service
                    )
                );
            }

            $instance = $this->serviceInstances[$service];
        } else {
            $instance = $this->currentServiceInstance
                ?? throw new \LogicException('No services have been mocked yet by the trait');
        }

        return [$instance, $this->__getServiceState($instance)];
    }

    /**
     * Doubles are addressed by their type, or by their type and parameter name when a specific
     * parameter of an ambiguous (repeated) type has to be targeted.
     */
    private function __doubleKey(
        string $type,
        string|null $parameter
    ): string {
        return null === $parameter ? $type : $type.'$'.$parameter;
    }

    /**
     * @param ServiceState $state
     */
    private function __assertParameterExists(
        array $state,
        string $type,
        string|null $parameter
    ): void {
        foreach ($state['parameters'] as $definition) {
            if ($definition['type'] !== $type) {
                continue;
            }

            if (null === $parameter || $definition['name'] === $parameter) {
                return;
            }
        }

        throw new \LogicException(
            sprintf(
                null === $parameter
                    ? 'Mocked class %s not found in %s, it is either not a dependency of that service or has been provided explicitly'
                    : 'Mocked class %1$s is not the type of parameter $%3$s in %2$s, it is either not a dependency of that service or has been provided explicitly',
                $type,
                $state['class'],
                $parameter ?? ''
            )
        );
    }

    /**
     * @param class-string $class
     */
    private function __parameterType(
        string $class,
        \ReflectionMethod $method,
        \ReflectionParameter $parameter
    ): \ReflectionNamedType {
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

        assert($type instanceof \ReflectionNamedType);

        return $type;
    }

    /**
     * @param class-string $class
     * @param array<string, mixed> $definedParameters
     *
     * @return list<array{name: string, type: class-string}>
     */
    private function __indexMethodParameters(
        string $class,
        \ReflectionMethod $method,
        array $definedParameters
    ): array {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            if (array_key_exists($parameter->getName(), $definedParameters)) {
                continue;
            }

            $type = $this->__parameterType($class, $method, $parameter);

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

                continue;
            }

            /** @var class-string $typeName */
            $typeName = $type->getName();

            if (!class_exists($typeName) && !interface_exists($typeName)) {
                throw new \LogicException(
                    sprintf(
                        'Cannot create a test double for unknown type %s of parameter $%s in %s::%s',
                        $typeName,
                        $parameter->getName(),
                        $class,
                        $method->getName()
                    )
                );
            }

            $typeReflection = new \ReflectionClass($typeName);

            if ($typeReflection->isInternal() || $typeReflection->isEnum()) {
                throw new \LogicException(
                    sprintf(
                        'Specify parameter $%s in %s::%s explicitly, %s is %s and cannot be doubled safely',
                        $parameter->getName(),
                        $class,
                        $method->getName(),
                        $typeName,
                        $typeReflection->isEnum() ? 'an enum' : 'an internal class'
                    )
                );
            }

            $parameters[] = [
                'name' => $parameter->getName(),
                'type' => $typeName,
            ];
        }

        return $parameters;
    }

    /**
     * @param ServiceState $state
     * @param class-string $type
     */
    private function __resolveParameterDouble(
        array &$state,
        string $name,
        string $type,
        bool $asMock
    ): object {
        assert($this instanceof TestCase);

        foreach ([$this->__doubleKey($type, $name), $type] as $key) {
            if (isset($state['mocks'][$key])) {
                return $state['mocks'][$key];
            }

            if (isset($state['stubs'][$key])) {
                return $state['stubs'][$key];
            }
        }

        if ($asMock) {
            return $state['mocks'][$type] = $this->createMock($type);
        }

        return $state['stubs'][$type] = static::createStub($type);
    }

    /**
     * @param ServiceState $state
     * @param array<string, mixed> $definedParameters
     *
     * @return list<mixed>
     */
    private function __resolveMethodParameters(
        array &$state,
        \ReflectionMethod $method,
        array $definedParameters,
        bool $asMock
    ): array {
        /** @var list<mixed> $params */
        $params = [];

        foreach ($method->getParameters() as $parameter) {
            if (array_key_exists($parameter->getName(), $definedParameters)) {
                $params[] = $definedParameters[$parameter->getName()];
                continue;
            }

            $type = $this->__parameterType($state['class'], $method, $parameter);

            if ($type->isBuiltin()) {
                // builtin parameters always have a default value at this point, see __indexMethodParameters()
                $params[] = $parameter->getDefaultValue();
                continue;
            }

            /** @var class-string $typeName */
            $typeName = $type->getName();

            $params[] = $this->__resolveParameterDouble($state, $parameter->getName(), $typeName, $asMock);
        }

        return $params;
    }

    /**
     * @param \ReflectionClass<object> $reflection
     *
     * @return list<\ReflectionMethod>
     */
    private function __getRequiredMethods(
        \ReflectionClass $reflection
    ): array {
        $methods = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ([] === $method->getAttributes(\Symfony\Contracts\Service\Attribute\Required::class)) {
                continue;
            }

            $methods[] = $method;
        }

        return $methods;
    }

    /**
     * Fetch (or declare) a mock for one of the dependencies of a service created by the trait.
     *
     * Declaring a mock must happen before the service is used for the first time, as the service
     * is only built once it is actually touched.
     *
     * @template TMockFetchTarget of object
     *
     * @param class-string<TMockFetchTarget> $class
     * @param string|null $parameter name of the parameter to target, required only when a service
     *                               depends on the same type more than once
     * @param class-string|null $service service to fetch the mock from, defaults to the last one created
     *
     * @return MockObject&TMockFetchTarget
     */
    protected function getMockedService(
        string $class,
        string|null $parameter = null,
        string|null $service = null
    ): mixed {
        assert($this instanceof TestCase);

        [$instance, $state] = $this->__resolveServiceInstance($service);
        $key = $this->__doubleKey($class, $parameter);

        if (isset($state['mocks'][$key])) {
            /** @var MockObject&TMockFetchTarget $mock */
            $mock = $state['mocks'][$key];

            return $mock;
        }

        $this->__assertParameterExists($state, $class, $parameter);

        if ($state['initialized']) {
            throw new \LogicException(
                sprintf(
                    'Service %s has already been created, mock %s cannot be used by it anymore. '
                    .'Call getMockedService() before using the service for the first time.',
                    $state['class'],
                    $class
                )
            );
        }

        $mock = $this->createMock($class);
        $state['mocks'][$key] = $mock;
        $this->__setServiceState($instance, $state);

        /** @var MockObject&TMockFetchTarget $mock */
        return $mock;
    }

    /**
     * Fetch (or declare) a stub for one of the dependencies of a service created by the trait.
     *
     * Dependencies nobody asks for are stubs anyway, this only hands the stub back so return
     * values can be configured on it without turning it into a mock.
     *
     * @template TStubFetchTarget of object
     *
     * @param class-string<TStubFetchTarget> $class
     * @param string|null $parameter name of the parameter to target, required only when a service
     *                               depends on the same type more than once
     * @param class-string|null $service service to fetch the stub from, defaults to the last one created
     *
     * @return Stub&TStubFetchTarget
     */
    protected function getStubbedService(
        string $class,
        string|null $parameter = null,
        string|null $service = null
    ): mixed {
        assert($this instanceof TestCase);

        [$instance, $state] = $this->__resolveServiceInstance($service);

        foreach (array_unique([$this->__doubleKey($class, $parameter), $class]) as $key) {
            if (isset($state['mocks'][$key])) {
                /** @var MockObject&TStubFetchTarget $mock */
                $mock = $state['mocks'][$key];

                return $mock;
            }

            if (isset($state['stubs'][$key])) {
                /** @var Stub&TStubFetchTarget $stub */
                $stub = $state['stubs'][$key];

                return $stub;
            }
        }

        $this->__assertParameterExists($state, $class, $parameter);

        if ($state['initialized']) {
            throw new \LogicException(
                sprintf(
                    'Service %s has already been created and no stub for %s has been used by it',
                    $state['class'],
                    $class
                )
            );
        }

        $stub = static::createStub($class);
        $state['stubs'][$this->__doubleKey($class, $parameter)] = $stub;
        $this->__setServiceState($instance, $state);

        /** @var Stub&TStubFetchTarget $stub */
        return $stub;
    }

    /**
     * Create a real instance of a service with all of its dependencies doubled.
     *
     * The instance is a lazy ghost, its dependencies are resolved the first time the service is
     * actually used. Dependencies asked for via getMockedService() become mocks, everything else
     * becomes a stub, which keeps PHPUnit from complaining about mocks without expectations.
     *
     * @template TMockCreationTarget of object
     *
     * @param class-string<TMockCreationTarget> $class
     * @param array<string, mixed> $constructor
     * @param array<string, mixed> $required
     *
     * @return TMockCreationTarget
     */
    protected function createRealMockedServiceInstance(
        string $class,
        array $constructor = [],
        array $required = []
    ): mixed {
        assert($this instanceof TestCase);

        try {
            $reflection = new \ReflectionClass($class); // @phpstan-ignore-line
        } catch (\ReflectionException $e) { // @phpstan-ignore-line
            throw new \LogicException('Failed to read class reflection, specify proper FQCN', previous: $e);
        }

        $construct = $reflection->getConstructor();
        $requiredMethods = $this->__getRequiredMethods($reflection);
        $parameters = null !== $construct
            ? $this->__indexMethodParameters($class, $construct, $constructor)
            : [];

        foreach ($requiredMethods as $method) {
            foreach ($this->__indexMethodParameters($class, $method, $required) as $parameter) {
                $parameters[] = $parameter;
            }
        }

        try {
            $service = $reflection->newLazyGhost(
                function (object $instance) use ($construct, $requiredMethods, $constructor, $required): void {
                    $state = $this->__getServiceState($instance);
                    $state['initialized'] = true;

                    if (null !== $construct) {
                        $params = $this->__resolveMethodParameters($state, $construct, $constructor, false);
                        $this->__setServiceState($instance, $state);

                        $construct->invoke($instance, ...$params);
                    } else {
                        $this->__setServiceState($instance, $state);
                    }

                    foreach ($requiredMethods as $method) {
                        $params = $this->__resolveMethodParameters($state, $method, $required, false);
                        $this->__setServiceState($instance, $state);

                        $method->invoke($instance, ...$params);
                    }
                }
            );
        } catch (\ReflectionException $e) {
            throw new \LogicException(
                sprintf('Cannot create a lazy instance of %s', $class),
                previous: $e
            );
        }

        $this->__registerService($service, $class, $parameters);

        return $service;
    }

    /**
     * Create a partial mock of a service with all of its dependencies doubled.
     *
     * A partial mock is a generated class and has to be built eagerly, so its dependencies cannot
     * be deferred either - they are all created as mocks, exactly like they used to be.
     *
     * @template TMockCreationPartialTarget of object
     *
     * @param class-string<TMockCreationPartialTarget> $class
     * @param list<non-empty-string> $methods
     * @param array<string, mixed> $constructor
     * @param array<string, mixed> $required
     *
     * @return TMockCreationPartialTarget&MockObject
     */
    protected function createRealPartialMockedServiceInstance(
        string $class,
        array $methods,
        array $constructor = [],
        array $required = []
    ): mixed {
        assert($this instanceof TestCase);

        try {
            $reflection = new \ReflectionClass($class); // @phpstan-ignore-line
        } catch (\ReflectionException $e) { // @phpstan-ignore-line
            throw new \LogicException('Failed to read class reflection, specify proper FQCN', previous: $e);
        }

        $construct = $reflection->getConstructor();
        $requiredMethods = $this->__getRequiredMethods($reflection);

        /** @var ServiceState $state */
        $state = [
            'class' => $class,
            'parameters' => null !== $construct
                ? $this->__indexMethodParameters($class, $construct, $constructor)
                : [],
            'mocks' => [],
            'stubs' => [],
            'initialized' => true,
        ];

        foreach ($requiredMethods as $method) {
            foreach ($this->__indexMethodParameters($class, $method, $required) as $parameter) {
                $state['parameters'][] = $parameter;
            }
        }

        $params = null !== $construct
            ? $this->__resolveMethodParameters($state, $construct, $constructor, true)
            : [];

        $service = new MockBuilder($this, $class)
            ->setConstructorArgs($params)
            ->disableOriginalClone()
            ->onlyMethods($methods)
            ->getMock();

        foreach ($requiredMethods as $method) {
            $service->{$method->getName()}(
                ...$this->__resolveMethodParameters($state, $method, $required, true)
            );
        }

        $this->__registerService(
            $service,
            $class,
            $state['parameters'],
            $state['mocks'],
            $state['stubs'],
            true
        );

        // MockBuilder does not emit an event of its own, unlike createMock()/createStub()
        \PHPUnit\Event\Facade::emitter()->testCreatedPartialMockObject(
            $class,
            ...$methods,
        );

        return $service;
    }
}
