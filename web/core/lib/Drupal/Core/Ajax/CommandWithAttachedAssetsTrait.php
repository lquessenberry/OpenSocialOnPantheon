<?php

namespace Drupal\Core\Ajax;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Asset\AttachedAssetsInterface;

/**
 * Trait for Ajax commands that render content and attach assets.
 *
 * @ingroup ajax
 */
trait CommandWithAttachedAssetsTrait {

  /**
   * The attached assets for this Ajax command.
   *
   * @var \Drupal\Core\Asset\AttachedAssets
   */
  protected $attachedAssets;

  /**
   * Processes the content for output.
   *
   * If content is a render array, it may contain attached assets to be
   * processed.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   HTML rendered content.
   */
  protected function getRenderedContent() {
    if (is_array($this->content)) {
      if (!$this->content) {
        return '';
      }
      $html = \Drupal::service('renderer')->renderRoot($this->content);
      $this->addAttachedAssets(AttachedAssets::createFromRenderArray($this->content));
      return $html;
    }
    else {
      return $this->content;
    }
  }

  /**
   * Adds new attached assets.
   *
   * @param \Drupal\Core\Asset\AttachedAssetsInterface $attached_assets
   *   The attachments to be added.
   */
  protected function addAttachedAssets(AttachedAssetsInterface $attached_assets) {
    if (!isset($this->attachedAssets)) {
      $this->attachedAssets = new AttachedAssets();
    }
    $this->attachedAssets->setLibraries(NestedArray::mergeDeep($this->attachedAssets->getLibraries(), $attached_assets->getLibraries()));
    $this->attachedAssets->setSettings(NestedArray::mergeDeep($this->attachedAssets->getSettings(), $attached_assets->getSettings()));
  }

  /**
   * Gets the attached assets.
   *
   * @return \Drupal\Core\Asset\AttachedAssets|null
   *   The attached assets for this command.
   */
  public function getAttachedAssets() {
    return $this->attachedAssets;
  }

}
