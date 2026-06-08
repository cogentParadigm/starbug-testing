<?php
namespace Starbug\Testing\Domain;

use Starbug\Testing\Adapter\CrudAdapterInterface;

/**
 * Generic CRUD domain layer.
 *
 * Wraps a CrudAdapterInterface into test-friendly operations
 * with sensible defaults and built-in assertions.
 */
class Crud {

  public function __construct(
    protected CrudAdapterInterface $adapter
  ) {
  }

  /**
   * Create a record.
   *
   * @param bool $expectSuccess Whether to assert the operation succeeded.
   * @return array The created record if expectSuccess is true, otherwise empty.
   */
  public function create(array $data, bool $expectSuccess = true): array {
    $this->adapter->create($data);
    if ($expectSuccess) {
      return $this->adapter->assertCreated();
    }
    return [];
  }

  /**
   * Read a single record by ID.
   *
   * @return array Associative array of field values.
   */
  public function read(int $id): array {
    $record = $this->adapter->read($id);
    $this->adapter->assertRead($id);
    return $record;
  }

  /**
   * Update a record.
   *
   * @param bool $expectSuccess Whether to assert the operation succeeded.
   */
  public function update(int $id, array $data, bool $expectSuccess = true): void {
    $this->adapter->update($id, $data);
    if ($expectSuccess) {
      $this->adapter->assertUpdated();
    }
  }

  /**
   * Delete a record.
   *
   * @param bool $expectSuccess Whether to assert the operation succeeded.
   */
  public function delete(int $id, bool $expectSuccess = true): void {
    $this->adapter->delete($id);
    if ($expectSuccess) {
      $this->adapter->assertDeleted();
    }
  }

  /**
   * Navigate to or retrieve the list.
   */
  public function list(): void {
    $this->adapter->list();
    $this->adapter->assertOnList();
  }

  /**
   * Assert the last create operation succeeded.
   *
   * @return array The created record.
   */
  public function assertCreated(): array {
    return $this->adapter->assertCreated();
  }

  /**
   * Assert the last update operation succeeded.
   */
  public function assertUpdated(): void {
    $this->adapter->assertUpdated();
  }

  /**
   * Assert the last delete operation succeeded.
   */
  public function assertDeleted(): void {
    $this->adapter->assertDeleted();
  }

  /**
   * Assert the current view is the list.
   */
  public function assertOnList(): void {
    $this->adapter->assertOnList();
  }

  /**
   * Assert the last create operation failed with expected validation errors.
   *
   * @param array $expectedErrors Map of field name => error message fragment.
   */
  public function assertValidationFailed(array $expectedErrors): void {
    $this->adapter->assertValidationFailed($expectedErrors);
  }

}
