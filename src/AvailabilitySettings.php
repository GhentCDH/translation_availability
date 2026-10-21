<?php

declare(strict_types=1);

namespace Drupal\translation_availability;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * The settings of translation availability: global defaults and per bundle.
 *
 * Bundle settings are third party settings of the bundle entity (the content
 * type), edited on its form.
 */
final class AvailabilitySettings {

  /**
   * The defaults of a bundle.
   */
  public const BUNDLE_DEFAULTS = [
    'enabled' => FALSE,
    'fields' => [],
    'override_thresholds' => FALSE,
    'thresholds' => [],
    'missing' => 'original',
    'unpublished' => 'unavailable_page',
    'notice' => TRUE,
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the settings of a bundle (with the defaults filled in).
   */
  public function forBundle(string $bundle, string $entity_type_id = 'node'): array {
    $settings = self::BUNDLE_DEFAULTS;
    $bundle_entity_type = $this->entityTypeManager->getDefinition($entity_type_id)->getBundleEntityType();
    if ($bundle_entity_type && ($bundle_entity = $this->entityTypeManager->getStorage($bundle_entity_type)->load($bundle))) {
      $settings = $bundle_entity->getThirdPartySettings('translation_availability') + $settings;
    }
    $settings['thresholds'] = ($settings['override_thresholds'] ? array_filter($settings['thresholds'], 'is_numeric') : []) + $this->defaultThresholds();
    return $settings;
  }

  /**
   * Whether availability is tracked for a bundle.
   */
  public function isEnabled(string $bundle, string $entity_type_id = 'node'): bool {
    $settings = $this->forBundle($bundle, $entity_type_id);
    return $settings['enabled'] && $settings['fields'];
  }

  /**
   * Returns the bundles with tracking enabled.
   *
   * @return string[]
   */
  public function enabledBundles(string $entity_type_id = 'node'): array {
    $bundle_entity_type = $this->entityTypeManager->getDefinition($entity_type_id)->getBundleEntityType();
    $bundles = [];
    foreach ($this->entityTypeManager->getStorage($bundle_entity_type)->loadMultiple() as $id => $bundle) {
      if ($bundle->getThirdPartySetting('translation_availability', 'enabled') && $bundle->getThirdPartySetting('translation_availability', 'fields')) {
        $bundles[] = (string) $id;
      }
    }
    return $bundles;
  }

  /**
   * Returns the global thresholds.
   *
   * @return array{empty: int, short: int, full: int, ratio: int}
   */
  public function defaultThresholds(): array {
    return ($this->configFactory->get('translation_availability.settings')->get('thresholds') ?? []) + [
      'empty' => 10,
      'short' => 200,
      'full' => 1000,
      'ratio' => 10,
    ];
  }

  public function markLanguageSwitcher(): bool {
    return (bool) $this->configFactory->get('translation_availability.settings')->get('mark_language_switcher');
  }

}
