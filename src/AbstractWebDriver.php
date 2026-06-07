<?php
namespace Starbug\Testing;

use RuntimeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Starbug\Http\UriBuilderInterface;
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
 * - $uriBuilder UriBuilderInterface wired with the correct base URL and path.
 *   Paths passed to the driver are app-relative; the builder handles resolution.
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
   * The URI of the last request (after any redirects).
   */
  protected ?UriInterface $uri = null;

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
   * URI builder for constructing and relativizing paths.
   */
  protected UriBuilderInterface $uriBuilder;

  /**
   * Create a new AbstractWebDriver.
   *
   * @param UriBuilderInterface $uriBuilder The URI builder for the app.
   */
  public function __construct(UriBuilderInterface $uriBuilder) {
    $this->uriBuilder = $uriBuilder;
  }

  /**
   * Build a URI from an app-relative path.
   *
   * {@inheritdoc}
   */
  public function build(string $path, bool $absolute = false): string {
    return (string) $this->uriBuilder->build($path, $absolute);
  }

  /**
   * Relativize a URI against the app base URI.
   *
   * {@inheritdoc}
   */
  public function relativize(string $path): string {
    return (string) $this->uriBuilder->relativize($path);
  }

  /**
   * Get a DomCrawler for the last response body.
   */
  protected function getCrawler(): Crawler {
    if ($this->crawler === null) {
      $this->crawler = new Crawler($this->lastBody, (string) $this->getUri()->withQuery(''));
    }
    return $this->crawler;
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
   */
  public function selectLink(string $text): Crawler {
    return $this->getCrawler()->selectLink($text);
  }

  /**
   * {@inheritdoc}
   *
   * @throws RuntimeException if the link is not found.
   */
  public function followLink(string $text): void {
    $link = $this->selectLink($text);
    if ($link->count() === 0) {
      throw new RuntimeException("Link with text '{$text}' not found.");
    }
    $this->request('GET', $this->relativize($link->first()->link()->getUri()));
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

    $this->formValues = [];
    $this->request(
      strtoupper($form->getMethod()),
      $this->relativize($form->getUri()),
      $data
    );
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

    $this->request(
      strtoupper($form->getMethod()),
      $this->relativize($form->getUri()),
      $form->getPhpValues()
    );
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
      $node = new Crawler($input, (string) $this->getUri()->withQuery(''));

      // Walk up ancestors looking for alert-danger.
      $parent = $input->parentNode;
      while ($parent && $parent->nodeName !== 'body') {
        $parentCrawler = new Crawler($parent, (string) $this->getUri()->withQuery(''));
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
   *
   * @throws RuntimeException if no request has been made yet.
   */
  public function getUri(): UriInterface {
    if ($this->uri === null) {
      throw new RuntimeException('No request has been made yet.');
    }
    return $this->uri;
  }

  /**
   * {@inheritdoc}
   */
  public function isOnPath(string $path): bool {
    return $this->build($path, false) === $this->uri->getPath();
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

  /**
   * Reset all tracked response and DOM state.
   *
   * Subclasses should override to also clear transport-specific
   * state (e.g. cookies, shared jars).
   */
  public function reset(): void {
    $this->lastResponse = null;
    $this->lastBody = '';
    $this->uri = null;
    $this->invalidateDomState();
  }
}
