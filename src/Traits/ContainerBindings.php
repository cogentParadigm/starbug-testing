<?php
namespace Starbug\Testing\Traits;

use DI\Container;
use ReflectionNamedType;
use ReflectionUnionType;
use ReflectionIntersectionType;
use ReflectionClass;
use ReflectionProperty;
use PHPUnit\Framework\TestCase;
use Starbug\Testing\Attribute\Bind;

/**
 * Container binding overrides for test cases.
 *
 * Scans the test class for #[Bind] attributes, overrides the container
 * before injection, and restores the original state in tearDown.
 */
trait ContainerBindings {

  /**
   * Tracks container state overridden by #[Bind] so it can be restored.
   */
  private array $bindRestorations = [];

  private const NOT_RESOLVED = 'starbug.testing.bindings.not-resolved';

  /**
   * Apply class-level container binding overrides and inject dependencies.
   *
   * Call this in setUp() before interacting with injected services.
   */
  protected function applyBinds(): void {
    global $container;
    $reflection = new ReflectionClass($this);
    $binds = [];
    do {
      $attributes = $reflection->getAttributes(Bind::class);
      foreach ($attributes as $attribute) {
        $binds[] = $attribute->newInstance();
      }
      $reflection = $reflection->getParentClass();
    } while ($reflection && $reflection->getName() !== TestCase::class);

    if (!empty($binds)) {
      $resolvedProp = new ReflectionProperty($container, 'resolvedEntries');
      $resolvedProp->setAccessible(true);
      $originalEntries = $resolvedProp->getValue($container);

      foreach (array_reverse($binds) as $bind) {
        $interface = $bind->interface;

        if (array_key_exists($interface, $originalEntries)) {
          $this->bindRestorations[$interface] = $originalEntries[$interface];
        } else {
          $this->bindRestorations[$interface] = self::NOT_RESOLVED;
        }

        $container->set($interface, $container->get($bind->implementation));
      }

      // Invalidate cached objects whose constructors (directly) accept any
      // of the bound interfaces so that injectOn creates fresh instances.
      $currentEntries = $resolvedProp->getValue($container);
      $boundInterfaces = array_map(fn($b) => $b->interface, $binds);
      foreach ($originalEntries as $name => $value) {
        if ($value instanceof Container || !is_object($value)) {
          continue;
        }
        $rc = new ReflectionClass(get_class($value));
        $constructor = $rc->getConstructor();
        if (!$constructor) {
          continue;
        }
        foreach ($constructor->getParameters() as $param) {
          $type = $param->getType();
          if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && in_array($type->getName(), $boundInterfaces, true)) {
            unset($currentEntries[$name]);
            break;
          }
          if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $innerType) {
              if ($innerType instanceof ReflectionNamedType && !$innerType->isBuiltin() && in_array($innerType->getName(), $boundInterfaces, true)) {
                unset($currentEntries[$name]);
                break 2;
              }
            }
          }
        }
      }
      $resolvedProp->setValue($container, $currentEntries);
    }

    $container->injectOn($this);
  }

  /**
   * Restore container bindings overridden by applyBinds().
   *
   * Call this in tearDown().
   */
  protected function restoreBinds(): void {
    if (empty($this->bindRestorations)) {
      return;
    }

    global $container;
    $resolvedProp = new ReflectionProperty($container, 'resolvedEntries');
    $resolvedProp->setAccessible(true);
    $resolvedEntries = $resolvedProp->getValue($container);

    foreach ($this->bindRestorations as $interface => $value) {
      if ($value === self::NOT_RESOLVED) {
        unset($resolvedEntries[$interface]);
      } else {
        $resolvedEntries[$interface] = $value;
      }
    }

    $resolvedProp->setValue($container, $resolvedEntries);
    $this->bindRestorations = [];
  }
}
