<?php
namespace Starbug\Testing;

use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Contract for drivers that perform HTTP requests and expose
 * response state for sequential acceptance tests.
 */
interface WebDriverInterface {

  /**
   * Dispatch an HTTP request and store response state internally.
   *
   * Redirects are handled according to the driver implementation.
   */
  public function request(string $method, string $path, array $data = [], array $headers = []): ResponseInterface;

  /**
   * Convenience: dispatch a GET request.
   */
  public function get(string $path, array $headers = []): ResponseInterface;

  /**
   * Convenience: dispatch a POST request.
   */
  public function post(string $path, array $data = [], array $headers = []): ResponseInterface;

  /**
   * Get the status code of the last response.
   */
  public function getStatusCode(): int;

  /**
   * Get the body of the last response.
   */
  public function getResponseBody(): string;

  /**
   * Get the current request path (after any redirects).
   */
  public function getCurrentPath(): string;

  /**
   * Get a cookie value from the shared jar.
   */
  public function getCookie(string $name): ?string;

  /**
   * Find elements matching a CSS selector in the last response body.
   *
   * Returns a DomCrawler (empty if no matches).
   */
  public function filter(string $selector): Crawler;

  /**
   * Find the first element matching a CSS selector.
   *
   * Throws RuntimeException if not found.
   */
  public function filterOne(string $selector): Crawler;

  /**
   * Assert the last response body contains text.
   */
  public function assertContains(string $text): void;

  /**
   * Assert the last response body does not contain text.
   */
  public function assertNotContains(string $text): void;

  /**
   * Assert an element matching the selector exists and contains text.
   */
  public function assertElementContains(string $selector, string $text): void;

  /**
   * Follow a link in the last response body by its visible text.
   */
  public function followLink(string $text): void;

  /**
   * Fill a form field by name.
   *
   * Finds the form containing the field, sets the value, and stores
   * the form state internally for the next pressButton() or submitForm().
   */
  public function fillField(string $field, string $value): void;

  /**
   * Select an option from a dropdown by its visible text.
   */
  public function selectOption(string $field, string $option): void;

  /**
   * Check a checkbox by its name or label.
   */
  public function checkField(string $field): void;

  /**
   * Uncheck a checkbox by its name or label.
   */
  public function uncheckField(string $field): void;

  /**
   * Press a button by its text and submit the form.
   *
   * Finds the button, resolves its form, gathers all filled values
   * (hidden inputs travel automatically via DomCrawler), and submits.
   */
  public function pressButton(string $button): void;

  /**
   * Submit a form via DomCrawler's Form class.
   *
   * GETs the page, finds the form (optionally scoped by $formSelector),
   * sets values, and submits. Hidden inputs travel automatically.
   *
   * Bulk alternative to fillField + pressButton.
   */
  public function submitForm(string $path, array $data, ?string $formSelector = null): void;

  /**
   * Download content from a path via GET.
   */
  public function download(string $path): string;

  /**
   * Extract form field errors from the last response body.
   */
  public function getFormErrors(): array;
}
