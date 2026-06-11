<?php
namespace Starbug\Testing\Adapter;

/**
 * Generic contract for CRUD protocol adapters.
 *
 * Implementations translate create, read, update, delete, and list
 * operations into transport-specific calls (HTTP, shell, etc.).
 */
interface CrudAdapterInterface {

  /**
   * Create a record.
   *
   * @param array $data Field values.
   */
  public function create(array $data): void;

  /**
   * Read a single record by ID.
   *
   * @return array Associative array of field values.
   */
  public function read(int $id): array;

  /**
   * Update a record.
   */
  public function update(int $id, array $data): void;

  /**
   * Delete a record.
   */
  public function delete(int $id): void;

  /**
   * Navigate to or retrieve the list.
   */
  public function list(): void;

  /**
   * Assert the last create operation succeeded.
   */
  public function assertCreated(): array;

  /**
   * Assert the last read operation succeeded.
   */
  public function assertRead(int $id): void;

  /**
   * Assert the last update operation succeeded.
   *
   * @return array The updated record.
   */
  public function assertUpdated(): array;

  /**
   * Assert the last delete operation succeeded.
   */
  public function assertDeleted(): void;

  /**
   * Assert the current view is the list.
   */
  public function assertOnList(): void;

  /**
   * Assert the last create operation failed with expected validation errors.
   *
   * @param array $expectedErrors Map of field name => error message fragment.
   */
  public function assertValidationFailed(array $expectedErrors): void;

}
