<?php
namespace Starbug\Testing;

use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Generic web assertions for acceptance tests.
 *
 * Tests use this trait to make assertions against the current
 * response state held by the driver. This keeps the driver layer
 * focused on queries and actions, not PHPUnit coupling.
 */
trait WebAssertions {

  /**
   * Assert the last response body contains the given text.
   *
   * @throws RuntimeException if no request has been made.
   */
  protected function assertPageContains(string $text): void {
    $body = $this->getDriver()->getResponseBody();
    Assert::assertStringContainsString($text, $body);
  }

  /**
   * Assert the last response body does not contain the given text.
   *
   * @throws RuntimeException if no request has been made.
   */
  protected function assertPageDoesNotContain(string $text): void {
    $body = $this->getDriver()->getResponseBody();
    Assert::assertStringNotContainsString($text, $body);
  }

  /**
   * Assert an element matching the CSS selector exists and contains text.
   *
   * @throws RuntimeException if the element is not found.
   */
  protected function assertElementContains(string $selector, string $text): void {
    $element = $this->getDriver()->filterOne($selector);
    Assert::assertStringContainsString($text, $element->text(''));
  }

  /**
   * Assert an element matching the CSS selector exists on the page.
   *
   * @throws RuntimeException if no request has been made.
   */
  protected function assertElementExists(string $selector): void {
    $elements = $this->getDriver()->filter($selector);
    Assert::assertGreaterThan(0, $elements->count(), "No element matching '{$selector}' found.");
  }

  /**
   * Assert the current request path matches the expected value.
   */
  protected function assertPathEquals(string $expected): void {
    Assert::assertEquals($expected, $this->getDriver()->getCurrentPath());
  }

  /**
   * Assert a link with the given visible text exists and points to the
   * expected app-relative path.
   *
   * Resolves the href against the current page (per RFC 3986) and compares
   * the resulting app-relative path. Use `selectLink` directly if you need
   * to inspect the raw `href` attribute or the resolved absolute URI.
   */
  protected function assertLinkHrefEquals(string $text, string $href): void {
    $link = $this->getDriver()->selectLink($text);
    Assert::assertGreaterThan(0, $link->count(), "Expected link '{$text}' not found.");
    $actual = $this->getDriver()->relativize($link->first()->link()->getUri());
    Assert::assertEquals($href, $actual);
  }

  /**
   * Get the driver instance.
   *
   * Implementing classes must provide access to the driver
   * (typically via a protected $driver property).
   */
  abstract protected function getDriver(): WebDriverInterface;
}
