<?php
namespace Starbug\Testing;

/**
 * Contract for drivers that execute shell commands safely.
 *
 * Implementations accept commands as argv arrays to prevent
 * shell injection, capture stdout/stderr/exit, and provide
 * a strict variant that throws on non-zero exit codes.
 */
interface ShellDriverInterface {

  /**
   * Execute a command and capture stdout, stderr, and exit code.
   *
   * @param array $argv Command and arguments as an array (e.g. ['php', 'sb', 'query', 'users'])
   * @param string|null $cwd Working directory for the process
   *
   * @return array ['stdout' => string, 'stderr' => string, 'exit' => int]
   * @throws \RuntimeException if the process cannot be started
   */
  public function run(array $argv, ?string $cwd = null): array;

  /**
   * Execute a command and throw if the exit code is non-zero.
   *
   * @param array $argv Command and arguments as an array
   * @param string|null $cwd Working directory for the process
   *
   * @return string The stdout output
   * @throws \RuntimeException on non-zero exit code
   */
  public function runOrFail(array $argv, ?string $cwd = null): string;

  /**
   * Set the default working directory for subsequent commands.
   */
  public function setCwd(?string $cwd): void;

  /**
   * Get the current default working directory.
   */
  public function getCwd(): ?string;
}
