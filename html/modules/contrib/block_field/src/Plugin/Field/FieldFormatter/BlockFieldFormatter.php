<?php

namespace Drupal\block_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'block_field' formatter.
 *
 * @FieldFormatter(
 *   id = "block_field",
 *   label = @Translation("Block field"),
 *   field_types = {
 *     "block_field"
 *   }
 * )
 */
class BlockFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      /** @var \Drupal\block_field\BlockFieldItemInterface $item */
      $block_instance = $item->getBlock();
      // Make sure the block exists and is accessible.
      if (!$block_instance || !$block_instance->access(\Drupal::currentUser())) {
        continue;
      }

      // @see \Drupal\block\BlockViewBuilder::buildPreRenderableBlock
      // @see template_preprocess_block()
      $elements[$delta] = [
        '#theme' => 'block',
        '#attributes' => [],
        '#configuration' => $block_instance->getConfiguration(),
        '#plugin_id' => $block_instance->getPluginId(),
        '#base_plugin_id' => $block_instance->getBaseId(),
        '#derivative_plugin_id' => $block_instance->getDerivativeId(),
        'content' => $block_instance->build(),
      ];

      /** @var \Drupal\Core\Render\RendererInterface $renderer */
      $renderer = \Drupal::service('renderer');
      $renderer->addCacheableDependency($elements[$delta], $block_instance);
    }
    return $elements;
  }

}
