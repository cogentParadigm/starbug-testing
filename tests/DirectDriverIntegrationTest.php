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

  public function testAllCookiesFromJarAreSent(): void {
    $this->jar['session_id'] = 'abc123';
    $this->jar['tracking'] = 'xyz';
    $this->driver->get('/');

    $request = $this->handler->requests[0];
    $cookies = $request->getCookieParams();
    $this->assertSame('abc123', $cookies['session_id']);
    $this->assertSame('xyz', $cookies['tracking']);
  }

  public function testBasePathPrependedToRequests(): void {
    $driver = new DirectDriver($this->handler, $this->jar, basePath: '/myapp/');
    $driver->get('/admin');

    $request = $this->handler->requests[0];
    $this->assertSame('/myapp/admin', $request->getUri()->getPath());
    $this->assertSame('/admin', $driver->getCurrentPath());
  }

  public function testRedirectStripsBasePath(): void {
    $responses = [
      new Response(302, ['Location' => 'https://localhost/myapp/target']),
      new Response(200, [], 'Target'),
    ];
    $handler = new class($responses) implements RequestHandlerInterface {
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
    $driver = new DirectDriver($handler, $this->jar, basePath: '/myapp/');
    $driver->get('/source');

    $this->assertCount(2, $handler->requests);
    $this->assertSame('/target', $driver->getCurrentPath());
  }

  public function testCustomBaseUrlUsedInRequests(): void {
    $driver = new DirectDriver($this->handler, $this->jar, baseUrl: 'https://app.local.com');
    $driver->get('/login');

    $request = $this->handler->requests[0];
    $this->assertSame('https://app.local.com/login', (string) $request->getUri());
  }
}
