<?php
namespace Starbug\Testing;

use RuntimeException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Base web driver with state tracking and generic HTTP helpers.
 *
 * Concrete drivers must implement request(). All other methods either
 * read stored state or provide reusable actions (followLink, submitForm,
 * fillField, pressButton, etc.) that work across any driver implementation.
 *
 * DOM operations use Symfony DomCrawler for CSS selector support and
 * form interaction.
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
   * Cached DomCrawler for the last response body.
   */
  protected ?Crawler $crawler = null;

  /**
   * Accumulated form field values for the current page.
   *
   * Keyed by field name. Cleared after pressButton or submitForm.
   */
  protected array $formValues = [];

  /**
   * Get a DomCrawler for the last response body.
   */
  protected function getCrawler(): Crawler {
    if ($this->crawler === null) {
      $this->crawler = new Crawler($this->lastBody, $this->getBaseUri());
    }
    return $this->crawler;
  }

  /**
   * Get the base URI for link/form resolution.
   *
   * Defaults to the current path with https://localhost scheme.
   */
  protected function getBaseUri(): string {
    return 'https://localhost' . $this->currentPath;
  }

  /**
   * Invalidate the cached crawler and form values after a request.
   */
  protected function invalidateDomState(): void {
    $this->crawler = null;
    $this->formValues = [];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(string $selector): Crawler {
    return $this->getCrawler()->filter($selector);
  }

  /**
   * {@inheritdoc}
   *
   * @throws RuntimeException if no element matches the selector.
   */
  public function filterOne(string $selector): Crawler {
    $crawler = $this->filter($selector);
    if ($crawler->count() === 0) {
      throw new RuntimeException("No element matching selector '{$selector}' found.");
    }
    return $crawler->first();
  }

  /**
   * {@inheritdoc}
   *
   * @throws RuntimeException if the text is not found.
   */
  public function assertContains(string $text): void {
    $body = $this->getCrawler()->text('');
    if (strpos($body, $text) === false) {
      throw new RuntimeException("Page does not contain '{$text}'.");
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws RuntimeException if the text is found.
   */
  public function assertNotContains(string $text): void {
    $body = $this->getCrawler()->text('');
    if (strpos($body, $text) !== false) {
      throw new RuntimeException("Page contains '{$text}' but should not.");
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws RuntimeException if the element or text is not found.
   */
  public function assertElementContains(string $selector, string $text): void {
    $element = $this->filterOne($selector);
    $elementText = $element->text('');
    if (strpos($elementText, $text) === false) {
      throw new RuntimeException("Element '{$selector}' does not contain '{$text}'.");
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws RuntimeException if the link is not found.
   */
  public function followLink(string $text): void {
    $link = $this->getCrawler()->selectLink($text);
    if ($link->count() === 0) {
      throw new RuntimeException("Link with text '{$text}' not found.");
    }
    $uri = $link->first()->link()->getUri();
    // Extract path from absolute localhost URI.
    if (str_starts_with($uri, 'https://localhost') || str_starts_with($uri, 'http://localhost')) {
      $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
    }
    $this->request('GET', $uri);
  }

  /**
   * {@inheritdoc}
   */
  public function fillField(string $field, string $value): void {
    $this->formValues[$field] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function selectOption(string $field, string $option): void {
    $this->formValues[$field] = $option;
  }

  /**
   * {@inheritdoc}
   */
  public function checkField(string $field): void {
    $this->formValues[$field] = '1';
  }

  /**
   * {@inheritdoc}
   */
  public function uncheckField(string $field): void {
    $this->formValues[$field] = '';
  }

  /**
   * {@inheritdoc}
   *
   * @throws RuntimeException if the button is not found.
   */
  public function pressButton(string $button): void {
    $btn = $this->getCrawler()->selectButton($button);
    if ($btn->count() === 0) {
      throw new RuntimeException("Button '{$button}' not found.");
    }

    $form = $btn->first()->form();
    $data = $this->formValues + $form->getPhpValues();

    // Auto-extract CSRF oid if present.
    $oid = $this->extractHiddenOid();
    if ($oid !== null && !isset($data['oid'])) {
      $data['oid'] = $oid;
    }

    $uri = $form->getUri();
    $method = strtoupper($form->getMethod());

    // Extract path from absolute localhost URI.
    if (str_starts_with($uri, 'https://localhost') || str_starts_with($uri, 'http://localhost')) {
      $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
    }

    $this->formValues = [];
    $this->request($method, $uri, $data);
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function download(string $path): string {
    $this->request('GET', $path);
    return $this->getResponseBody();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormErrors(): array {
    $errors = [];
    $crawler = $this->getCrawler();
    $inputs = $crawler->filter('input[name], select[name], textarea[name]');

    foreach ($inputs as $input) {
      $name = $input->getAttribute('name');
      $node = new Crawler($input, $this->getBaseUri());

      // Walk up ancestors looking for alert-danger.
      $parent = $input->parentNode;
      while ($parent && $parent->nodeName !== 'body') {
        $parentCrawler = new Crawler($parent, $this->getBaseUri());
        $alerts = $parentCrawler->filter('.alert.alert-danger');
        if ($alerts->count() > 0) {
          $message = trim($alerts->first()->text(''));
          if ($message !== '') {
            $errors[$name] = $message;
          }
          break;
        }
        $parent = $parent->parentNode;
      }
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   *
   * @throws RuntimeException if no request has been made yet.
   */
  public function getStatusCode(): int {
    if (!$this->lastResponse) {
      throw new RuntimeException('No request has been made yet.');
    }
    return $this->lastResponse->getStatusCode();
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseBody(): string {
    return $this->lastBody;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentPath(): string {
    return $this->currentPath;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $path, array $headers = []): ResponseInterface {
    return $this->request('GET', $path, [], $headers);
  }

  /**
   * {@inheritdoc}
   */
  public function post(string $path, array $data = [], array $headers = []): ResponseInterface {
    return $this->request('POST', $path, $data, $headers);
  }

  /**
   * {@inheritdoc}
   */
  public function extractHiddenOid(): ?string {
    $crawler = $this->getCrawler();
    $inputs = $crawler->filter('input[name="oid"]');
    if ($inputs->count() > 0) {
      return $inputs->first()->attr('value');
    }
    return null;
  }
}
