<?php
namespace Starbug\Testing;

use Traversable;
use ArrayAccess;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Starbug\Http\UriBuilderInterface;

/**
 * DirectDriver dispatches HTTP requests through the application middleware
 * stack in-process, without using an actual HTTP client or web server.
 *
 * It maintains cookie state via a shared jar and auto-follows
 * redirects up to a configured maximum depth.
 *
 * Configuration:
 * - $uriBuilder UriBuilderInterface wired with the correct base URL and path.
 *   Paths passed to the driver are app-relative; the builder handles resolution.
 */
class DirectDriver extends AbstractWebDriver {

  /**
   * Maximum number of redirects to follow automatically.
   */
  protected int $maxRedirects = 5;

  /**
   * The application request handler (middleware dispatcher).
   */
  protected RequestHandlerInterface $handler;

  /**
   * Shared cookie jar. Must be iterable (array or Traversable).
   */
  protected Traversable|array $jar;

  /**
   * Create a new DirectDriver.
   *
   * @param RequestHandlerInterface $handler The application request handler.
   * @param Traversable|array $jar Shared cookie jar.
   * @param UriBuilderInterface $uriBuilder The URI builder for the app.
   * @param int $maxRedirects Maximum redirect follows (default 5).
   */
  public function __construct(
    RequestHandlerInterface $handler,
    Traversable|array $jar,
    UriBuilderInterface $uriBuilder,
    int $maxRedirects = 5
  ) {
    parent::__construct($uriBuilder);
    $this->handler = $handler;
    $this->jar = $jar;
    $this->maxRedirects = $maxRedirects;
  }

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
    $this->invalidateDomState();

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
    $uri = new Uri($this->build($path, true));
    $request = new ServerRequest($method, $uri, $headers);

    // Seed all cookies from the shared jar.
    $cookies = [];
    foreach ($this->jar as $name => $value) {
      $cookies[$name] = $value;
    }
    $request = $request->withCookieParams($cookies);

    // Add form-encoded body for POST/PUT/PATCH.
    if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true) && !empty($data)) {
      $request = $request
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withBody(Utils::streamFor(http_build_query($data)));
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

    // Track current path as root-relative.
    $this->currentPath = $uri->getPath();

    return $response;
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
   *
   * Relativizes the location to produce an app-relative path.
   */
  protected function getLocation(ResponseInterface $response): ?string {
    $location = $response->getHeaderLine('Location');
    return empty($location) ? null : $this->relativize($location);
  }

  public function getCookie(string $name): ?string {
    if (is_array($this->jar) || $this->jar instanceof ArrayAccess) {
      return $this->jar[$name] ?? null;
    }
    foreach ($this->jar as $key => $value) {
      if ($key === $name) {
        return $value;
      }
    }
    return null;
  }

  /**
   * {@inheritdoc}
   *
   * Also clears the shared cookie jar.
   */
  public function reset(): void {
    parent::reset();
    if (is_array($this->jar)) {
      $this->jar = [];
    } else {
      $this->jar->set([]);
    }
  }
}
