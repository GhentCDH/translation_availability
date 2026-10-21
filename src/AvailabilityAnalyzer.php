<?php

declare(strict_types=1);

namespace Drupal\translation_availability;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * The availability of the translations of an entity.
 *
 * States of a translation, from worst to best:
 * - missing: there is no translation in this language;
 * - unpublished: there is one, but it is not published;
 * - empty: fewer words than the "empty" threshold;
 * - short: fewer words than the "short" threshold;
 * - partial: fewer words than the "full" threshold;
 * - full: at least the "full" threshold.
 */
final class AvailabilityAnalyzer {

  public const STATES = ['missing', 'unpublished', 'empty', 'short', 'partial', 'full'];

  public function __construct(
    private readonly AvailabilitySettings $settings,
    private readonly WordCounter $counter,
    private readonly AvailabilityStorage $storage,
    private readonly LanguageManagerInterface $languageManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Whether the availability of an entity is tracked.
   */
  public function applies(ContentEntityInterface $entity): bool {
    return $entity->isTranslatable() && $this->settings->isEnabled($entity->bundle(), $entity->getEntityTypeId());
  }

  /**
   * Calculates and stores the availability of all languages of an entity.
   */
  public function refresh(ContentEntityInterface $entity): void {
    if (!$this->applies($entity)) {
      $this->storage->delete($entity->getEntityTypeId(), (int) $entity->id());
      return;
    }
    $this->storage->save($entity->getEntityTypeId(), (int) $entity->id(), $entity->bundle(), $this->calculate($entity));
  }

  /**
   * Calculates the availability of all languages of an entity.
   *
   * @return array<string, array{state: string, words: int}>
   */
  public function calculate(ContentEntityInterface $entity): array {
    $settings = $this->settings->forBundle($entity->bundle(), $entity->getEntityTypeId());
    $rows = [];
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      if (!$entity->hasTranslation($langcode)) {
        $rows[$langcode] = ['state' => 'missing', 'words' => 0];
        continue;
      }
      $translation = $entity->getTranslation($langcode);
      $words = $this->counter->count($translation, $settings['fields']);
      $published = !$translation instanceof EntityPublishedInterface || $translation->isPublished();
      $rows[$langcode] = [
        'state' => $published ? $this->stateForWords($words, $settings['thresholds']) : 'unpublished',
        'words' => $words,
      ];
    }
    return $rows;
  }

  /**
   * Returns the availability of all languages of an entity (stored, or calculated
   * when it was not stored yet).
   *
   * @return array<string, array{state: string, words: int}>
   */
  public function availability(ContentEntityInterface $entity): array {
    if (!$this->applies($entity)) {
      return [];
    }
    $rows = $this->storage->load($entity->getEntityTypeId(), (int) $entity->id());
    if (count($rows) < count($this->languageManager->getLanguages())) {
      $rows = $this->calculate($entity);
    }
    return array_map(fn($row) => ['state' => $row['state'], 'words' => (int) $row['words']], $rows);
  }

  /**
   * Returns the state of one translation (current content language by
   * default), or NULL when availability is not tracked for the entity.
   */
  public function state(ContentEntityInterface $entity, ?string $langcode = NULL): ?string {
    $langcode ??= $this->currentLangcode();
    return $this->availability($entity)[$langcode]['state'] ?? NULL;
  }

