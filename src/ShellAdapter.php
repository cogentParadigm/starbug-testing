<?php
namespace Starbug\Testing;

use RuntimeException;

/**
 * Base adapter for domain-specific shell protocol adapters.
 *
 * Adapters extend this class to provide domain actions (e.g. runQueryCommand)
 * while delegating shell operations to a ShellDriverInterface implementation.
 */
abstract class ShellAdapter implements ShellAdapterInterface {

  public function __construct(protected ShellDriverInterface $driver) {
  }

  /**
   * Execute a command and capture stdout, stderr, and exit code.
   *
   * @param array $argv Command and arguments as an array
   * @param string|null $cwd Working directory for the process
   *
   * @return array ['stdout' => string, 'stderr' => string, 'exit' => int]
   */
  public function run(array $argv, ?string $cwd = null): array {
    return $this->driver->run($argv, $cwd);
  }

  /**
   * Execute a command and throw if the exit code is non-zero.
   *
   * @param array $argv Command and arguments as an array
   * @param string|null $cwd Working directory for the process
   *
   * @return string The stdout output
   * @throws RuntimeException on non-zero exit code
   */
  public function runOrFail(array $argv, ?string $cwd = null): string {
    return $this->driver->runOrFail($argv, $cwd);
  }

  /**
   * Get the underlying driver for direct access when needed.
   */
  public function getDriver(): ShellDriverInterface {
    return $this->driver;
  }
}
