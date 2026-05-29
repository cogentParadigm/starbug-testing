<?php
namespace Starbug\Testing;

use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Starbug\Bundle\Bundle;

/**
 * DirectDriver dispatches HTTP requests through the application middleware
 * stack in-process, without using an actual HTTP client or web server.
 *
 * It maintains cookie state via a shared Bundle jar and auto-follows
 * redirects up to a configured maximum depth.
 */
class DirectDriver {

  /**
   * Maximum number of redirects to follow automatically.
   */
  protected int $maxRedirects = 5;

  /**
   * The application request handler (middleware dispatcher).
   */
  protected RequestHandlerInterface $handler;

  /**
   * Shared cookie jar backed by a Bundle.
   */
  protected Bundle $jar;

  /**
   * The last response captured.
   */
  protected ?ResponseInterface $lastResponse = null;

  /**
   * The response body of the last request.
   */
  protected string $lastBody = '';

  /**
   * The current request path (after any redirects).
   */
  protected string $currentPath = '/';

  /**
   * @param RequestHandlerInterface $handler The application request handler.
   * @param Bundle $jar Shared cookie jar.
   * @param int $maxRedirects Maximum redirect follows (default 5).
   */
  public function __construct(
    RequestHandlerInterface $handler,
    Bundle $jar,
    int $maxRedirects = 5
  ) {
    $this->handler = $handler;
    $this->jar = $jar;
    $this->maxRedirects = $maxRedirects;
  }

  /**
   * Dispatch a request and return the final response.
   *
   * Cookies from the shared jar are seeded into the request.
   * Redirects are followed automatically up to $maxRedirects.
   *
   * @param string $method HTTP method.
   * @param string $path Request path (e.g. "/login").
   * @param array $data POST/GET data.
   * @param array $headers Additional headers.
   * @return ResponseInterface The final response.
   */
  public function request(
    string $method,
    string $path,
    array $data = [],
    array $headers = []
  ): ResponseInterface {
    $redirects = 0;
    $response = $this->doRequest($method, $path, $data, $headers);

    while ($this->isRedirect($response) && $redirects < $this->maxRedirects) {
      $location = $this->getLocation($response);
      if (empty($location)) {
        break;
      }
      $redirects++;
      $response = $this->doRequest('GET', $location, [], []);
    }

    $this->lastResponse = $response;
    $this->lastBody = (string) $response->getBody();
    $this->currentPath = $path;

    return $response;
  }

  /**
   * Perform a single request without redirect handling.
   */
  protected function doRequest(
    string $method,
    string $path,
    array $data,
    array $headers
  ): ResponseInterface {
    $uri = $this->buildUri($path);
    $request = new ServerRequest($method, $uri, $headers);

    // Seed cookies from the shared jar.
    $cookies = [];
    if ($sid = $this->jar->get('sid')) {
      $cookies['sid'] = $sid;
    }
    if ($oid = $this->jar->get('oid')) {
      $cookies['oid'] = $oid;
    }
    $request = $request->withCookieParams($cookies);

    // Add form-encoded body for POST/PUT/PATCH.
    if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true) && !empty($data)) {
      $request = $request
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withBody(\GuzzleHttp\Psr7\Utils::streamFor(http_build_query($data)));
    }

    // Add query params for GET.
    if (strtoupper($method) === 'GET' && !empty($data)) {
      $uri = $uri->withQuery(http_build_query($data));
      $request = $request->withUri($uri);
    }

    // Use dispatch() if available (Middleland Dispatcher) to reset middleware
    // queue between requests. Fall back to handle() for generic handlers.
    if (method_exists($this->handler, 'dispatch')) {
      $response = $this->handler->dispatch($request);
    } else {
      $response = $this->handler->handle($request);
    }

    // Update current path from request.
    $this->currentPath = $uri->getPath();

    return $response;
  }

  /**
   * Build a URI from a path string.
   */
  protected function buildUri(string $path): \Psr\Http\Message\UriInterface {
    // Ensure path starts with "/".
    if (!str_starts_with($path, '/')) {
      $path = '/' . $path;
    }
    return new \GuzzleHttp\Psr7\Uri('https://localhost' . $path);
  }

  /**
   * Determine if a response is a redirect.
   */
  protected function isRedirect(ResponseInterface $response): bool {
    $status = $response->getStatusCode();
    return in_array($status, [301, 302, 303, 307, 308], true);
  }

  /**
   * Extract the Location header from a response.
   */
  protected function getLocation(ResponseInterface $response): ?string {
    $location = $response->getHeaderLine('Location');
    if (empty($location)) {
      return null;
    }
    // If the location is an absolute URL on the same host, extract just the path.
    if (str_starts_with($location, 'https://localhost') || str_starts_with($location, 'http://localhost')) {
      $location = parse_url($location, PHP_URL_PATH) ?: '/';
    }
    return $location;
  }

  /**
   * Get the status code of the last response.
   */
  public function getStatusCode(): int {
    if (!$this->lastResponse) {
      throw new \RuntimeException('No request has been made yet.');
    }
    return $this->lastResponse->getStatusCode();
  }

  /**
   * Get the body of the last response.
   */
  public function getResponseBody(): string {
    return $this->lastBody;
  }

  /**
   * Get the current request path.
   */
  public function getCurrentPath(): string {
    return $this->currentPath;
  }

  /**
   * Get a cookie value from the shared jar.
   */
  public function getCookie(string $name): ?string {
    return $this->jar->get($name);
  }

  /**
   * Extract the hidden CSRF token (oid) from the last response body.
   *
   * Looks for an input with name="oid".
   *
   * @return string|null The token value, or null if not found.
   */
  public function extractHiddenOid(): ?string {
    if (empty($this->lastBody)) {
      return null;
    }

    $dom = new \DOMDocument();
    // Suppress warnings from malformed HTML.
    @$dom->loadHTML($this->lastBody);
    $xpath = new \DOMXPath($dom);

    $inputs = $xpath->query('//input[@name="oid"]');
    if ($inputs->length > 0) {
      return $inputs->item(0)->getAttribute('value');
    }

    return null;
  }
}
