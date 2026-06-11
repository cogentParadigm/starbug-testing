<?php
namespace Starbug\Testing\Domain;

use PHPUnit\Framework\Assert;
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
   * Create a record and assert the returned record matches expected fields.
   *
   * @param array $matchData Fields that must match in the returned record.
   * @param array $storeOnlyData Fields to include in the create but not match.
   * @return array The created record.
   */
  public function createAndMatch(array $matchData, array $storeOnlyData = []): array {
    $record = $this->create($matchData + $storeOnlyData);
    foreach ($matchData as $field => $expected) {
      Assert::assertArrayHasKey($field, $record, "Created record missing expected field '{$field}'.");
      Assert::assertEquals($expected, $record[$field], "Expected created record field '{$field}' to equal '{$expected}', got '{$record[$field]}'.");
    }
    return $record;
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
   * @return array The updated record if expectSuccess is true, otherwise empty.
   */
  public function update(int $id, array $data, bool $expectSuccess = true): array {
    $this->adapter->update($id, $data);
    if ($expectSuccess) {
      return $this->adapter->assertUpdated();
    }
    return [];
  }

  /**
   * Update a record and assert the returned record matches expected fields.
   *
   * @param int $id The record ID.
   * @param array $matchData Fields that must match in the returned record.
   * @param array $storeOnlyData Fields to include in the update but not match.
   * @return array The updated record.
   */
  public function updateAndMatch(int $id, array $matchData, array $storeOnlyData = []): array {
    $record = $this->update($id, $matchData + $storeOnlyData);
    foreach ($matchData as $field => $expected) {
      Assert::assertArrayHasKey($field, $record, "Updated record missing expected field '{$field}'.");
      Assert::assertEquals($expected, $record[$field], "Expected updated record field '{$field}' to equal '{$expected}', got '{$record[$field]}'.");
    }
    return $record;
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
   *
   * @return array The updated record.
   */
  public function assertUpdated(): array {
    return $this->adapter->assertUpdated();
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
