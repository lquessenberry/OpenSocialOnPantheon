<?php

namespace Drupal\data_policy\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\data_policy\InformBlockInterface;

/**
 * Defines the InformBlock entity.
 *
 * @ConfigEntityType(
 *   id = "informblock",
 *   label = @Translation("Inform Block"),
 *   handlers = {
 *     "access" = "Drupal\data_policy\DataPolicyInformAccessControlHandler",
 *     "list_builder" = "Drupal\data_policy\Controller\InformBlockListBuilder",
 *     "form" = {
 *       "add" = "Drupal\data_policy\Form\InformBlockForm",
 *       "edit" = "Drupal\data_policy\Form\InformBlockForm",
 *       "delete" = "Drupal\data_policy\Form\InformBlockDeleteForm",
 *     }
 *   },
 *   config_prefix = "informblock",
 *   config_export = {
 *     "id",
 *     "label",
 *     "page",
 *     "status",
 *     "summary",
 *     "body",
 *   },
 *   admin_permission = "administer inform and consent settings",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/system/inform-consent/{informblock}",
 *     "delete-form" = "/admin/config/system/inform-consent/{informblock}/delete",
 *   }
 * )
 */
class InformBlock extends ConfigEntityBase implements InformBlockInterface {

  /**
   * The ID of the Inform Block.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the Inform Block.
   *
   * @var string
   */
  public $title;

  /**
   * The link to a page where the Inform Block should be showed.
   *
   * @var string
   */
  public $page;

  /**
   * The status of the Inform Block.
   *
   * If it is set to FALSE it should not display any of the text.
   *
   * @var bool
   */
  public $status;

  /**
   * The summary to show in the block.
   *
   * @var string
   */
  public $summary;

  /**
   * The detailed description to show in the pop-up.
   *
   * @var string
   */
  public $body;

}
