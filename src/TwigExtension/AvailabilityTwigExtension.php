<?php

declare(strict_types=1);

namespace Drupal\translation_availability\TwigExtension;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\translation_availability\AvailabilityAnalyzer;
use Drupal\translation_availability\WordCounter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig functions for templates.
 *
 * - translation_state(node, langcode?): missing, unpublished, empty, short,
 *   partial, full (NULL when not tracked).
 * - translation_suggestions(node, langcode?): better translations, see
 *   AvailabilityAnalyzer::suggestions().
 * - translation_availability(node): the state and words per language.
 * - reading_time(node, words_per_minute = 250): minutes, from the counted
 *   words of the current language.
 */
final class AvailabilityTwigExtension extends AbstractExtension {

  public function __construct(private readonly AvailabilityAnalyzer $analyzer) {}

  public function getFunctions(): array {
    return [
      new TwigFunction('translation_state', fn(?ContentEntityInterface $entity, ?string $langcode = NULL) => $entity ? $this->analyzer->state($entity, $langcode) : NULL),
      new TwigFunction('translation_suggestions', fn(?ContentEntityInterface $entity, ?string $langcode = NULL) => $entity ? $this->analyzer->suggestions($entity, $langcode) : NULL),
      new TwigFunction('translation_availability', fn(?ContentEntityInterface $entity) => $entity ? $this->analyzer->availability($entity) : []),
      new TwigFunction('reading_time', function (?ContentEntityInterface $entity, int $words_per_minute = 250): ?int {
        $words = $entity ? ($this->analyzer->availability($entity)[$entity->language()->getId()]['words'] ?? NULL) : NULL;
        return $words === NULL ? NULL : max(1, (int) ceil($words / $words_per_minute));
      }),
    ];
  }

}
