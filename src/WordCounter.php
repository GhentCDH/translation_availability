<?php

declare(strict_types=1);

namespace Drupal\translation_availability;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;

/**
 * Counts the words of a translation in the fields that count as content.
 *
 * Fields are given as paths: "body", or through references (e.g.
 * paragraphs) "field_content.field_text", "field_tabs.field_content.field_text".
 * Referenced entities count in the same language only: a paragraph that is
 * not translated into that language adds no words.
 */
final class WordCounter {

  /**
   * How deep the field paths can go through references.
   */
  public const MAX_DEPTH = 3;

  public function __construct(
    private readonly TextExtractorManager $extractors,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Counts the words of an entity (one translation) in the given fields.
   *
   * @param string[] $paths
   */
  public function count(ContentEntityInterface $entity, array $paths): int {
    return self::countWords($this->text($entity, $paths));
  }

  /**
   * Returns the text of an entity in the given fields.
   *
   * @param string[] $paths
   */
  public function text(ContentEntityInterface $entity, array $paths): string {
    $langcode = $entity->language()->getId();
    $texts = [];
    foreach ($paths as $path) {
      $texts[] = $this->textOfPath($entity, explode('.', $path), $langcode);
    }
    return trim(implode(' ', array_filter($texts)));
  }

  /**
   * Counts the words in a text: letters and digits, also with accents, and
   * words joined with an apostrophe or hyphen ("l'histoire", "re-signification").
   */
  public static function countWords(string $text): int {
    return (int) preg_match_all("/[\\p{L}\\p{N}]+(?:['’\\-][\\p{L}\\p{N}]+)*/u", $text);
  }

  private function textOfPath(ContentEntityInterface $entity, array $segments, string $langcode): string {
    $field_name = array_shift($segments);
    if (!$entity->hasField($field_name)) {
      return '';
    }
    $items = $entity->get($field_name);
    if (!$segments) {
      $extractor = $this->extractors->getExtractor($items->getFieldDefinition()->getType());
      return $extractor ? $extractor->extract($items) : '';
    }
    // Follow a reference (e.g. paragraphs) to the next field.
    if (!method_exists($items, 'referencedEntities')) {
      return '';
    }
    $texts = [];
    foreach ($items->referencedEntities() as $referenced) {
      if (!$referenced instanceof ContentEntityInterface) {
        continue;
      }
      if ($referenced instanceof TranslatableInterface && $referenced->isTranslatable()) {
        if (!$referenced->hasTranslation($langcode)) {
          // Shown in another language: not part of this translation.
          continue;
        }
        $referenced = $referenced->getTranslation($langcode);
      }
      $texts[] = $this->textOfPath($referenced, $segments, $langcode);
    }
    return implode(' ', array_filter($texts));
  }

  /**
   * Returns the field paths that can count as content for a bundle.
   *
   * Text fields of the bundle, and text fields of the bundles they reference
   * (e.g. paragraph types), up to MAX_DEPTH levels deep.
   *
   * @return array<string, string>
   *   Path => label, e.g. "field_content.field_text" => "Content › Text".
   */
  public function candidatePaths(string $entity_type_id, string $bundle, int $depth = 1, string $prefix = '', string $label_prefix = ''): array {
    $paths = [];
    $text_types = $this->extractors->getTextFieldTypes();
    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $name => $definition) {
      if ($definition->getFieldStorageDefinition()->isBaseField() && $name !== 'title' && $name !== 'name' && $name !== 'description') {
        continue;
      }
      $path = $prefix . $name;
      $label = $label_prefix . $definition->getLabel();
      if (in_array($definition->getType(), $text_types, TRUE)) {
        $paths[$path] = $label;
        continue;
      }
      // References to entities with fields of their own (e.g. paragraphs).
      if ($depth < self::MAX_DEPTH && in_array($definition->getType(), ['entity_reference_revisions', 'entity_reference'], TRUE)) {
        $target = $definition->getSetting('target_type');
        if ($target === 'paragraph' || $definition->getType() === 'entity_reference_revisions') {
          $bundles = $definition->getSetting('handler_settings')['target_bundles'] ?? [];
          foreach ($bundles as $target_bundle) {
            $paths += $this->candidatePaths($target, $target_bundle, $depth + 1, "$path.", "$label › ");
          }
        }
      }
    }
    return $paths;
  }

}
