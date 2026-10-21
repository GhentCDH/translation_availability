<?php

declare(strict_types=1);

namespace Drupal\translation_availability\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\translation_availability\AvailabilityAnalyzer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The "not available in this language" page, with links to the languages it
 * is available in.
 */
final class UnavailableController extends ControllerBase {

  public function __construct(private readonly AvailabilityAnalyzer $analyzer) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('translation_availability.analyzer'));
  }

  public function page(NodeInterface $node): array|RedirectResponse {
    $language = $this->languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
    $langcode = $language->getId();

    // Available after all (e.g. translated or published since): go there.
    if ($node->hasTranslation($langcode) && $node->getTranslation($langcode)->isPublished()) {
      return new RedirectResponse($node->getTranslation($langcode)->toUrl()->toString(), 302);
    }

    $links = $this->analyzer->publishedTranslations($node);
    if (!$links && !$node->access('view')) {
      throw new NotFoundHttpException();
    }

    // Editors can add the translation from here.
    $translate_url = NULL;
    $add = Url::fromRoute('entity.node.content_translation_add', [
      'node' => $node->id(),
      'source' => $node->getUntranslated()->language()->getId(),
      'target' => $langcode,
    ]);
    if (!$node->hasTranslation($langcode) && $add->access()) {
      $translate_url = $add->toString();
    }

    return [
      '#theme' => 'translation_availability_unavailable',
      '#node' => $node,
      '#language_name' => $this->analyzer->languageName($langcode),
      '#links' => $links,
      '#translate_url' => $translate_url,
      '#cache' => [
        'tags' => $node->getCacheTags(),
        'contexts' => ['languages:language_content', 'user.permissions'],
      ],
    ];
  }

  public function title(NodeInterface $node): TranslatableMarkup {
    return $this->analyzer->publishedTranslations($node)
      ? $this->t('Available in another language')
      : $this->t('Not available');
  }

}
