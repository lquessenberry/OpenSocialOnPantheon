<?php

namespace Drupal\lazy\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\lazy\Lazy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to lazy-load images.
 *
 * @Filter(
 *   id = "lazy_filter",
 *   title = @Translation("Lazy-load images and iframes"),
 *   description = @Translation("Only selected tags will be lazy-loaded in activated text-formats."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   settings = {
 *     "image" = TRUE,
 *     "iframe" = TRUE,
 *   },
 *   weight = 20
 * )
 */
class LazyFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Lazy-load service.
   *
   * @var \Drupal\lazy\Lazy
   */
  protected $lazyLoad;

  /**
   * Constructs a LazyFilter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\lazy\Lazy $lazy_load
   *   The Lazy-load service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, Lazy $lazy_load) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->lazyLoad = $lazy_load;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('lazy')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['info'] = [
      '#markup' => $this->t('Lazy-load filter can be enabled for images and iframes.'),
    ];
    $form['image'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable for images (%img tags)', ['%img' => '<img>']),
      "#description" => $this->t('This option only applies to inline-images. If <em>Embed media</em> filter is enabled, the images embedded from media library would use the the selected view mode settings.'),
      '#default_value' => $this->settings['image'],
      '#return_value' => TRUE,
    ];
    $form['iframe'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable for iframes (%iframe tags)', ['%iframe' => '<iframe>']),
      '#default_value' => $this->settings['iframe'],
      '#return_value' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);

    if (
      $configuration['status']
      && !empty($configuration['settings'])
      && $configuration['settings']['image'] == FALSE
      && $configuration['settings']['iframe'] == FALSE
    ) {
      $this->status = FALSE;
      $this->messenger()->addWarning($this->t('Lazy-loading is not enabled. The filter configuration needs to be enabled for either of the IMG or IFRAME tags.'));
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    $result = new FilterProcessResult($text);

    if (
      $this->status
      && ($this->settings['image'] || $this->settings['iframe'])
      && $this->lazyLoad->isPathAllowed()
      && $lazy_settings = $this->configFactory->get('lazy.settings')->get()
    ) {
      $html_dom = Html::load($text);
      $xpath = new \DOMXPath($html_dom);

      /** @var \DOMElement $node */
      foreach ($xpath->query('//img | //iframe') as $node) {
        $classes = empty($node->getAttribute('class')) ?
          [] : explode(' ', $node->getAttribute('class'));
        $parent_classes = empty($node->parentNode->getAttribute('class')) ?
          [] : explode(' ', $node->parentNode->getAttribute('class'));

        // Get original source value.
        $src = $node->getAttribute('src');

        // Check which tags are enabled in text-format settings.
        $enabled_tags = [
          'img' => $this->settings['image'],
          'iframe' => $this->settings['iframe'],
        ];
        foreach ($enabled_tags as $tag => $status) {
          // Act only on the elements that are enabled under "Lazy-load images
          // and iframes" in filter settings.
          if ($node->tagName === $tag && $enabled_tags[$node->tagName]) {
            // Check if the element, or its parent has a skip class.
            if (in_array($lazy_settings['skipClass'], $classes, TRUE) || in_array($lazy_settings['skipClass'], $parent_classes, TRUE)) {
              // Leave this node unchanged.
              continue;
            }

            if ($lazy_settings['preferNative']) {
              // Set required attribute `loading="lazy"`.
              $node->setAttribute('loading', 'lazy');
            }
            else {
              // Add Lazysizes selector class name to element attributes.
              $classes[] = $lazy_settings['lazysizes']['lazyClass'];
              $classes = array_unique($classes);
              $node->setAttribute('class', implode(' ', $classes));

              // Change source attribute from `src` to `data-src`, or whatever
              // is defined in Lazysizes configuration for `srcAttr` at
              // /admin/config/content/lazy.
              $opt_src = ($lazy_settings['lazysizes']['srcAttr'] !== 'src') ? $lazy_settings['lazysizes']['srcAttr'] : 'data-filterlazy-src';
              $node->removeAttribute('src');
              $node->setAttribute($opt_src, $src);

              // If the default placeholder defined, it would be used in `src`
              // attribute.
              if ($lazy_settings['placeholderSrc']) {
                $node->setAttribute('src', $lazy_settings['placeholderSrc']);
              }
            }
          }
        }
      }

      $result->setProcessedText(Html::serialize($html_dom));
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    $tags = [
      'img' => $this->settings['image'],
      'iframe' => $this->settings['iframe'],
    ];
    $options = [
      '%img' => '<img>',
      '%iframe' => '<iframe>',
    ];
    $skip_class = $this->configFactory->get('lazy.settings')->get('skipClass');
    $skip_help = $this->t('If you want certain elements skip lazy-loading, add <code>%skip_class</code> class name.', ['%skip_class' => $skip_class]);

    if (!empty($tags)) {
      if ($tags['img'] && $tags['iframe']) {
        return $this->t('Lazy-loading is enabled for both %img and %iframe tags.', $options) . ' ' . $skip_help;
      }

      if ($tags['img']) {
        return $this->t('Lazy-loading is enabled for %img tags.', $options) . ' ' . $skip_help;
      }

      if ($tags['iframe']) {
        return $this->t('Lazy-loading is enabled for %iframe tags.', $options) . ' ' . $skip_help;
      }
    }

    return $this->t('Lazy-loading is not enabled.');
  }

}
