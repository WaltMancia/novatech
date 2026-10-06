<?php
declare(strict_types=1);

namespace NovaTech\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/** Contenedor de inyección de dependencias con autowiring por constructor. */
final class Container
{
    /** @var array<string, Closure(Container): object> */
    private array $factories = [];
    /** @var array<string, bool> */
    private array $shared = [];
    /** @var array<string, object> */
    private array $instances = [];
    /** @var array<string, true> */
    private array $resolving = [];

    /** @param Closure(Container): object $factory */
    public function singleton(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = true;
        unset($this->instances[$id]);
    }

    /** @param Closure(Container): object $factory */
    public function bind(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = false;
    }

    public function instance(string $id, object $object): void
    {
        $this->instances[$id] = $object;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]) || class_exists($id);
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (isset($this->factories[$id])) {
            $object = ($this->factories[$id])($this);
            if ($this->shared[$id]) {
                $this->instances[$id] = $object;
            }
            return $object;
        }
        return $this->autowire($id);
    }

    private function autowire(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("No se puede resolver '{$class}' en el contenedor.");
        }
        if (isset($this->resolving[$class])) {
            throw new RuntimeException("Dependencia circular detectada en '{$class}'.");
        }
        $this->resolving[$class] = true;

        try {
            $reflection = new ReflectionClass($class);
            if (!$reflection->isInstantiable()) {
                throw new RuntimeException("'{$class}' no es instanciable; regístrala en el contenedor.");
            }
            $args = [];
            foreach ($reflection->getConstructor()?->getParameters() ?? [] as $param) {
                $type = $param->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $args[] = $this->get($type->getName());
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } elseif ($type !== null && $type->allowsNull()) {
                    $args[] = null;
                } else {
                    throw new RuntimeException("No se puede resolver el parámetro \${$param->getName()} de '{$class}'.");
                }
            }
            return $reflection->newInstanceArgs($args);
        } finally {
            unset($this->resolving[$class]);
        }
    }
}
