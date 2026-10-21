<?php

declare(strict_types=1);

namespace Drupal\translation_availability\Plugin\TextExtractor;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\translation_availability\Attribute\TextExtractor;
use Drupal\translation_availability\TextExtractorInterface;

/**
 * Formatted text: the value (and summary) without HTML.
 */
#[TextExtractor(
  id: 'formatted_text',
  label: new TranslatableMarkup('Formatted text'),
  field_types: ['text', 'text_long', 'text_with_summary'],
)]
final class FormattedText extends PluginBase implements TextExtractorInterface {

  public function extract(FieldItemListInterface $items): string {
    $texts = [];
    foreach ($items as $item) {
      foreach (['summary', 'value'] as $property) {
        if (isset($item->{$property}) && $item->{$property} !== '') {
          // Keep words apart where tags separate them (<p>a</p><p>b</p>).
          $texts[] = strip_tags(str_replace('<', ' <', (string) $item->{$property}));
        }
      }
    }
    return html_entity_decode(implode(' ', $texts), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }

}
