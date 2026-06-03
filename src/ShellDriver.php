<?php
namespace Starbug\Testing;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Safe command execution driver using Symfony Process with an argv array.
 */
class ShellDriver implements ShellDriverInterface {

  protected ?string $cwd = null;

  public function __construct(?string $cwd = null) {
    $this->cwd = $cwd ?? getcwd();
  }

  /**
   * Set the default working directory for subsequent commands.
   */
  public function setCwd(?string $cwd): void {
    $this->cwd = $cwd;
  }

  /**
   * Get the current default working directory.
   */
  public function getCwd(): ?string {
    return $this->cwd;
  }

  /**
   * Execute a command and capture stdout, stderr, and exit code.
   *
   * @param array $argv Command and arguments as an array (e.g. ['php', 'sb', 'query', 'users'])
   * @param string|null $cwd Working directory for the process
   *
   * @return array ['stdout' => string, 'stderr' => string, 'exit' => int]
   * @throws RuntimeException if the process cannot be started
   */
  public function run(array $argv, ?string $cwd = null): array {
    $process = new Process($argv, $cwd ?? $this->cwd);
    $process->run();

    return [
      'stdout' => $process->getOutput(),
      'stderr' => $process->getErrorOutput(),
      'exit' => $process->getExitCode() ?? -1
    ];
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
    $result = $this->run($argv, $cwd);
    if ($result['exit'] !== 0) {
      $cmd = implode(' ', array_map('escapeshellarg', $argv));
      $message = "Command failed with exit code {$result['exit']}: {$cmd}";
      if (!empty($result['stderr'])) {
        $message .= "\nSTDERR: " . $result['stderr'];
      }
      throw new RuntimeException($message);
    }
    return $result['stdout'];
  }
}
