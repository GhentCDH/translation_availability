<?php

declare(strict_types=1);

namespace Drupal\translation_availability\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * The global settings: default thresholds and the language switcher.
 */
final class SettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'translation_availability_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['translation_availability.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('translation_availability.settings');
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Translation availability is enabled per content type, with the fields that count as content: see the "Translation availability" tab when editing a <a href=":url">content type</a>. Run <code>drush translation-availability:refresh</code> after changing the fields or thresholds.', [
        ':url' => Url::fromRoute('entity.node_type.collection')->toString(),
      ]) . '</p>',
    ];
    $form['thresholds'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default thresholds (words)'),
      '#tree' => TRUE,
    ];
    foreach (_translation_availability_threshold_labels() as $key => $label) {
      $form['thresholds'][$key] = [
        '#type' => 'number',
        '#title' => $label,
        '#min' => 0,
        '#required' => TRUE,
        '#default_value' => $config->get("thresholds.$key"),
      ];
    }
    $form['mark_language_switcher'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mark the languages without a published translation in the language switcher'),
      '#description' => $this->t('Adds the class <code>translation-availability--unavailable</code> and a title to those links.'),
      '#default_value' => $config->get('mark_language_switcher'),
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('translation_availability.settings')
      ->set('thresholds', array_map('intval', $form_state->getValue('thresholds')))
      ->set('mark_language_switcher', (bool) $form_state->getValue('mark_language_switcher'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
