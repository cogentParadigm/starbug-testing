<?php
namespace Starbug\Testing\Attribute;

use Attribute;

/**
 * Override a container binding for the duration of a test class.
 *
 * Applied to the test class itself. Before each test, the container
 * will resolve the given interface to the specified implementation
 * instead of its default binding. The original binding is restored
 * in tearDown so it does not leak to subsequent tests.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Bind {

  public function __construct(
    public readonly string $interface,
    public readonly string $implementation
  ) {
  }
}
