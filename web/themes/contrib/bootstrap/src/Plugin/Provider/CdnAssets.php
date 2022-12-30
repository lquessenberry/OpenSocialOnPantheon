<?php

namespace Drupal\bootstrap\Plugin\Provider;

use Drupal\bootstrap\Utility\Crypt;
use Drupal\Component\Render\MarkupInterface;

/**
 * Class CdnAssets.
 */
class CdnAssets {

  /**
   * An array of CdnAsset objects.
   *
   * @var \Drupal\bootstrap\Plugin\Provider\CdnAsset[]
   */
  protected $assets = [];

  /**
   * The human readable label for these assets.
   *
   * @var \Drupal\Component\Render\MarkupInterface
   */
  protected $label;

  /**
   * The library associated with these assets.
   *
   * @var string
   */
  protected $library;

  /**
   * CdnAssets constructor.
   *
   * @param \Drupal\bootstrap\Plugin\Provider\CdnAsset[] $assets
   *   Optional. An array of CdnAsset objects to set.
   */
  public function __construct(array $assets = []) {
    $this->appendAssets($assets);
  }

  /**
   * Retrieves all assets.
   *
   * @param bool|bool[] $minified
   *   Flag indicating whether only the minified asset should be retrieved.
   *   This can be an associative array where the key is the asset type and
   *   the value is a boolean indicating whether to use minified assets for
   *   that specific type. If not set, all assets are retrieved regardless
   *   if they are minified or not.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\CdnAsset[]
   *   An array of CdnAsset objects.
   */
  public function all($minified = NULL) {
    $assets = [];
    if (isset($minified) && !is_array($minified)) {
      $minified = ['css' => !!$minified, 'js' => !!$minified];
    }
    foreach (['css', 'js'] as $type) {
      $assets = array_merge($assets, $this->get($type, isset($minified[$type]) ? $minified[$type] : NULL));
    }
    return $assets;
  }

  /**
   * Appends a CdnAsset object to the list.
   *
   * @param \Drupal\bootstrap\Plugin\Provider\CdnAsset $asset
   *   A CdnAsset object.
   */
  public function append(CdnAsset $asset) {
    if (isset($this->assets[$asset->getId()])) {
      $this->assets[$asset->getId()] = $asset;
    }
    else {
      $this->assets = array_merge($this->assets, [$asset->getId() => $asset]);
    }
  }

  /**
   * Appends an array of CdnAsset objects to the list.
   *
   * @param \Drupal\bootstrap\Plugin\Provider\CdnAsset[] $assets
   *   An array of CdnAsset objects.
   */
  public function appendAssets(array $assets) {
    foreach ($assets as $asset) {
      $this->append($asset);
    }
  }

  /**
   * Retrieves specific types of assets.
   *
   * @param string $type
   *   The type of assets to retrieve (e.g. css or js).
   * @param bool|bool[] $minified
   *   Flag indicating whether only the minified asset should be retrieved.
   *   This can be an associative array where the key is the asset type and
   *   the value is a boolean indicating whether to use minified assets for
   *   that specific type. If not set, all assets are retrieved regardless
   *   if they are minified or not.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\CdnAsset[]
   *   An array of CdnAsset objects.
   */
  public function get($type, $minified = NULL) {
    // Filter by type.
    $assets = array_filter($this->assets, function (CdnAsset $asset) use ($type) {
      return $asset->getType() === $type;
    });

    // Filter assets by matching minification value.
    if (isset($minified)) {
      $assets = array_filter($assets, function (CdnAsset $asset) use ($minified) {
        return $asset->isMinified() === $minified;
      });
    }

    return $assets;
  }

  /**
   * Retrieves the human readable label.
   *
   * Note: if the label isn't yet set, it will attempt to retrieve the label
   * from the first available asset.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The label.
   */
  public function getLabel() {
    if (!isset($this->label)) {
      $asset = reset($this->assets);
      $this->label = $asset ? $asset->getLabel() : NULL;
    }
    return $this->label;
  }

  /**
   * Retrieves the library associated with these assets.
   *
   * Note: if the library isn't yet set, it will attempt to retrieve the library
   * from the first available asset.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The library.
   */
  public function getLibrary() {
    if (!isset($this->library)) {
      $asset = reset($this->assets);
      $this->library = $asset ? $asset->getLibrary() : NULL;
    }
    return $this->library;
  }

  /**
   * Retrieves a specific theme.
   *
   * @param string $theme
   *   The theme to return. If not specified, the first available theme will
   *   be returned.
   *
   * @return static
   */
  public function getTheme($theme = NULL) {
    $themes = $this->getThemes();
    if (!$theme) {
      return reset($themes) ?: new static();
    }
    if (isset($themes[$theme])) {
      return $themes[$theme];
    }
    return new static();
  }

