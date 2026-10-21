<?php

declare(strict_types=1);

namespace Drupal\translation_availability\Plugin\TextExtractor;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\translation_availability\Attribute\TextExtractor;
use Drupal\translation_availability\TextExtractorInterface;

/**
 * Plain text fields.
 */
#[TextExtractor(
  id: 'plain_text',
  label: new TranslatableMarkup('Plain text'),
  field_types: ['string', 'string_long'],
)]
final class PlainText extends PluginBase implements TextExtractorInterface {

  public function extract(FieldItemListInterface $items): string {
    return implode(' ', array_map(fn($item) => (string) $item->value, iterator_to_array($items)));
  }

}
