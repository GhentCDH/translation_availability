<?php

declare(strict_types=1);

namespace Drupal\translation_availability\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a text extractor: gets the readable text of a field type.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TextExtractor extends Plugin {

  /**
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label.
   * @param string[] $field_types
   *   The field types this extractor reads.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly array $field_types = [],
  ) {}

}