  /**
   * Groups available assets by theme.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\CdnAssets[]
   *   A collection of newly created CdnAssets objects, keyed by theme name.
   */
  public function getThemes() {
    /** @var \Drupal\bootstrap\Plugin\Provider\CdnAssets[] $themes */
    $themes = [];
    foreach ($this->assets as $asset) {
      $theme = $asset->getTheme();
      if (!isset($themes[$theme])) {
        $themes[$theme] = (new static())
          ->setLabel($asset->getLabel())
          ->setLibrary($asset->getLibrary());
      }
      $themes[$theme]->append($asset);
    }

    // Sort the themes.
    uksort($themes, [$this, 'sortThemes']);

    // Post process the themes to fill in any missing assets.
    $bootstrap = isset($themes['bootstrap']) ? $themes['bootstrap'] : new static();
    foreach (array_keys($themes) as $theme) {
      // The example Bootstrap theme are just overrides, it requires the main
      // bootstrap library CSS to be loaded first.
      if ($theme === 'bootstrap_theme') {
        if ($css = $bootstrap->get('css', TRUE)) {
          $themes['bootstrap_theme']->prependAssets($css);
        }
        if ($css = $bootstrap->get('css', FALSE)) {
          $themes['bootstrap_theme']->prependAssets($css);
        }
      }

      // Populate missing JavaScript.
      if (!$themes[$theme]->get('js', TRUE)) {
        if ($js = $bootstrap->get('js', FALSE)) {
          $themes[$theme]->appendAssets($js);
        }
        if ($js = $bootstrap->get('js', TRUE)) {
          $themes[$theme]->appendAssets($js);
        }
      }
    }

    return $themes;
  }

  /**
   * Merges another CdnAssets object onto this one.
   *
   * @param \Drupal\bootstrap\Plugin\Provider\CdnAssets $assets
   *   A CdnAssets object.
   *
   * @return static
   */
  public function merge(CdnAssets $assets) {
    $this->appendAssets($assets->toArray());
    return $this;
  }

  /**
   * Prepends a CdnAsset object to the list.
   *
   * @param \Drupal\bootstrap\Plugin\Provider\CdnAsset $asset
   *   A CdnAsset object.
   */
  public function prepend(CdnAsset $asset) {
    if (isset($this->assets[$asset->getId()])) {
      $this->assets[$asset->getId()] = $asset;
    }
    else {
      $this->assets = array_merge([$asset->getId() => $asset], $this->assets);
    }
  }

  /**
   * Prepends an array of CdnAsset objects to the list.
   *
   * @param \Drupal\bootstrap\Plugin\Provider\CdnAsset[] $assets
   *   An array of CdnAsset objects.
   */
  public function prependAssets(array $assets) {
    foreach (array_reverse($assets) as $asset) {
      $this->prepend($asset);
    }
  }

  /**
   * Retrieves all the set CDN Asset objects, as an array.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\CdnAsset[]
   *   The CDN Asset objects.
   */
  public function toArray() {
    return $this->assets;
  }

  /**
   * Converts the CDN Assets into an array suitable for a Drupal library array.
   *
   * @param bool $minified
   *   Flag indicating whether to use minified assets.
   *
   * @return array
   *   An array structured for use in a Drupal library.
   */
  public function toLibraryArray($minified = NULL) {
    $assets = $this->all($minified);
    $library = [];

    // Iterate over each type.
    foreach ($assets as $asset) {
      $url = (string) $asset;
      $type = $asset->getType();
      $data = ['data' => $url, 'type' => 'external'];

      // Attempt to add a corresponding SRI attribute for the URL.
      // @see https://developer.mozilla.org/en-US/docs/Web/Security/Subresource_Integrity
      foreach (['sha512', 'sha384', 'sha256', 'sha', 'hash', 'sri', 'integrity'] as $key) {
        if ($integrity = $asset->getInfo($key)) {
          // Parse the SRI integrity value to extract both the algorithm and
          // hash. Note: this is needed as some APIs do not prepend the hash
          // with the actual algorithm used. This is likely because the field,
          // while a valid base64 encoded hash, isn't specifically intended for
          // use as an SRI integrity attribute value.
          list($algorithm, $hash) = Crypt::parseSriIntegrity($integrity);

          // Ensure the algorithm and hash are valid.
          if (Crypt::checkBase64HashAlgorithm($algorithm, $hash, TRUE)) {
            $data['attributes'] = [
              'integrity' => "$algorithm-$hash",
              'crossorigin' => $asset->getInfo('crossorigin', 'anonymous'),
            ];
          }
          break;
        }
      }

      // CSS library assets use "SMACSS" categorization, assign to "base".
      if ($type === 'css') {
        $library[$type]['base'][$url] = $data;
      }
      else {
        $library[$type][$url] = $data;
      }
    }

    return $library;
  }

  /**
   * Sets the label.
   *
   * @param \Drupal\Component\Render\MarkupInterface $label
   *   The label to set.
   *
   * @return static
   */
  public function setLabel(MarkupInterface $label) {
    $this->label = $label;
    return $this;
  }

  /**
   * Sets the library associated with these assets.
   *
   * @param string $library
   *   The library to set.
   *
   * @return static
   */
  public function setLibrary($library) {
    $this->library = $library;
    return $this;
  }

  /**
   * Sorts themes.
   *
   * @param string $a
   *   First theme to compare.
   * @param string $b
   *   Second theme to compare.
   *
   * @return false|int|string
   *   The comparision value, similar to other comparison functions.
   */
  protected function sortThemes($a, $b) {
    $order = ['bootstrap', 'bootstrap_theme'];
    $aIndex = array_search($a, $order);
    if ($aIndex === FALSE) {
      $aIndex = 2;
    }
    $bIndex = array_search($b, $order);
    if ($bIndex === FALSE) {
      $bIndex = 2;
    }
    if ($aIndex !== $bIndex) {
      return $aIndex - $bIndex;
    }
    return strnatcasecmp($a, $b);
  }

}
