<?php

namespace Drupal\graphql\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Admin page controller that shows the list of configured GraphQL servers.
 *
 * @package Drupal\graphql\Controller
 *
 * @codeCoverageIgnore
 */
class ServerListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return [
      'label' => $this->t('Label'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    return [
      'label' => $entity->label(),
    ] + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    $id = $entity->id();

    if (\Drupal::currentUser()->hasPermission('use graphql explorer')) {
      $operations['explorer'] = [
        'title' => 'Explorer',
        'weight' => 10,
        'url' => Url::fromRoute('graphql.explorer', ['graphql_server' => $id]),
      ];
    }

    if (\Drupal::currentUser()->hasPermission('use graphql voyager')) {
      $operations['voyager'] = [
        'title' => 'Voyager',
        'weight' => 10,
        'url' => Url::fromRoute('graphql.voyager', ['graphql_server' => $id]),
      ];
    }

    if (\Drupal::currentUser()->hasPermission("administer graphql configuration")) {
      $operations['persisted_queries'] = [
        'title' => 'Persisted queries',
        'weight' => 10,
        'url' => Url::fromRoute('entity.graphql_server.persisted_queries_form', ['graphql_server' => $id]),
      ];
    }

    return $operations;
  }

}
