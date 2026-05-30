<?php
namespace Starbug\Testing\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Starbug\Testing\Tests\Fixtures\TestDriver;

class AbstractWebDriverTest extends TestCase {

  protected TestDriver $driver;

  protected function setUp(): void {
    $this->driver = new TestDriver();
  }

  // --- DOM Query Tests ---

  public function testFilterReturnsCrawlerWithMatches(): void {
    $this->driver->setTestBody('<html><body><div class="foo">Hello</div><div class="bar">World</div></body></html>');
    $nodes = $this->driver->filter('.foo');
    $this->assertSame(1, $nodes->count());
    $this->assertSame('Hello', $nodes->first()->text());
  }

  public function testFilterReturnsEmptyCrawlerWhenNoMatches(): void {
    $this->driver->setTestBody('<html><body></body></html>');
    $nodes = $this->driver->filter('.missing');
    $this->assertSame(0, $nodes->count());
  }

  public function testFilterOneReturnsFirstMatch(): void {
    $this->driver->setTestBody('<html><body><div class="item">A</div><div class="item">B</div></body></html>');
    $node = $this->driver->filterOne('.item');
    $this->assertSame('A', $node->text());
  }

  public function testFilterOneThrowsWhenNoMatches(): void {
    $this->driver->setTestBody('<html><body></body></html>');
    $this->expectException(RuntimeException::class);
    $this->driver->filterOne('.missing');
  }

  // --- Content Assertion Tests ---

  public function testAssertContainsPassesWhenTextPresent(): void {
    $this->driver->setTestBody('<html><body>Hello World</body></html>');
    $this->driver->assertContains('World');
    $this->addToAssertionCount(1);
  }

  public function testAssertContainsThrowsWhenTextMissing(): void {
    $this->driver->setTestBody('<html><body>Hello</body></html>');
    $this->expectException(RuntimeException::class);
    $this->driver->assertContains('Missing');
  }

  public function testAssertNotContainsPassesWhenTextAbsent(): void {
    $this->driver->setTestBody('<html><body>Hello</body></html>');
    $this->driver->assertNotContains('Missing');
    $this->addToAssertionCount(1);
  }

  public function testAssertNotContainsThrowsWhenTextPresent(): void {
    $this->driver->setTestBody('<html><body>Hello</body></html>');
    $this->expectException(RuntimeException::class);
    $this->driver->assertNotContains('Hello');
  }

  public function testAssertElementContainsPassesWhenMatch(): void {
    $this->driver->setTestBody('<html><body><div class="msg">Hello</div></body></html>');
    $this->driver->assertElementContains('.msg', 'Hello');
    $this->addToAssertionCount(1);
  }

  public function testAssertElementContainsThrowsWhenSelectorMissing(): void {
    $this->driver->setTestBody('<html><body></body></html>');
    $this->expectException(RuntimeException::class);
    $this->driver->assertElementContains('.msg', 'Hello');
  }

  public function testAssertElementContainsThrowsWhenTextMissing(): void {
    $this->driver->setTestBody('<html><body><div class="msg">Bye</div></body></html>');
    $this->expectException(RuntimeException::class);
    $this->driver->assertElementContains('.msg', 'Hello');
  }

  // --- Link Tests ---

  public function testFollowLinkDispatchesRequest(): void {
    $this->driver->setTestBody('<html><body><a href="/foo">Go</a></body></html>');
    $this->driver->followLink('Go');
    $this->assertCount(1, $this->driver->capturedRequests);
    $this->assertSame('GET', $this->driver->capturedRequests[0]['method']);
    $this->assertSame('/foo', $this->driver->capturedRequests[0]['path']);
  }

  public function testFollowLinkThrowsWhenLinkMissing(): void {
    $this->driver->setTestBody('<html><body></body></html>');
    $this->expectException(RuntimeException::class);
    $this->driver->followLink('Go');
  }

  // --- CSRF Tests ---

  public function testExtractHiddenOidReturnsValue(): void {
    $this->driver->setTestBody('<form><input type="hidden" name="oid" value="abc123"/></form>');
    $this->assertSame('abc123', $this->driver->extractHiddenOid());
  }

  public function testExtractHiddenOidReturnsNullWhenMissing(): void {
    $this->driver->setTestBody('<form><input type="text" name="foo"/></form>');
    $this->assertNull($this->driver->extractHiddenOid());
  }

  // --- Form Error Tests ---

