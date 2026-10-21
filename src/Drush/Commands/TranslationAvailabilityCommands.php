<?php

declare(strict_types=1);

namespace Drupal\translation_availability\Drush\Commands;

use Drupal\translation_availability\AvailabilityAnalyzer;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands of Translation availability.
 */
final class TranslationAvailabilityCommands extends DrushCommands {

  public function __construct(private readonly AvailabilityAnalyzer $analyzer) {
    parent::__construct();
  }

  public static function create(ContainerInterface $container): self {
    return new self($container->get('translation_availability.analyzer'));
  }

  /**
   * Counts the words of all translations of the tracked content again.
   *
   * Needed after changing the fields or thresholds of a content type, or
   * after adding a language.
   */
  #[CLI\Command(name: 'translation-availability:refresh', aliases: ['tavr'])]
  #[CLI\Option(name: 'bundle', description: 'Only this content type.')]
  #[CLI\Usage(name: 'drush translation-availability:refresh', description: 'Recount all tracked content types.')]
  public function refresh(array $options = ['bundle' => NULL]): void {
    $count = $this->analyzer->refreshAll('node', $options['bundle'] ?: NULL);
    $this->logger()->success(dt('Counted @count items.', ['@count' => $count]));
  }

}
