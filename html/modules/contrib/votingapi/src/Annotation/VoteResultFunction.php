<?php

namespace Drupal\votingapi\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a voting result annotation object.
 *
 * Plugin Namespace: Plugin\votingapi\VoteResultFunction
 *
 * For a working example, see
 * \Drupal\votingapi\Plugin\VoteResultFunction\Sum
 *
 * @see hook_vote_result_info_alter()
 * @see \Drupal\votingapi\VoteResultFunctionInterface
 * @see \Drupal\votingapi\VoteResultFunctionBase
 * @see \Drupal\votingapi\VoteResultFunctionManager
 * @see plugin_api
 *
 * @Annotation
 */
class VoteResultFunction extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the voting result.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * A brief description of the voting result.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation (optional)
   */
  public $description = '';

}
