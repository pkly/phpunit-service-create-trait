<?php

declare(strict_types=1);

namespace Pkly;

use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Framework\MockObject\Generator\Generator as MockGenerator;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use PHPUnit\Metadata\Parser\Registry as MetadataRegistry;

/**
 * @phpstan-type ServiceState array{
 *     class: class-string,
 *     parameters: list<array{name: string, type: class-string}>,
 *     doubles: array<string, MockObject>,
 *     registered: array<string, true>
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
     * @param ServiceState $state
     */
    private function __registerService(
        object $service,
        array $state
    ): void {
        $this->__serviceStates()->offsetSet($service, $state);

        $this->serviceInstances[$state['class']] = $service;
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
     * Locate the double for a type within one service's state.
     *
     * @param ServiceState $state
     *
     * @return string|null the key it is stored under, or null when that service has no such dependency
     */
    private function __findDoubleKey(
        array $state,
        string $type,
        string|null $parameter
    ): string|null {
        if (null !== $parameter) {
            $key = $this->__doubleKey($type, $parameter);

            return isset($state['doubles'][$key]) ? $key : null;
        }

        $found = null;

        foreach ($state['parameters'] as $definition) {
            if ($definition['type'] !== $type) {
                continue;
            }

            $key = $this->__doubleKey($type, $definition['name']);

            if (!isset($state['doubles'][$key])) {
                continue;
            }

            if (null !== $found) {
                throw new \LogicException(
                    sprintf(
                        'Service %s depends on %s more than once, pass the parameter name to target one of them',
                        $state['class'],
                        $type
                    )
                );
            }

            $found = $key;
        }

        return $found;
    }

    /**
     * Resolve which service instance a double should be taken from.
     *
     * Defaults to the most recently created service. When that service has no such dependency the
     * other services created by the trait are searched, so that building a second object mid-test
     * does not hide the dependencies of the one actually under test.
     *
     * @param class-string $type
     * @param class-string|null $service
     *
     * @return array{0: object, 1: ServiceState, 2: string}
     */
    private function __resolveDouble(
        string $type,
        string|null $parameter,
        string|null $service
    ): array {
        if (null !== $service) {
            if (!isset($this->serviceInstances[$service])) {
                throw new \LogicException(
                    sprintf('Service %s has not been created by the trait yet', $service)
                );
            }

            $instance = $this->serviceInstances[$service];
            $state = $this->__getServiceState($instance);
            $key = $this->__findDoubleKey($state, $type, $parameter);

            if (null === $key) {
                throw $this->__unknownDependency($state, $type, $parameter);
            }

            return [$instance, $state, $key];
        }

        $instance = $this->currentServiceInstance
            ?? throw new \LogicException('No services have been mocked yet by the trait');
        $state = $this->__getServiceState($instance);

        if (null !== ($key = $this->__findDoubleKey($state, $type, $parameter))) {
            return [$instance, $state, $key];
        }

        // fall back to the other services created by the trait, but only when unambiguous
        $matches = [];

        foreach ($this->serviceInstances as $candidate) {
            if ($candidate === $instance) {
                continue;
            }

            $candidateState = $this->__getServiceState($candidate);

            if (null !== ($candidateKey = $this->__findDoubleKey($candidateState, $type, $parameter))) {
                $matches[] = [$candidate, $candidateState, $candidateKey];
            }
        }

        if (1 === count($matches)) {
            return $matches[0];
        }

        if ([] !== $matches) {
            throw new \LogicException(
                sprintf(
                    'Multiple services created by the trait depend on %s, pass the service name to target one of them',
                    $type
                )
            );
        }

        throw $this->__unknownDependency($state, $type, $parameter);
    }

    /**
     * @param ServiceState $state
     */
    private function __unknownDependency(
        array $state,
        string $type,
        string|null $parameter
    ): \LogicException {
        return new \LogicException(
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
     * Whether return values should be generated for the doubles this trait creates.
     *
     * Mirrors TestCase::generateReturnValuesForTestDoubles(), which is private.
     */
    private function __generateReturnValues(): bool
    {
        return MetadataRegistry::parser()
            ->forClass(static::class)
            ->isDisableReturnValueGenerationForTestDoubles()
            ->isEmpty();
    }

    /**
     * Create a test double that is deliberately NOT registered with the TestCase.
     *
     * It is a full MockObject, so expects() is available on it, but PHPUnit neither verifies it nor
     * complains about it having no expectations. It is registered later, on the first
     * getMockedService() call for it, which is the point at which the test takes ownership of it.
     *
     * @param class-string $type
     */
    private function __createUnregisteredMock(
        string $type
    ): MockObject {
        $double = new MockGenerator()->testDouble(
            $type,
            true,
            callOriginalConstructor: false,
            callOriginalClone: false,
            returnValueGeneration: $this->__generateReturnValues(),
        );

        assert($double instanceof MockObject);

        return $double;
    }

    /**
     * @param class-string $class
     * @param array<string, mixed> $definedParameters
     * @param ServiceState $state
     *
     * @return list<mixed>
     */
    private function __resolveMethodParameters(
        string $class,
        \ReflectionMethod $method,
        array $definedParameters,
        array &$state
    ): array {
        /** @var list<mixed> $params */
        $params = [];

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $definedParameters)) {
                $params[] = $definedParameters[$name];
                continue;
            }

            $type = $this->__parameterType($class, $method, $parameter);

            // only builtin parameters fall back to their default; a class-typed parameter is
            // doubled even when it is nullable with a default, because tests routinely mock those
            if ($type->isBuiltin()) {
                if (!$parameter->isDefaultValueAvailable()) {
                    throw new \LogicException(
                        sprintf(
                            'Specify parameter $%s in %s::%s',
                            $name,
                            $class,
                            $method->getName()
                        )
                    );
                }

                $params[] = $parameter->getDefaultValue();
                continue;
            }

            /** @var class-string $typeName */
            $typeName = $type->getName();

            if (!class_exists($typeName) && !interface_exists($typeName)) {
                throw new \LogicException(
                    sprintf(
                        'Cannot create a test double for unknown type %s of parameter $%s in %s::%s',
                        $typeName,
                        $name,
                        $class,
                        $method->getName()
                    )
                );
            }

            // enums cannot be doubled at all; internal classes generally can be, so they are left
            // to PHPUnit, which raises a precise error for the ones it cannot handle
            if (new \ReflectionClass($typeName)->isEnum()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $params[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new \LogicException(
                    sprintf(
                        'Specify parameter $%s in %s::%s explicitly, %s is an enum and cannot be doubled',
                        $name,
                        $class,
                        $method->getName(),
                        $typeName
                    )
                );
            }

            $key = $this->__doubleKey($typeName, $name);
            $double = $state['doubles'][$key] ??= $this->__createUnregisteredMock($typeName);

            $state['parameters'][] = [
                'name' => $name,
                'type' => $typeName,
            ];

            $params[] = $double;
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
     * Fetch a mock for one of the dependencies of a service created by the trait.
     *
     * This is what hands ownership of the double to the test: from here on PHPUnit verifies it and
     * will point out that it has no expectations configured. Dependencies nobody fetches stay
     * unregistered and are silently left alone.
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

        [$instance, $state, $key] = $this->__resolveDouble($class, $parameter, $service);
        $double = $state['doubles'][$key];

        if (!isset($state['registered'][$key])) {
            $this->registerMockObject($class, $double);
            EventFacade::emitter()->testCreatedMockObject($class);

            $state['registered'][$key] = true;
            $this->__setServiceState($instance, $state);
        }

        /** @var MockObject&TMockFetchTarget $double */
        return $double;
    }

    /**
     * Fetch a dependency of a service created by the trait without taking ownership of it.
     *
     * The double is returned unregistered, so return values can be configured on it while PHPUnit
     * keeps ignoring it - no verification, and no complaint about missing expectations.
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

        [, $state, $key] = $this->__resolveDouble($class, $parameter, $service);

        /** @var Stub&TStubFetchTarget $double */
        $double = $state['doubles'][$key];

        return $double;
    }

    /**
     * Create a real instance of a service with all of its dependencies doubled.
     *
     * Dependencies are created as unregistered mocks: fully configurable, but invisible to PHPUnit
     * until the test asks for one with getMockedService(). That keeps PHPUnit from complaining
     * about the dependencies a test never touches, without constraining when the test configures
     * the ones it cares about.
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

        /** @var ServiceState $state */
        $state = [
            'class' => $class,
            'parameters' => [],
            'doubles' => [],
            'registered' => [],
        ];

        $params = null !== ($construct = $reflection->getConstructor())
            ? $this->__resolveMethodParameters($class, $construct, $constructor, $state)
            : [];

        $service = new $class(...$params);

        foreach ($this->__getRequiredMethods($reflection) as $method) {
            $service->{$method->getName()}(
                ...$this->__resolveMethodParameters($class, $method, $required, $state)
            );
        }

        $this->__registerService($service, $state);

        return $service;
    }

    /**
     * Create a partial mock of a service with all of its dependencies doubled.
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

        /** @var ServiceState $state */
        $state = [
            'class' => $class,
            'parameters' => [],
            'doubles' => [],
            'registered' => [],
        ];

        $params = null !== ($construct = $reflection->getConstructor())
            ? $this->__resolveMethodParameters($class, $construct, $constructor, $state)
            : [];

        $service = new MockBuilder($this, $class)
            ->setConstructorArgs($params)
            ->disableOriginalClone()
            ->onlyMethods($methods)
            ->getMock();

        foreach ($this->__getRequiredMethods($reflection) as $method) {
            $service->{$method->getName()}(
                ...$this->__resolveMethodParameters($class, $method, $required, $state)
            );
        }

        $this->__registerService($service, $state);

        // MockBuilder does not emit an event of its own, unlike createMock()/createStub()
        EventFacade::emitter()->testCreatedPartialMockObject(
            $class,
            ...$methods,
        );

        return $service;
    }
}