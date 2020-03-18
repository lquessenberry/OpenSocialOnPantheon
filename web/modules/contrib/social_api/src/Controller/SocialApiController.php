<?php

namespace Drupal\social_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\social_api\Plugin\NetworkManager;

/**
 * Renders integrations of social api.
 */
class SocialApiController extends ControllerBase {
  /**
   * The network manager.
   *
   * @var NetworkManager
   */
  private $networkManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.network.manager'));
  }

  /**
   * SocialApiController constructor.
   *
   * @param NetworkManager $networkManager
   *   The network manager.
   */
  public function __construct(NetworkManager $networkManager) {
    $this->networkManager = $networkManager;
  }

  /**
   * Render the list of plugins for a social network.
   *
   * @param string $type
   *   Integration type: social_auth, social_post, or social_widgets.
   *
   * @return array
   *   Render array listing the integrations.
   */
  public function integrations($type) {
    $networks = $this->networkManager->getDefinitions();
    $header = [
      $this->t('Module'),
      $this->t('Social Network'),
    ];
    $data = [];
    foreach ($networks as $network) {
      if ($network['type'] == $type) {
        $data[] = [
          $network['id'],
          $network['social_network'],
        ];
      }
    }
    return [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $data,
      '#empty' => $this->t('There are no social integrations enabled.'),
    ];
  }

}