  public function testGetFormErrorsExtractsAlerts(): void {
    $this->driver->setTestBody('
      <div><input name="email"/><div class="alert alert-danger">Invalid email</div></div>
      <div><input name="name"/></div>
    ');
    $errors = $this->driver->getFormErrors();
    $this->assertSame(['email' => 'Invalid email'], $errors);
  }

  public function testGetFormErrorsReturnsEmptyArrayWhenNoAlerts(): void {
    $this->driver->setTestBody('<form><input name="email"/></form>');
    $this->assertSame([], $this->driver->getFormErrors());
  }

  // --- Form Interaction Tests ---

  public function testFillFieldThenPressButtonSubmitsForm(): void {
    $this->driver->setTestBody('<form action="/submit" method="post"><input name="foo"/><button type="submit">Save</button></form>');
    $this->driver->fillField('foo', 'hello');
    $this->driver->pressButton('Save');
    $this->assertCount(1, $this->driver->capturedRequests);
    $this->assertSame('POST', $this->driver->capturedRequests[0]['method']);
    $this->assertSame('/submit', $this->driver->capturedRequests[0]['path']);
    $this->assertSame('hello', $this->driver->capturedRequests[0]['data']['foo']);
  }

  public function testPressButtonAutoExtractsOid(): void {
    $this->driver->setTestBody('<form action="/submit" method="post"><input type="hidden" name="oid" value="xyz789"/><input name="bar"/><button type="submit">Submit</button></form>');
    $this->driver->fillField('bar', 'world');
    $this->driver->pressButton('Submit');
    $this->assertSame('xyz789', $this->driver->capturedRequests[0]['data']['oid']);
    $this->assertSame('world', $this->driver->capturedRequests[0]['data']['bar']);
  }

  public function testPressButtonThrowsWhenButtonMissing(): void {
    $this->driver->setTestBody('<form><input name="foo"/></form>');
    $this->expectException(RuntimeException::class);
    $this->driver->pressButton('Save');
  }

  public function testSelectOptionSetsValue(): void {
    $this->driver->setTestBody('<form action="/submit" method="post"><select name="color"><option value="red">Red</option><option value="blue">Blue</option></select><button type="submit">Go</button></form>');
    $this->driver->selectOption('color', 'blue');
    $this->driver->pressButton('Go');
    $this->assertSame('blue', $this->driver->capturedRequests[0]['data']['color']);
  }

  public function testCheckFieldSetsValueToOne(): void {
    $this->driver->setTestBody('<form action="/submit" method="post"><input type="checkbox" name="agree"/><button type="submit">Send</button></form>');
    $this->driver->checkField('agree');
    $this->driver->pressButton('Send');
    $this->assertSame('1', $this->driver->capturedRequests[0]['data']['agree']);
  }

  public function testUncheckFieldSetsValueToEmpty(): void {
    $this->driver->setTestBody('<form action="/submit" method="post"><input type="checkbox" name="agree" checked="checked"/><button type="submit">Send</button></form>');
    $this->driver->uncheckField('agree');
    $this->driver->pressButton('Send');
    $this->assertSame('', $this->driver->capturedRequests[0]['data']['agree']);
  }

  public function testSubmitFormBulkApi(): void {
    $this->driver->setTestBody('<form action="/create" method="post"><input type="hidden" name="oid" value="abc"/><input name="name"/></form>');
    $this->driver->submitForm('/create', ['name' => 'test']);
    $this->assertCount(2, $this->driver->capturedRequests);
    // First request is GET to fetch CSRF.
    $this->assertSame('GET', $this->driver->capturedRequests[0]['method']);
    // Second request is POST with data + oid.
    $this->assertSame('POST', $this->driver->capturedRequests[1]['method']);
    $this->assertSame('test', $this->driver->capturedRequests[1]['data']['name']);
    $this->assertSame('abc', $this->driver->capturedRequests[1]['data']['oid']);
  }

  // --- State Tracking Tests ---

  public function testFormValuesClearedAfterPressButton(): void {
    $this->driver->setTestBody('<form action="/a" method="post"><input name="x"/><button type="submit">A</button></form>');
    $this->driver->fillField('x', '1');
    $this->driver->pressButton('A');

    // Switch to a new page with a different form.
    $this->driver->setTestBody('<form action="/b" method="post"><input name="y"/><button type="submit">B</button></form>');
    $this->driver->pressButton('B');
    // y should be empty because formValues was cleared.
    $this->assertSame('', $this->driver->capturedRequests[1]['data']['y']);
  }

  public function testCrawlerInvalidatedAfterBodyChange(): void {
    $this->driver->setTestBody('<html><body><div class="foo">A</div></body></html>');
    $this->assertSame('A', $this->driver->filter('.foo')->first()->text());

    $this->driver->setTestBody('<html><body><div class="foo">B</div></body></html>');
    $this->assertSame('B', $this->driver->filter('.foo')->first()->text());
  }
}
