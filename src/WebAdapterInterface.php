<?php
namespace Starbug\Testing;

/**
 * Minimal contract for all web protocol adapters.
 *
 * Provides a generic accessor to the underlying driver so
 * cross-cutting helpers and patterns can work with any adapter.
 */
interface WebAdapterInterface {

  /**
   * Get the underlying web driver.
   */
  public function getDriver(): WebDriverInterface;
}
