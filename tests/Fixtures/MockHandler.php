<?php
namespace Starbug\Testing\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Mock request handler for integration testing DirectDriver.
 */
class MockHandler implements RequestHandlerInterface {
  public array $requests = [];
  public string $responseBody = '';
  public int $statusCode = 200;

  public function handle(ServerRequestInterface $request): ResponseInterface {
    $this->requests[] = $request;
    return new \GuzzleHttp\Psr7\Response($this->statusCode, [], $this->responseBody);
  }
}
