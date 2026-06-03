<?php
namespace Starbug\Testing;

/**
 * Minimal contract for all shell protocol adapters.
 *
 * Provides a generic accessor to the underlying driver so
 * cross-cutting helpers and patterns can work with any adapter.
 */
interface ShellAdapterInterface {

  /**
   * Get the underlying shell driver.
   */
  public function getDriver(): ShellDriverInterface;
}
