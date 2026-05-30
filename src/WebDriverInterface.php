<?php
namespace Starbug\Testing;

use Psr\Http\Message\ResponseInterface;

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
   * Extract the hidden CSRF token (oid) from the last response body.
   */
  public function extractHiddenOid(): ?string;
}
