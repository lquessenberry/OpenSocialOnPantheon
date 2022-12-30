<?php

namespace Drupal\data_policy\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\data_policy\InformBlockInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'Data Policy' block.
 *
 * @Block(
 *   id = "data_policy_inform_block",
 *   admin_label = @Translation("Data Policy Inform"),
 *   category = @Translation("Data Policy")
 * )
 */
class DataPolicyInformBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * An alias manager to find the alias for the current system path.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DataPolicyInformBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   An alias manager to find the alias for the current system path.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, CurrentPathStack $current_path, AliasManagerInterface $alias_manager, PathMatcherInterface $path_matcher, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->requestStack = $request_stack;
    $this->currentPath = $current_path;
    $this->aliasManager = $alias_manager;
    $this->pathMatcher = $path_matcher;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('path.current'),
      $container->get('path_alias.manager'),
      $container->get('path.matcher'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $configuration = parent::getConfiguration();

    if (!empty($inform_block = $this->getInformBlock())) {
      $configuration['label'] = $inform_block->label();
    }

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if ($this->getInformBlock() instanceof InformBlockInterface) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $inform_block = $this->getInformBlock();

    $build['text'] = ['#markup' => $inform_block->summary['value']];

    if (!empty($inform_block->body['value'])) {
      $build['#attached']['library'][] = 'core/drupal.dialog.ajax';

      $build['link'] = [
        '#type' => 'link',
        '#title' => $this->t('Read more'),
        '#url' => Url::fromRoute('data_policy.description', [
          'informblock' => $inform_block->id(),
        ]),
        '#attributes' => [
          'class' => ['use-ajax btn btn-flat'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'title' => $inform_block->label(),
            'width' => 700,
          ]),
        ],
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['label']['#disabled'] = $form['label_display']['#disabled'] = TRUE;

    return $form;
  }

  /**
   * Get entity of inform_block for current page.
   *
   * @return \Drupal\data_policy\InformBlockInterface|null
   *   If current page has data for information block then will be returned
   *   entity else NULL.
   */
  private function getInformBlock() {
    $inform_blocks = $this->entityTypeManager->getStorage('informblock')
      ->loadByProperties(['status' => TRUE]);

    foreach ($inform_blocks as $inform_block) {
      $link = $inform_block->page;
      $request = $this->requestStack->getCurrentRequest();
      $path = $this->currentPath->getPath($request);
      $path = $path === '/' ? $path : rtrim($path, '/');
      $path_alias = mb_strtolower($this->aliasManager->getAliasByPath($path));

      if ($this->pathMatcher->matchPath($path_alias, $link) || (($path != $path_alias) && $this->pathMatcher->matchPath($path, $link))) {
        return $inform_block;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeTags(parent::getCacheTags(), ['route']);
  }

}
