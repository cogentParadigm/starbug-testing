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
 *
 * Configuration:
 * - $baseUrl    Scheme + host (e.g. "https://localhost")
 * - $basePath   App mount point (e.g. "/myapp/"). Paths passed to the
 *               driver are app-relative; basePath is prepended internally.
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
   * The current request path (app-relative, without basePath).
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
   * Base URL (scheme + host, no trailing slash).
   */
  protected string $baseUrl = 'https://localhost';

  /**
   * Base path (e.g. "/myapp/"). Always starts and ends with "/".
   * Defaults to "/" for apps mounted at the domain root.
   */
  protected string $basePath = '/';

  /**
   * Create a new AbstractWebDriver.
   *
   * @param string $baseUrl Scheme + host (default "https://localhost").
   * @param string $basePath App mount point (default "/").
   */
  public function __construct(
    string $baseUrl = 'https://localhost',
    string $basePath = '/'
  ) {
    $this->baseUrl = rtrim($baseUrl, '/');
    $this->basePath = $this->normalizeBasePath($basePath);
  }

  /**
   * Normalize a base path so it always starts and ends with "/".
   */
  protected function normalizeBasePath(string $path): string {
    $path = trim($path, '/');
    return $path === '' ? '/' : '/' . $path . '/';
  }

  /**
   * Strip basePath from a server path to produce an app-relative path.
   */
  protected function normalizePath(string $path): string {
    $path = '/' . ltrim($path, '/');
    if ($this->basePath !== '/' && str_starts_with($path, $this->basePath)) {
      $path = '/' . ltrim(substr($path, strlen($this->basePath)), '/');
    }
    return $path;
  }

  /**
   * Build a server-absolute path from an app-relative path.
   */
  protected function buildAbsolutePath(string $path): string {
    $path = '/' . ltrim($path, '/');
    if ($this->basePath === '/') {
      return $path;
    }
    return $this->basePath . ltrim($path, '/');
  }

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
   * Combines baseUrl + basePath + currentPath.
   */
  protected function getBaseUri(): string {
    if ($this->basePath === '/') {
      return $this->baseUrl . $this->currentPath;
    }
    return $this->baseUrl . $this->basePath . ltrim($this->currentPath, '/');
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
   * @throws RuntimeException if the link is not found.
   */
  public function followLink(string $text): void {
    $link = $this->getCrawler()->selectLink($text);
    if ($link->count() === 0) {
      throw new RuntimeException("Link with text '{$text}' not found.");
    }
    $uri = $link->first()->link()->getUri();
    // Strip base URL to get server path.
    if (str_starts_with($uri, $this->baseUrl)) {
      $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
    }
    // Strip basePath so request receives app-relative path.
    if ($this->basePath !== '/' && str_starts_with($uri, $this->basePath)) {
      $uri = '/' . ltrim(substr($uri, strlen($this->basePath)), '/');
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

    $uri = $form->getUri();
    $method = strtoupper($form->getMethod());

    // Extract app-relative path from absolute URI.
    if (str_starts_with($uri, $this->baseUrl)) {
      $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
    }
    if ($this->basePath !== '/' && str_starts_with($uri, $this->basePath)) {
      $uri = '/' . ltrim(substr($uri, strlen($this->basePath)), '/');
    }

    $this->formValues = [];
    $this->request($method, $uri, $data);
  }

  /**
   * {@inheritdoc}
   *
   * @throws RuntimeException if no form is found on the page.
   */
  public function submitForm(string $path, array $data, ?string $formSelector = null): void {
    $this->request('GET', $path);

    $crawler = $this->getCrawler();
    if ($formSelector !== null) {
      $formNode = $crawler->filter($formSelector);
    } else {
      $formNode = $crawler->filter('form');
    }

    if ($formNode->count() === 0) {
      throw new RuntimeException(
        "No form found" . ($formSelector ? " matching '{$formSelector}'" : "") . " on page {$path}."
      );
    }

    $form = $formNode->first()->form();
    $form->setValues($data);

    $uri = $form->getUri();
    $method = strtoupper($form->getMethod());

    // Extract app-relative path from absolute URI.
    if (str_starts_with($uri, $this->baseUrl)) {
      $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
    }
    if ($this->basePath !== '/' && str_starts_with($uri, $this->basePath)) {
      $uri = '/' . ltrim(substr($uri, strlen($this->basePath)), '/');
    }

    $this->request($method, $uri, $form->getPhpValues());
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
   * Extract the value of a hidden input by its name attribute.
   *
   * @param string $name The input name to search for.
   *
   * @return string|null The input value, or null if not found.
   */
  public function extractHiddenField(string $name): ?string {
    $crawler = $this->getCrawler();
    $inputs = $crawler->filter('input[name="' . $name . '"]');
    if ($inputs->count() > 0) {
      return $inputs->first()->attr('value');
    }
    return null;
  }
}
