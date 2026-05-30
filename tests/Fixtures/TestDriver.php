<?php
namespace Starbug\Testing\Tests\Fixtures;

use Starbug\Testing\AbstractWebDriver;
use GuzzleHttp\Psr7\Response;

/**
 * Test double for AbstractWebDriver that captures requests instead of performing HTTP.
 */
class TestDriver extends AbstractWebDriver {
  public array $capturedRequests = [];

  public function request(string $method, string $path, array $data = [], array $headers = []): \Psr\Http\Message\ResponseInterface {
    $this->capturedRequests[] = ['method' => $method, 'path' => $path, 'data' => $data];
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

  public function setCsrfFieldName(?string $name): void {
    $this->csrfFieldName = $name;
  }
}
