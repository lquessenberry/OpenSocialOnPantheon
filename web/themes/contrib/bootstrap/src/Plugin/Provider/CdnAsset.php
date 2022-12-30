<?php

namespace Drupal\bootstrap\Plugin\Provider;

use Drupal\bootstrap\Bootstrap;
use Drupal\bootstrap\Utility\Crypt;
use Drupal\bootstrap\Utility\Unicode;
use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\ToStringTrait;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class CdnAsset.
 */
class CdnAsset {

  use StringTranslationTrait;
  use DependencySerializationTrait;
  use ToStringTrait;

  /**
   * Invalid asset regular expression.
   *
   * @var string
   */
  const INVALID_FILE_REGEXP = '`^/2|/bower_components`';

  /**
   * Valid asset regular expression.
   *
   * @var string
   */
  const VALID_FILE_REGEXP = '`([^/]*)/(?:[\w-]+)?bootstrap(?:-([\w]+))?(\.min)?\.(js|css)$`';

  /**
   * A list of available Bootswatch themes, keyed by major Bootstrap version.
   *
   * @var array
   */
  protected static $bootswatchThemes = [
    3 => [
      'cerulean',
      'cosmo',
      'cyborg',
      'darkly',
      'flatly',
      'journal',
      'lumen',
      'paper',
      'readable',
      'sandstone',
      'simplex',
      'slate',
      'spacelab',
      'superhero',
      'united',
      'yeti',
    ],
    4 => [
      'cerulean',
      'cosmo',
      'cyborg',
      'darkly',
      'flatly',
      'journal',
      'litera',
      'lumen',
      'lux',
      'materia',
      'minty',
      'pulse',
      'sandstone',
      'simplex',
      'sketchy',
      'slate',
      'solar',
      'spacelab',
      'superhero',
      'united',
      'yeti',
    ],
  ];

  /**
   * A unique identifier.
   *
   * @var string
   */
  protected $id;

  /**
   * Additional information supplied from the CDN API.
   *
   * @var array
   */
  protected $info;

  /**
   * A human readable label for the CDN Asset.
   *
   * @var \Drupal\Component\Render\MarkupInterface
   */
  protected $label;

  /**
   * The library this URL references.
   *
   * @var string
   */
  protected $library;

  /**
   * Flag indicating whether the URL is minified.
   *
   * @var bool
   */
  protected $minified;

  /**
   * The theme this URL references.
   *
   * @var string
   */
  protected $theme;

  /**
   * The type of resource, e.g. css or js.
   *
   * @var string
   */
  protected $type;

  /**
   * The URL.
   *
   * @var string
   */
  protected $url;

  /**
   * The version this URL references.
   *
   * @var string
   */
  protected $version;

  /**
   * CdnAsset constructor.
   *
   * @param string $url
   *   The absolute URL to to the CDN asset.
   * @param string $library
   *   The specific library this asset is associated with, if known.
   * @param string $version
   *   The specific version this asset is associated with, if known.
   * @param array $info
   *   Additional information provided by the CDN.
   */
  public function __construct($url, $library = NULL, $version = NULL, array $info = []) {
    // Extract the necessary data from the file.
    list($path, $theme, $minified, $type) = static::extractParts($url);

    // @todo Remove once PHP 5.5 is no longer supported (use array access).
    $major = substr(Bootstrap::FRAMEWORK_VERSION, 0, 1);

    // Bootstrap's example theme.
    if ($theme === 'theme') {
      $theme = 'bootstrap_theme';
      $label = $this->t('Example Theme');
      if (!isset($library)) {
        $library = 'bootstrap';
      }
    }
    // Core bootstrap library.
    elseif (!$theme && ($path === 'css' || $path === 'js' || $path === Bootstrap::PROJECT_BRANCH)) {
      $theme = 'bootstrap';
      $label = $this->t('Default');
      if (!isset($library)) {
        $library = 'bootstrap';
      }
    }
    // Other (e.g. bootswatch theme).
    else {
      $bootswatchThemes = isset(static::$bootswatchThemes[$major]) ? static::$bootswatchThemes[$major] : [];
      if (!$theme || ($theme && !in_array($theme, $bootswatchThemes))) {
        $theme = in_array($path, $bootswatchThemes) ? $path : 'bootstrap';
      }
      $label = new HtmlEscapedText(ucfirst($theme));
      if (!isset($library)) {
        $library = in_array($theme, $bootswatchThemes) ? 'bootswatch' : 'unknown';
      }
    }

    // If no version was provided, attempt to extract it.
    // @todo Move regular expression to a constant once PHP 5.5 is no longer
    // supported.
    if (!isset($version) && preg_match('`(' . $major . '\.\d+\.\d+)`', $url, $matches)) {
      $version = $matches[1];
    }

    $this->id = Crypt::generateBase64HashIdentifier([
      'url' => $url,
      'info' => $info,
    ], [$library, $version, $theme, basename($url)]);
    $this->info = $info;
    $this->label = $label;
    $this->library = $library;
    $this->minified = $minified;
    $this->theme = $theme;
    $this->type = $type;
    $this->url = $url;
    $this->version = $version;
  }

