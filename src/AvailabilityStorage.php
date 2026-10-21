<?php

declare(strict_types=1);

namespace Drupal\translation_availability;

use Drupal\Core\Database\Connection;

/**
 * Stores the availability of each translation (table translation_availability).
 */
final class AvailabilityStorage {

  public function __construct(private readonly Connection $database) {}

  /**
   * Replaces the rows of an entity.
   *
   * @param array<string, array{state: string, words: int}> $rows
   *   Keyed by langcode.
   */
  public function save(string $entity_type_id, int $id, string $bundle, array $rows): void {
    $transaction = $this->database->startTransaction();
    $this->delete($entity_type_id, $id);
    if (!$rows) {
      return;
    }
    $insert = $this->database->insert('translation_availability')
      ->fields(['entity_type', 'entity_id', 'langcode', 'bundle', 'state', 'words', 'updated']);
    foreach ($rows as $langcode => $row) {
      $insert->values([$entity_type_id, $id, $langcode, $bundle, $row['state'], $row['words'], \Drupal::time()->getRequestTime()]);
    }
    $insert->execute();
    unset($transaction);
  }

  /**
   * Returns the rows of an entity, keyed by langcode.
   *
   * @return array<string, array{state: string, words: int}>
   */
  public function load(string $entity_type_id, int $id): array {
    return $this->database->select('translation_availability', 't')
      ->fields('t', ['langcode', 'state', 'words'])
      ->condition('entity_type', $entity_type_id)
      ->condition('entity_id', $id)
      ->execute()
      ->fetchAllAssoc('langcode', \PDO::FETCH_ASSOC);
  }

  public function delete(string $entity_type_id, int $id): void {
    $this->database->delete('translation_availability')
      ->condition('entity_type', $entity_type_id)
      ->condition('entity_id', $id)
      ->execute();
  }

  /**
   * Deletes the rows of the bundles that are no longer tracked.
   *
   * @param string[] $bundles
   *   The tracked bundles.
   */
  public function deleteOtherBundles(string $entity_type_id, array $bundles): void {
    $delete = $this->database->delete('translation_availability')->condition('entity_type', $entity_type_id);
    if ($bundles) {
      $delete->condition('bundle', $bundles, 'NOT IN');
    }
    $delete->execute();
  }

}
