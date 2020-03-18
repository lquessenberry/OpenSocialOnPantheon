<?php

/**
 * @file
 * Contains \Drupal\votingapi_test\Plugin\VoteResultFunction\Zebra.
 */

namespace Drupal\votingapi_test\Plugin\VoteResultFunction;

use Drupal\votingapi\VoteResultFunctionBase;

/**
 * @VoteResult(
 *   id = "zebra",
 *   label = @Translation("Zebra"),
 *   description = @Translation("A vote test plugin.")
 * )
 */
class Zebra extends VoteResultFunctionBase {

  /**
   * {@inheritdoc}
   */
  public function calculateResult($votes) {
    return 10101;
  }
}