  /**
   * Extracts the necessary parts of the URL.
   *
   * @param string $url
   *   The URL to parse.
   *
   * @return array
   */
  protected static function extractParts($url) {
    preg_match(static::VALID_FILE_REGEXP, $url, $matches);
    $path = isset($matches[1]) ? mb_strtolower(Html::escape($matches[1])) : NULL;
    $theme = isset($matches[2]) ? mb_strtolower(Html::escape($matches[2])) : NULL;
    $minified = isset($matches[3]) ? !!$matches[3] : FALSE;
    $type = isset($matches[4]) ? mb_strtolower(Html::escape($matches[4])) : NULL;
    return [$path, $theme, $minified, $type];
  }

  /**
   * Indicates whether the provided URL is valid.
   *
   * @param string $url
   *   The URL to check.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public static function isFileValid($url) {
    if (preg_match(static::INVALID_FILE_REGEXP, $url)) {
      return FALSE;
    }
    list($path, $example, $minified, $type) = static::extractParts($url);
    return $path && $type;
  }

  /**
   * Retrieves the unique identifier for this asset.
   *
   * @return string
   *   The unique identifier.
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Retrieves information provided by the CDN API, if available.
   *
   * @param string $key
   *   Optional. A specific key of the item to retrieve. Note: this can be in
   *   the form of dot notation if the value is nested in an array. If not
   *   provided, the entire contents of the info will be returned.
   * @param mixed $default
   *   The default value to use if $key was provided and does not exist.
   *
   * @return mixed
   *   The specified information or the entire contents of the array if $key
   *   was not provided.
   */
  public function getInfo($key = NULL, $default = NULL) {
    $info = $this->info ?: [];
    if (isset($key)) {
      $parts = Unicode::splitDelimiter($key);
      $value = NestedArray::getValue($info, $parts, $exists);
      return $exists ? $value : $default;
    }
    return $info;
  }

  /**
   * Retrieves the human readable label.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The label.
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Retrieves the library this CDN asset is associated with, if any.
   *
   * @return string
   *   The library.
   */
  public function getLibrary() {
    return $this->library;
  }

  /**
   * Retrieves the theme this CDN asset is associated with, if any.
   *
   * @return string
   *   The theme.
   */
  public function getTheme() {
    return $this->theme;
  }

  /**
   * Retrieves the type of CDN asset this is (e.g. css or js).
   *
   * @return string
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Retrieves the absolute URL this CDN asset represents.
   *
   * @return string
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * Retrieves the version this CDN asset is associated with, if any.
   *
   * @return string
   */
  public function getVersion() {
    return $this->version;
  }

  /**
   * Indicates whether the CDN asset is minified.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isMinified() {
    return $this->minified;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return $this->getUrl();
  }

}
