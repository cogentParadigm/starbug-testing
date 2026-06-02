<?php
namespace Starbug\Testing;

/**
 * Base class for web acceptance tests.
 *
 * Provides fixture loading (via DatabaseTestCase) and a protected
 * WebDriverInterface property. The default driver is DirectDriver
 * (in-process). Subclasses may override createDriver() to use a
 * different transport (e.g. BrowserDriver for JavaScript tests).
 */
abstract class WebTestCase extends DatabaseTestCase {
  use WebAssertions;

  /**
   * The web driver for this test.
   */
  protected WebDriverInterface $driver;

  protected function setUp(): void {
    parent::setUp();
    $this->getDriver()->reset();
  }

  /**
   * Factory method for the web driver.
   *
   * Defaults to DirectDriver using the container's request handler
   * and shared test cookie jar. Override to use a different driver.
   */
  protected function getDriver(): WebDriverInterface {
    if (empty($this->driver)) {
      global $container;
      $this->driver = $container->get(WebDriverInterface::class);
    }
    return $this->driver;
  }
}
