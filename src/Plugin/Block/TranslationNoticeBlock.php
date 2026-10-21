<?php

declare(strict_types=1);

namespace Drupal\translation_availability\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\translation_availability\AvailabilityAnalyzer;
use Drupal\translation_availability\AvailabilitySettings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A notice on a node page when another language is substantially better.
 */
#[Block(
  id: 'translation_availability_notice',
  admin_label: new TranslatableMarkup('Translation notice'),
  category: new TranslatableMarkup('Multilingual'),
)]
final class TranslationNoticeBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly AvailabilityAnalyzer $analyzer,
    private readonly AvailabilitySettings $settings,
    private readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('translation_availability.analyzer'),
      $container->get('translation_availability.settings'),
      $container->get('current_route_match'),
    );
  }

  public function build(): array {
    $node = $this->routeMatch->getParameter('node');
    $build = [];
    if ($node instanceof NodeInterface && $this->routeMatch->getRouteName() === 'entity.node.canonical'
      && $this->analyzer->applies($node) && $this->settings->forBundle($node->bundle())['notice']
      && ($suggestions = $this->analyzer->suggestions($node))) {
      $build = [
        '#theme' => 'translation_availability_notice',
        '#state' => $suggestions['state'],
        '#language_name' => $this->analyzer->languageName(\Drupal::languageManager()->getCurrentLanguage()->getId()),
        '#current_words' => $suggestions['current_words'],
        '#alternatives' => $suggestions['alternatives'],
      ];
    }
    $build['#cache'] = [
      'contexts' => ['route', 'languages:language_content'],
      'tags' => $node instanceof NodeInterface ? $node->getCacheTags() : [],
    ];
    return $build;
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route', 'languages:language_content']);
  }

}
