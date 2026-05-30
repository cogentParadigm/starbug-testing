<?php
namespace Starbug\Testing\Tests;

use PHPUnit\Framework\TestCase;
use Starbug\Testing\DirectDriver;
use Starbug\Bundle\Bundle;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GuzzleHttp\Psr7\Response;
use Starbug\Testing\Tests\Fixtures\MockHandler;

class DirectDriverIntegrationTest extends TestCase {

  protected RequestHandlerInterface $handler;
  protected Bundle $jar;
  protected DirectDriver $driver;

  protected function setUp(): void {
    $this->handler = new MockHandler();
    $this->jar = new Bundle();
    $this->driver = new DirectDriver($this->handler, $this->jar);
  }

  public function testRequestSetsBodyAndPath(): void {
    $this->handler->responseBody = '<html><body>Hello</body></html>';
    $this->driver->get('/test');

    $this->assertSame(200, $this->driver->getStatusCode());
    $this->assertSame('/test', $this->driver->getCurrentPath());
    $this->assertSame('<html><body>Hello</body></html>', $this->driver->getResponseBody());
  }

  public function testFilterWorksAfterRequest(): void {
    $this->handler->responseBody = '<html><body><div class="msg">Hello</div></body></html>';
    $this->driver->get('/page');

    $nodes = $this->driver->filter('.msg');
    $this->assertSame(1, $nodes->count());
    $this->assertSame('Hello', $nodes->first()->text());
  }

  public function testFollowLinkDispatchesGet(): void {
    $this->handler->responseBody = '<html><body><a href="/next">Next</a></body></html>';
    $this->driver->get('/start');
    $this->driver->followLink('Next');

    $this->assertCount(2, $this->handler->requests);
    $this->assertSame('/next', $this->driver->getCurrentPath());
  }

  public function testAssertContainsWorksAfterRequest(): void {
    $this->handler->responseBody = '<html><body>Welcome</body></html>';
    $this->driver->get('/');
    $this->driver->assertContains('Welcome');
    $this->addToAssertionCount(1);
  }

  public function testFormSubmissionViaPressButton(): void {
    $this->handler->responseBody = '<form action="/submit" method="post"><input name="email"/><button type="submit">Save</button></form>';
    $this->driver->get('/form');
    $this->driver->fillField('email', 'test@example.com');
    $this->driver->pressButton('Save');

    $this->assertCount(2, $this->handler->requests);
    $post = $this->handler->requests[1];
    $this->assertSame('POST', $post->getMethod());
    $this->assertSame('/submit', $post->getUri()->getPath());
  }

  public function testAutoFollowRedirects(): void {
    // First response is a redirect, second is the final page.
    $responses = [
      new Response(302, ['Location' => '/target']),
      new Response(200, [], '<html><body>Target</body></html>'),
    ];
    $this->handler = new class($responses) implements RequestHandlerInterface {
      private array $responses;
      private int $index = 0;
      public array $requests = [];
      public function __construct(array $responses) {
        $this->responses = $responses;
      }
      public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
        $this->requests[] = $request;
        return $this->responses[$this->index++];
      }
    };
    $this->driver = new DirectDriver($this->handler, $this->jar);
    $this->driver->get('/source');

    $this->assertCount(2, $this->handler->requests);
    $this->assertSame('/target', $this->driver->getCurrentPath());
  }
}
