<?php

declare(strict_types=1);

namespace Drupal\translation_availability\EventSubscriber;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\translation_availability\AvailabilityAnalyzer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends visitors to the "not available in this language" page.
 *
 * - A missing translation: on the request, after routing (Drupal would show
 *   the original), when the content type is set to do so.
 * - An unpublished translation: on the access denied exception. Access is
 *   checked inside the router, before later request subscribers run, so the
 *   exception is where this can be caught.
 */
final class UnavailableSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly AvailabilityAnalyzer $analyzer,
    private readonly LanguageManagerInterface $languageManager,
    private readonly AccountInterface $currentUser,
  ) {}

  public static function getSubscribedEvents(): array {
    return [
      // After the router (32).
      KernelEvents::REQUEST => ['onRequest', 30],
      KernelEvents::EXCEPTION => ['onException', 0],
    ];
  }

  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if (!$event->isMainRequest() || $request->attributes->get('_route') !== 'entity.node.canonical') {
      return;
    }
    $node = $request->attributes->get('node');
    if (!$node instanceof NodeInterface || $this->setting($node, 'missing') !== 'unavailable_page') {
      return;
    }
    if (!$node->hasTranslation($this->langcode())) {
      $event->setResponse($this->redirect($node));
    }
  }

  public function onException(ExceptionEvent $event): void {
    $request = $event->getRequest();
    if (!$event->getThrowable() instanceof AccessDeniedHttpException || $request->attributes->get('_route') !== 'entity.node.canonical') {
      return;
    }
    $node = $request->attributes->get('node');
    if (!$node instanceof NodeInterface || $this->setting($node, 'unpublished') !== 'unavailable_page') {
      return;
    }
    // Only for an unpublished translation, not for other access rules.
    $langcode = $this->langcode();
    if ($node->hasTranslation($langcode) && !$node->getTranslation($langcode)->isPublished() && $this->analyzer->publishedTranslations($node)) {
      $event->setResponse($this->redirect($node));
    }
  }

  private function setting(NodeInterface $node, string $key): ?string {
    if (!$this->analyzer->applies($node)) {
      return NULL;
    }
    return \Drupal::service('translation_availability.settings')->forBundle($node->bundle())[$key] ?? NULL;
  }

  private function redirect(NodeInterface $node): RedirectResponse {
    $url = Url::fromRoute('translation_availability.unavailable', ['node' => $node->id()], [
      'language' => $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT),
    ])->toString();
    // Not cacheable: it depends on the translation, which may be added.
    $response = new RedirectResponse($url, 302);
    $response->setPrivate();
    return $response;
  }

  private function langcode(): string {
    return $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
  }

}
