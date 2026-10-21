<?php

declare(strict_types=1);

namespace Drupal\translation_availability;

/**
 * Options for the filters and fields of the availability table in Views.
 *
 * Static, so Views can call them whenever it needs them ("options
 * callback"), also when the views data are cached.
 */
final class ViewsOptions {

  /**
   * The states of a translation.
   */
  public static function states(): array {
    return [
      'missing' => t('Missing'),
      'unpublished' => t('Unpublished'),
      'empty' => t('(Almost) empty'),
      'short' => t('Short'),
      'partial' => t('Partial'),
      'full' => t('Full'),
    ];
  }

  /**
   * The content types whose availability is tracked.
   */
  public static function trackedBundles(): array {
    $tracked = \Drupal::service('translation_availability.settings')->enabledBundles();
    return array_intersect_key(self::bundles(), array_flip($tracked));
  }

  /**
   * All content types (for their labels).
   */
  public static function bundles(): array {
    return array_map(fn($type) => $type->label(), \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple());
  }

  /**
   * The languages of the site (not "Not specified" and the like).
   */
  public static function languages(): array {
    return array_map(fn($language) => \Drupal::service('translation_availability.analyzer')->languageName($language->getId()), \Drupal::languageManager()->getLanguages());
  }

}
