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
    // Only the languages of the site.
    'filter' => ['id' => 'in_operator', 'options callback' => '\Drupal\translation_availability\ViewsOptions::languages'],
    'argument' => ['id' => 'language'],
    'sort' => ['id' => 'standard'],
  ];
  $data['translation_availability']['bundle'] = [
    'title' => t('Content type'),
    'field' => ['id' => 'machine_name', 'options callback' => '\Drupal\translation_availability\ViewsOptions::bundles'],
    'filter' => ['id' => 'in_operator', 'options callback' => '\Drupal\translation_availability\ViewsOptions::trackedBundles'],
    'sort' => ['id' => 'standard'],
  ];
  $data['translation_availability']['state'] = [
    'title' => t('State'),
    'help' => t('missing, unpublished, empty, short, partial or full.'),
    'field' => ['id' => 'machine_name', 'options callback' => '\Drupal\translation_availability\ViewsOptions::states'],
    'filter' => ['id' => 'in_operator', 'options callback' => '\Drupal\translation_availability\ViewsOptions::states'],
    'sort' => ['id' => 'standard'],
  ];
  $data['translation_availability']['words'] = [
    'title' => t('Words'),
    'help' => t('The number of words in the fields that count as content.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
  ];
  $data['translation_availability']['operation'] = [
    'title' => t('Edit or add the translation'),
    'help' => t('Edit the translation, or add it when it is missing.'),
    'real field' => 'entity_id',
    'field' => ['id' => 'translation_availability_operation', 'additional fields' => ['langcode']],
  ];
  $data['translation_availability']['updated'] = [
    'title' => t('Counted'),
    'field' => ['id' => 'date'],
    'sort' => ['id' => 'date'],
  ];
  return $data;
}
