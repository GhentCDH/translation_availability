<?php

declare(strict_types=1);

namespace Drupal\translation_availability;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Gets the readable text of a field, to count its words.
 *
 * Add a plugin (src/Plugin/TextExtractor) to count other field types.
 */
interface TextExtractorInterface {

  /**
   * Returns the readable text of the field items, without markup.
   */
  public function extract(FieldItemListInterface $items): string;

}
