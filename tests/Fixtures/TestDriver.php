<?php
namespace Starbug\Testing\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Starbug\Testing\AbstractWebDriver;
use Starbug\Testing\WebAssertions;
use Starbug\Testing\WebDriverInterface;
use Starbug\Http\UriBuilder;
use Starbug\Http\UriBuilderInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;

/**
 * Test double for AbstractWebDriver that captures requests instead of performing HTTP.
 */
class TestDriver extends AbstractWebDriver {
  use WebAssertions;

  public array $capturedRequests = [];

  public function __construct(?UriBuilderInterface $uriBuilder = null) {
    parent::__construct($uriBuilder ?? new UriBuilder(new Uri('https://localhost/')));
  }

  protected function getDriver(): WebDriverInterface {
    return $this;
  }

  public function request(string $method, string $path, array $data = [], array $headers = []): ResponseInterface {
    $this->capturedRequests[] = ['method' => $method, 'path' => $path, 'data' => $data];
    $this->currentPath = $this->uriBuilder->build($path, false)->getPath();
    $body = $this->lastBody;
    $response = new Response(200, [], $body);
    $this->lastResponse = $response;
    $this->invalidateDomState();
    return $response;
  }

  public function getCookie(string $name): ?string {
    return null;
  }

  public function setTestBody(string $body): void {
    $this->lastBody = $body;
    $this->invalidateDomState();
  }
}
