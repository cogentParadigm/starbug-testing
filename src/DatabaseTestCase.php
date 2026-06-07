<?php
namespace Starbug\Testing;

use PHPUnit\Framework\TestCase;
use Starbug\Testing\Traits\ContainerBindings;
use Starbug\Testing\Traits\Database;

abstract class DatabaseTestCase extends TestCase {

  use ContainerBindings, Database;

  protected function setUp(): void {
    parent::setUp();
    $this->applyBinds();
    $this->databaseSetup();
  }

  protected function tearDown(): void {
    $this->restoreBinds();
    parent::tearDown();
  }
}
