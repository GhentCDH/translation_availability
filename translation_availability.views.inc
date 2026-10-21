<?php

/**
 * @file
 * Views integration of Translation availability: the availability table as a base
 * table, with a relationship to the content.
 */

declare(strict_types=1);

/**
 * Implements hook_views_data().
 */
function translation_availability_views_data(): array {
  $data['translation_availability']['table'] = [
    'group' => t('Translation availability'),
    'provider' => 'translation_availability',
    'base' => [
      'field' => 'entity_id',
      'title' => t('Translation availability'),
      'help' => t('The availability of each translation (also the missing ones) of the tracked content.'),
      'weight' => 10,
    ],
  ];
  $data['translation_availability']['entity_id'] = [
    'title' => t('Content ID'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'argument' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'relationship' => [
      'title' => t('Content'),
      'help' => t('The content (in its original language).'),
      'id' => 'standard',
      'base' => 'node_field_data',
      'base field' => 'nid',
      'label' => t('Content'),
      'extra' => [['field' => 'default_langcode', 'value' => 1, 'numeric' => TRUE]],
    ],
  ];
  $data['translation_availability']['langcode'] = [
    'title' => t('Language'),
    'help' => t('The language of the translation.'),
    'field' => ['id' => 'language'],
    'filter' => ['id' => 'language'],
    'argument' => ['id' => 'language'],
    'sort' => ['id' => 'standard'],
  ];
  $data['translation_availability']['bundle'] = [
    'title' => t('Content type'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'in_operator', 'options callback' => '_translation_availability_bundle_options'],
    'sort' => ['id' => 'standard'],
  ];
  $data['translation_availability']['state'] = [
    'title' => t('State'),
    'help' => t('missing, unpublished, empty, short, partial or full.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'in_operator', 'options callback' => '_translation_availability_state_options'],
    'sort' => ['id' => 'standard'],
  ];
  $data['translation_availability']['words'] = [
    'title' => t('Words'),
    'help' => t('The number of words in the fields that count as content.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
  ];
  $data['translation_availability']['updated'] = [
    'title' => t('Counted'),
    'field' => ['id' => 'date'],
    'sort' => ['id' => 'date'],
  ];
  return $data;
}

/**
 * Returns the states, for the state filter.
 */
function _translation_availability_state_options(): array {
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
 * Returns the tracked content types, for the content type filter.
 */
function _translation_availability_bundle_options(): array {
  $options = [];
  $types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple(\Drupal::service('translation_availability.settings')->enabledBundles());
  foreach ($types as $id => $type) {
    $options[$id] = $type->label();
  }
  return $options;
}
