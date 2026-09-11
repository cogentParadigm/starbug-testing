<?php
namespace Starbug\Testing;

use PHPUnit\Framework\TestCase;
use Starbug\Testing\Traits\ContainerBindings;

/**
 * Base test case.
 *
 * Composes the `ContainerBindings` trait (for #[Bind] attribute support).
 */
abstract class BaseTestCase extends TestCase {

  use ContainerBindings;

  protected function setUp(): void {
    parent::setUp();
    $this->applyBinds();
  }

  protected function tearDown(): void {
    $this->restoreBinds();
    parent::tearDown();
  }
}
