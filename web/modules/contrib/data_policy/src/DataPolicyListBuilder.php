<?php

namespace Drupal\data_policy;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Data policy entities.
 *
 * @ingroup data_policy
 */
class DataPolicyListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['id'] = $this->t('Data policy ID');
    $header['revisions'] = $this->t('Revisions');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\data_policy\Entity\DataPolicyInterface $entity */
    $row['name'] = Link::createFromRoute($entity->label(), 'entity.data_policy.revision', [
      'entity_id' => $entity->id(),
      'data_policy_revision' => $entity->getRevisionId(),
    ]);

    $row['id'] = $entity->id();
    $row['revisions'] = count($this->storage->revisionIds($entity));

    return $row + parent::buildRow($entity);
  }

}
