<?php
namespace Starbug\Testing;

use ReflectionClass;
use PHPUnit\Framework\TestCase;
use Starbug\Db\DatabaseInterface;
use Starbug\Db\Operation\Migrate;
use Starbug\Imports\Import;
use Starbug\Imports\Importer;
use Starbug\Imports\Read\YamlFixtureStrategy;
use Starbug\Imports\Write\FixtureStrategy;
use Starbug\Testing\Attribute\Fixture;
use Starbug\Testing\Traits\ContainerBindings;

abstract class DatabaseTestCase extends TestCase {

  use ContainerBindings;

  protected $importer;
  protected $operation;
  protected DatabaseInterface $db;

  protected function setUp(): void {
    parent::setUp();
    global $container;
    $this->applyBinds();
    $this->getDatabase()->errors->set([]);
    if ($imports = $this->getDataSets()) {
      foreach ($imports as $import) {
        $this->getImporter()->run($import);
      }
    }
  }

  protected function tearDown(): void {
    $this->restoreBinds();
    parent::tearDown();
  }

  protected function getImporter() {
    if (empty($this->importer)) {
      global $container;
      $this->importer = $container->get(Importer::class);
    }
    return $this->importer;
  }

  protected function getDataSets() {
    $reflection = new ReflectionClass($this);
    $imports = [];
    do {
      $attributes = $reflection->getAttributes(Fixture::class);
      foreach ($attributes as $attribute) {
        $fixture = $attribute->newInstance();
        if ($fixture->type === 'yaml') {
          $imports[] = $this->createYamlDataSet($fixture->path);
        }
      }
      $reflection = $reflection->getParentClass();
    } while ($reflection && $reflection->getName() !== TestCase::class);

    return empty($imports) ? false : array_reverse($imports);
  }

  protected function createYamlDataSet($ymlFile) {
    $import = new Import(false);
    $import->setReadStrategy(YamlFixtureStrategy::class, ["path" => $ymlFile]);
    $import->setWriteStrategy(FixtureStrategy::class, ["operation" => $this->getOperation()]);
    return $import;
  }

  protected function getOperation() {
    if (empty($this->operation)) {
      global $container;
      $this->operation = $container->get(Migrate::class);
    }
    return $this->operation;
  }

  protected function getDatabase() {
    if (empty($this->db)) {
      global $container;
      $this->db = $container->get(DatabaseInterface::class);
    }
    return $this->db;
  }
}
