<?php
namespace Starbug\Testing;

use Psr\Http\Message\ResponseInterface;

/**
 * Base web driver with state tracking and generic HTTP helpers.
 *
 * Concrete drivers must implement request(). All other methods either
 * read stored state or provide reusable actions (followLink, submitForm,
 * etc.) that work across any driver implementation.
 */
abstract class AbstractWebDriver implements WebDriverInterface {

  /**
   * The last response captured.
   */
  protected ?ResponseInterface $lastResponse = null;
  /**
   * The response body of the last request.
   */
  protected string $lastBody = '';
  /**
   * The current request path.
   */
  protected string $currentPath = '/';

  /**
   * Follow a link in the last response body by its visible text.
   *
   * Parses the HTML, finds the first anchor whose textContent matches
   * $text exactly after trimming, and navigates to its href.
   */
  public function followLink(string $text): void {
    if (empty($this->lastBody)) {
      throw new \RuntimeException('No response body available to follow link.');
    }

    $dom = new \DOMDocument();
    @$dom->loadHTML($this->lastBody);
    $xpath = new \DOMXPath($dom);

    $links = $xpath->query('//a');
    foreach ($links as $link) {
      if (trim($link->textContent) === $text) {
        $href = $link->getAttribute('href');
        if (!empty($href)) {
          $this->request('GET', $href);
          return;
        }
      }
    }

    throw new \RuntimeException("Link with text '{$text}' not found.");
  }

  /**
   * Submit a form via POST after extracting the CSRF token.
   *
   * 1. GET the form page to fetch a fresh CSRF token.
   * 2. Extract the hidden oid input.
   * 3. POST to the same path with $data + oid.
   */
  public function submitForm(string $path, array $data): void {
    $this->request('GET', $path);
    $oid = $this->extractHiddenOid();
    if ($oid !== null) {
      $data['oid'] = $oid;
    }
    $this->request('POST', $path, $data);
  }

  /**
   * Download content from a path via GET.
   */
  public function download(string $path): string {
    $this->request('GET', $path);
    return $this->getResponseBody();
  }

  /**
   * Extract form field errors from the last response body.
   *
   * Looks for inputs with a name attribute and searches their ancestor
   * tree for elements with class "alert alert-danger". Returns a map
   * of field name to error message.
   *
   * This heuristic covers Starbug's default form rendering. It may need
   * refinement for custom themes or non-standard markup.
   */
  public function getFormErrors(): array {
    $errors = [];
    if (empty($this->lastBody)) {
      return $errors;
    }

    $dom = new \DOMDocument();
    @$dom->loadHTML($this->lastBody);
    $xpath = new \DOMXPath($dom);

    $inputs = $xpath->query('//input[@name] | //select[@name] | //textarea[@name]');
    foreach ($inputs as $input) {
      $name = $input->getAttribute('name');
      $parent = $input->parentNode;
      while ($parent && $parent->nodeName !== 'body') {
        $alerts = $xpath->query(
          './/*[contains(@class, "alert") and contains(@class, "alert-danger")]',
          $parent
        );
        if ($alerts->length > 0) {
          $message = trim($alerts->item(0)->textContent);
          if (!empty($message)) {
            $errors[$name] = $message;
          }
          break;
        }
        $parent = $parent->parentNode;
      }
    }

    return $errors;
  }

  public function getStatusCode(): int {
    if (!$this->lastResponse) {
      throw new \RuntimeException('No request has been made yet.');
    }
    return $this->lastResponse->getStatusCode();
  }

  public function getResponseBody(): string {
    return $this->lastBody;
  }

  public function getCurrentPath(): string {
    return $this->currentPath;
  }

  public function get(string $path, array $headers = []): ResponseInterface {
    return $this->request('GET', $path, [], $headers);
  }

  public function post(string $path, array $data = [], array $headers = []): ResponseInterface {
    return $this->request('POST', $path, $data, $headers);
  }

  public function extractHiddenOid(): ?string {
    if (empty($this->lastBody)) {
      return null;
    }

    $dom = new \DOMDocument();
    @$dom->loadHTML($this->lastBody);
    $xpath = new \DOMXPath($dom);

    $inputs = $xpath->query('//input[@name="oid"]');
    if ($inputs->length > 0) {
      return $inputs->item(0)->getAttribute('value');
    }

    return null;
  }
}
