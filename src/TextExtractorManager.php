<?php

declare(strict_types=1);

namespace Drupal\translation_availability;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\translation_availability\Attribute\TextExtractor;

/**
 * Manages the text extractor plugins.
 */
final class TextExtractorManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/TextExtractor', $namespaces, $module_handler, TextExtractorInterface::class, TextExtractor::class);
    $this->alterInfo('translation_availability_text_extractor_info');
    $this->setCacheBackend($cache_backend, 'translation_availability_text_extractor_plugins');
  }

  /**
   * Returns the extractor for a field type, or NULL if it has no text.
   */
  public function getExtractor(string $field_type): ?TextExtractorInterface {
    foreach ($this->getDefinitions() as $id => $definition) {
      if (in_array($field_type, $definition['field_types'], TRUE)) {
        return $this->createInstance($id);
      }
    }
    return NULL;
  }

  /**
   * Returns the field types that have an extractor.
   *
   * @return string[]
   */
  public function getTextFieldTypes(): array {
    return array_merge(...array_values(array_column($this->getDefinitions(), 'field_types'))) ?: [];
  }

}
