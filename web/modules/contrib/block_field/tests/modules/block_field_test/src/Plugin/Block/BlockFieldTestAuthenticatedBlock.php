<?php

namespace Drupal\block_field_test\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a 'Block field test authenticated' block.
 *
 * @Block(
 *   id = "block_field_test_authenticated",
 *   admin_label = @Translation("You are logged in as..."),
 *   category = @Translation("Block field test")
 * )
 */
class BlockFieldTestAuthenticatedBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'username',
      '#account' => \Drupal::currentUser()->getAccount(),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIf($account->isAuthenticated());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['user:' . \Drupal::currentUser()->id()];
  }

}
