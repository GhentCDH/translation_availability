<?php

declare(strict_types=1);

namespace Drupal\translation_availability\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\translation_availability\AvailabilityAnalyzer;
use Drupal\translation_availability\AvailabilityStorage;
use Drupal\translation_availability\ViewsOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Per language, how many translations are missing or (almost) empty, with
 * links to those in the report. For a dashboard.
 */
#[Block(
  id: 'translation_availability_summary',
  admin_label: new TranslatableMarkup('Translations to do'),
  category: new TranslatableMarkup('Translation availability'),
)]
final class AvailabilitySummaryBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The states that ask for work, and what to call them here.
   */
  private const STATES = ['missing', 'empty'];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly AvailabilityStorage $storage,
    private readonly AvailabilityAnalyzer $analyzer,
    private readonly LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('translation_availability.storage'),
      $container->get('translation_availability.analyzer'),
      $container->get('language_manager'),
    );
  }

  public function defaultConfiguration(): array {
    return ['bundles' => []];
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form['bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types'),
      '#description' => $this->t('All tracked content types when none is ticked.'),
      '#options' => ViewsOptions::trackedBundles(),
      '#default_value' => $this->configuration['bundles'],
    ];
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['bundles'] = array_values(array_filter($form_state->getValue('bundles')));
  }

  protected function blockAccess(AccountInterface $account): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'view translation availability report');
  }

  public function build(): array {
    $bundles = $this->configuration['bundles'];
    $counts = $this->storage->counts('node', $bundles);
    $labels = ViewsOptions::states();
    $default = $this->languageManager->getDefaultLanguage()->getId();
    $rows = [];
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $row = [$this->analyzer->languageName($langcode)];
      $todo = 0;
      foreach (self::STATES as $state) {
        $total = $counts[$langcode][$state] ?? 0;
        $todo += $total;
        $query = ['langcode' => $langcode, 'state' => $state] + (count($bundles) === 1 ? ['type' => reset($bundles)] : []);
        $row[] = $total
          ? ['data' => ['#type' => 'link', '#title' => (string) $total, '#url' => Url::fromRoute('view.translation_availability.page', [], ['query' => $query])]]
          : '0';
      }
      // The original language is rarely missing: leave it out when all is done.
      if ($todo || $langcode !== $default) {
        $rows[] = $row;
      }
    }
    return [
      '#type' => 'table',
      '#header' => [$this->t('Language'), $labels['missing'], $labels['empty']],
      '#rows' => $rows,
      '#empty' => $this->t('Nothing to translate.'),
      '#cache' => ['tags' => [AvailabilityStorage::CACHE_TAG, 'config:language_list']],
    ];
  }

}
