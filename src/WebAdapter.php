<?php
namespace Starbug\Testing;

use Psr\Http\Message\ResponseInterface;

/**
 * Base adapter for domain-specific web protocol adapters.
 *
 * Adapters extend this class to provide domain actions (e.g. navigateToUsersList)
 * while delegating HTTP operations to a WebDriverInterface implementation.
 */
abstract class WebAdapter implements WebAdapterInterface {
  use WebAssertions;

  public function __construct(protected WebDriverInterface $driver) {
  }

  /**
   * Navigate to a path via GET.
   *
   * Domain-oriented alias for driver->get().
   */
  public function visit(string $path): ResponseInterface {
    return $this->driver->get($path);
  }

  /**
   * Get the underlying driver for direct access when needed.
   */
  public function getDriver(): WebDriverInterface {
    return $this->driver;
  }
}
