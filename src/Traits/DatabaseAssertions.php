<?php
namespace Starbug\Testing\Traits;

use PHPUnit\Framework\Assert;

/**
 * Database assertion helpers for PHPUnit tests.
 *
 * These methods wrap common database query-and-assert patterns
 * that were previously scattered across test cases or implemented
 * in the legacy Behat DatabaseContext.
 *
 * Use alongside the `Database` trait (which provides `getDatabase()`).
 */
trait DatabaseAssertions {

  /**
   * Create a database record directly via db->store().
   *
   * @param string $entity The table/model name.
   * @param array $data Column-value pairs.
   *
   * @return array The stored record.
   */
  protected function createRecord(string $entity, array $data): array {
    $db = $this->getDatabase();
    $db->store($entity, $data);
    $id = $db->getInsertId($entity);
    $record = $db->query($entity)->condition("id", $id)->one();
    Assert::assertNotEmpty($record, "Failed to create {$entity} record.");
    return $record;
  }

  /**
   * Assert a record was created by the last insert operation.
   *
   * @param string $entity The table/model name.
   * @param string|null $message Optional assertion message.
   *
   * @return array The created record.
   */
  protected function assertRecordCreated(string $entity, ?string $message = null): array {
    $id = $this->getDatabase()->getInsertId($entity);
    return $this->assertRecordExists($entity, ['id' => $id], $message ?? "Expected a newly created {$entity} record.");
  }

  /**
   * Assert a record exists matching the given conditions.
   *
   * @param string $entity The table/model name.
   * @param array $conditions Column-value conditions.
   * @param string|null $message Optional assertion message.
   *
   * @return array The matching record.
   */
  protected function assertRecordExists(string $entity, array $conditions, ?string $message = null): array {
    $record = $this->getDatabase()->query($entity)->conditions($conditions)->one();
    Assert::assertNotEmpty($record, $message ?? "Expected a {$entity} record matching " . json_encode($conditions) . ".");
    return $record;
  }

  /**
   * Assert no record exists matching the given conditions.
   *
   * @param string $entity The table/model name.
   * @param array $conditions Column-value conditions.
   * @param string|null $message Optional assertion message.
   */
  protected function assertRecordNotExists(string $entity, array $conditions, ?string $message = null): void {
    $record = $this->getDatabase()->query($entity)->conditions($conditions)->one();
    Assert::assertEmpty($record, $message ?? "Did not expect a {$entity} record matching " . json_encode($conditions) . ".");
  }

  /**
   * Assert the number of records matching conditions.
   *
   * @param int $expected Expected count.
   * @param string $entity The table/model name.
   * @param array $conditions Column-value conditions.
   * @param string|null $message Optional assertion message.
   */
  protected function assertRecordCount(int $expected, string $entity, array $conditions = [], ?string $message = null): void {
    $query = $this->getDatabase()->query($entity);
    if (!empty($conditions)) {
      $query->conditions($conditions);
    }
    $count = $query->count();
    Assert::assertSame($expected, $count, $message ?? "Expected {$expected} {$entity} records, found {$count}.");
  }

  /**
   * Assert a field value contains a substring (LIKE match).
   *
   * @param string $entity The table/model name.
   * @param string $field The column to search.
   * @param string $value The substring to match.
   * @param array $conditions Additional column-value conditions.
   * @param string|null $message Optional assertion message.
   *
   * @return array The matching record.
   */
  protected function assertFieldValueContains(string $entity, string $field, string $value, array $conditions = [], ?string $message = null): array {
    $query = $this->getDatabase()->query($entity);
    if (!empty($conditions)) {
      $query->conditions($conditions);
    }
    $record = $query->where("{$field} LIKE \"%" . $value . "%\"")->one();
    Assert::assertNotEmpty($record, $message ?? "Expected a {$entity} record where {$field} contains '{$value}'.");
    return $record;
  }
}
