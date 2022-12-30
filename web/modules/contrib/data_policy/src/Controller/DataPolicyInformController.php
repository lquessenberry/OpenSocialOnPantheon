<?php

namespace Drupal\data_policy\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\data_policy\Entity\InformBlock;
use Drupal\data_policy\InformBlockInterface;

/**
 * Controller class for data_policy.
 *
 * @package Drupal\data_policy\Controller
 */
class DataPolicyInformController extends ControllerBase {

  /**
   * Show description of information block for the current page.
   *
   * @param string $informblock
   *   The 'informblock' entity ID.
   *
   * @return array
   *   The 'body' field value.
   */
  public function descriptionPage($informblock) {
    return ['#markup' => InformBlock::load($informblock)->body['value']];
  }

  /**
   * Title of an information block.
   *
   * @param string $informblock
   *   The 'informblock' entity ID.
   *
   * @return string
   *   The label of entity.
   */
  public function title($informblock) {
    return InformBlock::load($informblock)->label();
  }

  /**
   * Check if exist entity.
   *
   * @param string $informblock
   *   The 'informblock' entity ID.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access($informblock) {
    if (InformBlock::load($informblock) instanceof InformBlockInterface) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