  /**
   * Returns the translations that are substantially better than the one in
   * the given (or current) language, or NULL if there are none.
   *
   * A translation is suggested when the current one is missing or
   * unpublished, or when it is (almost) empty and the other one has many
   * times more words ("ratio"), or when the current one is short and the
   * other one is full.
   *
   * @return array{state: ?string, current_words: int, alternatives: array<string, array{language_name: string, title: string, url: string, words: int, state: string}>}|null
   */
  public function suggestions(ContentEntityInterface $entity, ?string $langcode = NULL): ?array {
    $langcode ??= $this->currentLangcode();
    $availability = $this->availability($entity);
    if (!$availability || !isset($availability[$langcode])) {
      return NULL;
    }
    $thresholds = $this->settings->forBundle($entity->bundle(), $entity->getEntityTypeId())['thresholds'];
    $state = $availability[$langcode]['state'];
    $words = in_array($state, ['missing', 'unpublished'], TRUE) ? 0 : $availability[$langcode]['words'];
    if ($words >= $thresholds['short']) {
      return NULL;
    }

    $alternatives = [];
    foreach ($availability as $other => $row) {
      if ($other === $langcode || in_array($row['state'], ['missing', 'unpublished'], TRUE) || !$row['words']) {
        continue;
      }
      $better = $words === 0
        || ($words < $thresholds['empty'] && $words * $thresholds['ratio'] < $row['words'])
        || $row['words'] >= $thresholds['full'];
      if (!$better) {
        continue;
      }
      $translation = $entity->getTranslation($other);
      $language = $this->languageManager->getLanguage($other);
      $alternatives[$other] = [
        'language_name' => $this->languageName($other),
        'title' => $translation->label(),
        'url' => $translation->toUrl('canonical', ['language' => $language])->toString(),
        'words' => $row['words'],
        'state' => $row['state'],
      ];
    }
    if (!$alternatives) {
      return NULL;
    }

    // Most words first; leave out the ones far below the best.
    uasort($alternatives, fn($a, $b) => $b['words'] <=> $a['words']);
    $max = reset($alternatives)['words'];
    $alternatives = array_filter($alternatives, fn($alternative) => $alternative['words'] === $max
      || $alternative['words'] >= $thresholds['full']
      || $alternative['words'] * $thresholds['ratio'] > $max);

    return ['state' => $state, 'current_words' => $words, 'alternatives' => $alternatives];
  }

  /**
   * Returns the published translations of an entity, for links.
   *
   * @return array<string, array{language_name: string, title: string, url: string}>
   */
  public function publishedTranslations(ContentEntityInterface $entity): array {
    $links = [];
    foreach ($entity->getTranslationLanguages() as $langcode => $language) {
      $translation = $entity->getTranslation($langcode);
      if ($translation instanceof EntityPublishedInterface && !$translation->isPublished()) {
        continue;
      }
      $links[$langcode] = [
        'language_name' => $this->languageName($langcode),
        'title' => $translation->label(),
        'url' => $translation->toUrl('canonical', ['language' => $language])->toString(),
      ];
    }
    return $links;
  }

  /**
   * Returns the state for a number of words.
   */
  public function stateForWords(int $words, array $thresholds): string {
    return match (TRUE) {
      $words < $thresholds['empty'] => 'empty',
      $words < $thresholds['short'] => 'short',
      $words < $thresholds['full'] => 'partial',
      default => 'full',
    };
  }

  /**
   * Recalculates all entities of the tracked bundles (or of one bundle).
   *
   * @return int
   *   The number of entities.
   */
  public function refreshAll(string $entity_type_id = 'node', ?string $bundle = NULL, ?callable $progress = NULL): int {
    $bundles = $bundle ? [$bundle] : $this->settings->enabledBundles($entity_type_id);
    if (!$bundle) {
      $this->storage->deleteOtherBundles($entity_type_id, $bundles);
    }
    if (!$bundles) {
      return 0;
    }
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $bundle_key = $this->entityTypeManager->getDefinition($entity_type_id)->getKey('bundle');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition($bundle_key, $bundles, 'IN')->execute();
    foreach (array_chunk($ids, 50) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $entity) {
        $this->refresh($entity);
      }
      $storage->resetCache($chunk);
      if ($progress) {
        $progress(count($chunk));
      }
    }
    return count($ids);
  }

  /**
   * Returns the name of a language in the language of the interface.
   */
  public function languageName(string $langcode): string {
    $language = $this->languageManager->getLanguage($langcode);
    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
    return $language ? (string) t($language->getName()) : $langcode;
  }

  private function currentLangcode(): string {
    return $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
  }

}
