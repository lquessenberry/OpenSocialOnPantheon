<?php

namespace Drupal\search_api\Plugin\search_api\display;

use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents a Views block display.
 *
 * @SearchApiDisplay(
 *   id = "views_block",
 *   views_display_type = "block",
 *   deriver = "Drupal\search_api\Plugin\search_api\display\ViewsDisplayDeriver"
 * )
 */
class ViewsBlock extends ViewsDisplayBase {

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface|null
   */
  protected ?ThemeManagerInterface $themeManager;

  /**
   * @inheritDoc
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->setThemeManager($container->get('theme.manager'));
    return $plugin;
  }

  /**
   * Retrieves the theme manager.
   *
   * @return \Drupal\Core\Theme\ThemeManagerInterface
   *   The theme manager.
   */
  public function getThemeManager(): ThemeManagerInterface {
    return $this->themeManager ?: \Drupal::service('theme.manager');
  }

  /**
   * Sets the theme manager.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The new theme manager.
   *
   * @return $this
   */
  public function setThemeManager(ThemeManagerInterface $theme_manager): self {
    $this->themeManager = $theme_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isRenderedInCurrentRequest() {
    // There can be more than one block rendering the display. If any block is
    // rendered, we return TRUE.
    $plugin_id = 'views_block:' . $this->pluginDefinition['view_id'] . '-' . $this->pluginDefinition['view_display'];
    $blocks = $this->getEntityTypeManager()
      ->getStorage('block')
      ->loadByProperties([
        'plugin' => $plugin_id,
        'theme' => $this->getThemeManager()->getActiveTheme()->getName(),
      ]);
    /** @var \Drupal\block\BlockInterface $block */
    foreach ($blocks as $block) {
      if ($block->access('view')) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
