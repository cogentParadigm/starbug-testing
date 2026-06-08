<?php
namespace Starbug\Testing\Adapter;

use PHPUnit\Framework\Assert;
use Starbug\Db\DatabaseInterface;
use Starbug\Testing\WebAdapter;
use Starbug\Testing\WebDriverInterface;
use Starbug\Testing\Traits\DatabaseAssertions;

/**
 * HTTP-based CRUD adapter base class.
 *
 * Parameterize via $basePath and $entityLabel and the standard
 * CRUD operations work out of the box.
 */
abstract class CrudWebAdapter extends WebAdapter implements CrudAdapterInterface {
  use DatabaseAssertions;

  protected string $basePath = '';
  protected string $entityLabel = '';
  protected string $tableSelector = 'table';
  protected array $expectedColumns = [];

  public function __construct(
    WebDriverInterface $driver,
    protected DatabaseInterface $db
  ) {
    parent::__construct($driver);
  }

  public function create(array $data): void {
    $this->driver->submitForm($this->basePath . '/create', $data);
  }

  public function read(int $id): array {
    $this->visit($this->basePath . '/update/' . $id);
    $form = $this->driver->filterOne('form')->form();
    return $form->getValues();
  }

  public function update(int $id, array $data): void {
    $this->driver->submitForm($this->basePath . '/update/' . $id, $data);
  }

  public function delete(int $id): void {
    $this->driver->submitForm($this->basePath . '/delete/' . $id, []);
  }

  public function list(): void {
    $this->visit($this->basePath);
  }

  public function assertCreated(): void {
    $this->assertPathEquals('/' . $this->basePath);
  }

  public function assertRead(int $id): void {
    $this->assertPathEquals('/' . $this->basePath . '/update/' . $id);
  }

  public function assertUpdated(): void {
    $this->assertPathEquals('/' . $this->basePath);
  }

  public function assertDeleted(): void {
    $this->assertPathEquals('/' . $this->basePath);
  }

  public function assertOnList(): void {
    $this->assertPathEquals('/' . $this->basePath);
    $this->assertPageContains('New ' . $this->entityLabel);
    $this->assertLinkHrefEquals('New ' . $this->entityLabel, $this->basePath . '/create');
    if (!empty($this->expectedColumns)) {
      $this->assertTableColumns($this->tableSelector, $this->expectedColumns);
    }
  }

  public function assertValidationFailed(array $expectedErrors): void {
    $errors = $this->driver->getFormErrors();
    foreach ($expectedErrors as $field => $message) {
      Assert::assertArrayHasKey($field, $errors, "Expected error for field {$field}");
      Assert::assertStringContainsString($message, $errors[$field]);
    }
  }

  protected function getDatabase(): DatabaseInterface {
    return $this->db;
  }

}
