<?php

use Reliese\Dependency\Container;

describe("Container", function () {
	describe("Registered Dependencies", function () {
		it("resolves a registered dependency", function () {
			$container = new Container();

			$container->register(OneSimpleDependency::class, function () {
				return new OneSimpleDependency();
			});

			$resolve = function () use ($container) {
				expect($container->resolve(OneSimpleDependency::class))->toBeAnInstanceOf(OneSimpleDependency::class);
			};

			expect($resolve)->not->toThrow();
		});

		it("resolves a singleton registered dependency", function () {
			$container = new Container();

			$container->singleton(OneSimpleDependency::class, function () {
				return new OneSimpleDependency();
			});

			$resolve = function () use ($container) {
				$firstInstance = $container->resolve(OneSimpleDependency::class);
				expect($firstInstance)->toBeAnInstanceOf(OneSimpleDependency::class);

				$secondInstance = $container->resolve(OneSimpleDependency::class);
				expect($secondInstance)->toBeAnInstanceOf(OneSimpleDependency::class);

				expect($firstInstance)->toBe($secondInstance);
			};

			expect($resolve)->not->toThrow();
		});

		it("resolves an abstract dependency to an instantiable dependency", function() {
			$container = new Container();

			$container->register(RegisteredAbstractDependency::class, function () {
				return new AnotherSimpleDependency();
			});

			$resolve = function () use ($container) {
				expect($container->resolve(RegisteredAbstractDependency::class))->toBeAnInstanceOf(AnotherSimpleDependency::class);
			};

			expect($resolve)->not->toThrow();
		});

		it("resolves a registered dependency on a method call", function () {
			$container = new Container();

			$container->register(OneSimpleDependency::class, function () {
				return new OneSimpleDependency();
			});

			$resolve = function () use ($container) {
				$object = new OneSimpleDependencyWithMethodDependencies();
				expect($container->call($object, 'handle'))->toBeAnInstanceOf(OneSimpleDependency::class);
			};

			$resolve();

//			expect($resolve)->not->toThrow();
		});
	});

	describe("Unregistered Dependencies", function () {
		describe("Unresolvable Dependencies", function () {
			it("cannot resolve an unregistered abstract dependency", function () {
				$container = new Container();

				$resolve = function () use ($container) {
					$container->resolve(NotRegisteredAbstractDependency::class);
				};

				expect($resolve)->toThrow();
			});

			it("cannot resolve a complex dependency when it depends on a not registered interface", function() {
				$container = new Container();

				$resolve = function () use ($container) {
					return $container->resolve(NotResolvableComplexDependency::class);
				};

				expect($resolve)->toThrow();
			});
		});

		describe("Resolvable Dependencies", function () {
			it("resolves a simple dependency", function () {
				$container = new Container();

				$resolve = function () use ($container) {
					expect($container->resolve(OneSimpleDependency::class))->toBeAnInstanceOf(OneSimpleDependency::class);
				};

				expect($resolve)->not->toThrow();
			});

			it("resolves a complex dependency", function () {
				$container = new Container();

				$cases = [
					OneComplexDependency::class,
					ResolvableComplexDependencyWithDefaultValuesInTheMiddle::class
				];

				foreach ($cases as $dependency) {
					$resolve = function () use ($container, $dependency) {
						expect($container->resolve($dependency))->toBeAnInstanceOf($dependency);
					};

					expect($resolve)->not->toThrow();
				}
			});

			it("resolves a complex dependency when it depends on registered abstract dependencies", function () {
				$container = new Container();

				$container->register(RegisteredAbstractDependency::class, function () {
					return new AnotherSimpleDependency();
				});

				$resolve = function () use ($container) {
					expect($container->resolve(AnotherComplexDependency::class))->toBeAnInstanceOf(AnotherComplexDependency::class);
				};

				expect($resolve)->not->toThrow();
			});

			it("resolves a deep complex dependency when it depends on registered abstract dependencies", function () {
				$container = new Container();

				$container->register(RegisteredAbstractDependency::class, function () {
					return new AnotherSimpleDependency();
				});

				$resolve = function () use ($container) {
					$resolved = $container->resolve(DeepComplexDependency::class);
					expect($resolved)->toBeAnInstanceOf(DeepComplexDependency::class);
				};

				expect($resolve)->not->toThrow();
			});
		});

		describe("Dependencies with primitive type params", function () {
			it("cannot resolve complex dependencies when it depends on primitive types", function () {
				$container = new Container();

				$resolve = function () use ($container) {
					$container->resolve(UnresolvableComplexDependency::class);
				};

				expect($resolve)->toThrow();
			});

			it("can resolve complex dependencies when it depends on optional primitive types", function () {
				$container = new Container();

				$container->register(RegisteredAbstractDependency::class, function () {
					return new AnotherSimpleDependency();
				});

				$resolve = function () use ($container) {
					$resolved = $container->resolve(ResolvableComplexDependency::class);
					expect($resolved)->toBeAnInstanceOf(ResolvableComplexDependency::class);
				};

				expect($resolve)->not->toThrow();
			});
		});
	});
});

interface RegisteredAbstractDependency {}
interface NotRegisteredAbstractDependency {};
class OneSimpleDependency {};
class OneSimpleDependencyWithMethodDependencies { public function handle(OneSimpleDependency $a): OneSimpleDependency { return $a; } };
class AnotherSimpleDependency implements RegisteredAbstractDependency {};
class OneComplexDependency { public function __construct(OneSimpleDependency $a, AnotherSimpleDependency $b) {} };
class AnotherComplexDependency { public function __construct(OneSimpleDependency $a, RegisteredAbstractDependency $b) {} };
class NotResolvableComplexDependency { public function __construct(OneSimpleDependency $a, NotRegisteredAbstractDependency $b) {} };
class DeepComplexDependency { public function __construct(AnotherComplexDependency $a, RegisteredAbstractDependency $b){} };
class UnresolvableComplexDependency { public function __construct(AnotherComplexDependency $a, string $b){} };
class ResolvableComplexDependency { public function __construct(AnotherComplexDependency $a, string $b = "hola"){} };
class ResolvableComplexDependencyWithDefaultValuesInTheMiddle { public function __construct(OneSimpleDependency $a, string $b = "hola", OneSimpleDependency $c){} };
