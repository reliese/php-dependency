<?php

namespace Reliese\Dependency;

use Closure;
use Exception;
use Reliese\Component\Dependency\Container as ContainerComponent;
use Reliese\Component\Dependency\Exceptions\UnresolvableDependencyException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

class Container implements ContainerComponent
{
	/**
	 * @var Closure[]
	 */
	protected $dependencies = [];

	/**
	 * @var array
	 */
	private $singletons = [];

	/**
	 * @param $dependency
	 * @param Closure $abstraction
	 *
	 * @return ContainerComponent
	 */
	public function singleton(string $dependency, Closure $abstraction) : ContainerComponent
	{
		return $this->register($dependency, function (ContainerComponent $container) use ($dependency, $abstraction) {
			if (array_key_exists($dependency, $this->singletons)) {
				return $this->singletons[$dependency];
			}

			return $this->singletons[$dependency] = $abstraction($container);
		});
	}

	/**
	 * @param $dependency
	 * @param Closure $abstraction
	 *
	 * @return ContainerComponent
	 */
	public function register(string $dependency, Closure $abstraction) : ContainerComponent
	{
		$this->dependencies[$dependency] = $abstraction;

		return $this;
	}

	/**
	 * @param string $dependency
	 *
	 * @return mixed
	 * @throws UnresolvableDependencyException
	 */
	public function resolve(string $dependency)
	{
		if (!$this->canResolve($dependency)) {
			throw new UnresolvableDependencyException($dependency);
		}

		$dependency = $this->dependencies[$dependency];

		return $dependency($this);
	}

	/**
	 * @param string $dependency
	 *
	 * @return bool
	 */
	protected function canResolve(string $dependency) : bool
	{
		if ($this->isRegistered($dependency)) {
			return true;
		}

		return $this->canBeInferred($dependency);
	}

	/**
	 * @param string $dependency
	 *
	 * @return bool
	 */
	protected function isRegistered(string $dependency) : bool
	{
		return array_key_exists($dependency, $this->dependencies);
	}

	/**
	 * @param string $dependency
	 *
	 * @return bool
	 */
	protected function canBeInferred(string $dependency) : bool
	{
		try {
			return $this->canResolveAndRegisterUsingReflection($dependency);
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * @param string $dependency
	 *
	 * @return bool
	 * @throws UnresolvableDependencyException
	 * @throws ReflectionException
	 */
	protected function canResolveAndRegisterUsingReflection(string $dependency) : bool
	{
		$definition = new ReflectionClass($dependency);

		// If the dependency is not instantiable, such as an Interface or
		// an Abstract Class, we won't be able to resolve it.
		if (!$definition->IsInstantiable()) {
			return false;
		}

		$parameters = $this->resolveConstructorDependencies($definition);

		$this->register($dependency, function () use ($definition, $parameters) {
			return $definition->newInstanceArgs($parameters);
		});

		return true;
	}

	/**
	 * @param ReflectionClass $reflector
	 *
	 * @return array
	 * @throws UnresolvableDependencyException
	 * @throws ReflectionException
	 */
	protected function resolveConstructorDependencies(ReflectionClass $reflector)
	{
		$constructor = $reflector->getConstructor();

		// If there are no constructors, it means there are no dependencies.
		if (is_null($constructor)) {
			return [];
		}

		return $this->resolveParameters($constructor->getParameters());
	}

	/**
	 * @param ReflectionParameter[] $parameters
	 *
	 * @return array
	 * @throws UnresolvableDependencyException
	 * @throws ReflectionException
	 */
	protected function resolveParameters(array $parameters)
	{
		$resolved = [];

		foreach ($parameters as $parameter) {
			$resolved[] = $this->resolveParameter($parameter);
		}

		return $resolved;
	}

	/**
	 * @param ReflectionParameter $parameter
	 *
	 * @return mixed
	 * @throws UnresolvableDependencyException
	 * @throws ReflectionException
	 */
	protected function resolveParameter(ReflectionParameter $parameter)
	{
		if ($this->parameterIsAnObject($parameter)) {
			return $this->resolve($parameter->getClass()->name);
		}

		if ($this->parameterHasDefaultValue($parameter)) {
			return $parameter->getDefaultValue();
		}

		throw new UnresolvableDependencyException($parameter->getName());
	}

	/**
	 * @param ReflectionParameter $parameter
	 *
	 * @return bool
	 */
	protected function parameterIsAnObject(ReflectionParameter $parameter) : bool
	{
		return !is_null($parameter->getClass());
	}

	/**
	 * @param ReflectionParameter $parameter
	 *
	 * @return bool
	 */
	protected function parameterHasDefaultValue(ReflectionParameter $parameter) : bool
	{
		return $parameter->isDefaultValueAvailable();
	}

	/**
	 * @param object $object
	 * @param string $method
	 *
	 * @return mixed
	 * @throws UnresolvableDependencyException
	 */
	public function call(object $object, string $method)
	{
		try {
			$callee = new \ReflectionMethod($object, $method);

			$parameters = $this->resolveParameters($callee->getParameters());

			return $callee->invokeArgs($object, $parameters);
		} catch (Exception $e) {
			throw new UnresolvableDependencyException("Unable to call [$method]", 0, $e);
		}
	}
}
