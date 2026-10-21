<?php

declare(strict_types=1);

namespace Drupal\translation_availability\Plugin\views\field;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The operation for the translation of a row: edit it, or add it if missing.
 */
#[ViewsField('translation_availability_operation')]
final class TranslationOperation extends FieldPluginBase {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, private readonly EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  public function render(ResultRow $values): array {
    $nid = $this->getValue($values);
    $node = $nid ? $this->entityTypeManager->getStorage('node')->load($nid) : NULL;
    $langcode = $values->{$this->aliases['langcode']};
    if (!$node) {
      return [];
    }
    if ($node->hasTranslation($langcode)) {
      $title = new TranslatableMarkup('Edit');
      $url = $node->getTranslation($langcode)->toUrl('edit-form');
    }
    else {
      $title = new TranslatableMarkup('Add translation');
      $url = Url::fromRoute('entity.node.content_translation_add', [
        'node' => $node->id(),
        'source' => $node->getUntranslated()->language()->getId(),
        'target' => $langcode,
      ], ['language' => \Drupal::languageManager()->getLanguage($langcode)]);
    }
    $access = $url->access(NULL, TRUE);
    $build = $access->isAllowed()
      ? ['#type' => 'link', '#title' => $title, '#url' => $url, '#attributes' => ['class' => ['button', 'button--small']]]
      : [];
    $build['#cache']['tags'] = $node->getCacheTags();
    CacheableMetadata::createFromObject($access)->applyTo($build);
    return $build;
  }

}
