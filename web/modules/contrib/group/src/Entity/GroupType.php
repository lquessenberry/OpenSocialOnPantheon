<?php

namespace Drupal\group\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Config\Entity\Exception\ConfigEntityIdLengthException;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Group type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "group_type",
 *   label = @Translation("Group type"),
 *   label_singular = @Translation("group type"),
 *   label_plural = @Translation("group types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count group type",
 *     plural = "@count group types"
 *   ),
 *   handlers = {
 *     "access" = "Drupal\group\Entity\Access\GroupTypeAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\group\Entity\Form\GroupTypeForm",
 *       "edit" = "Drupal\group\Entity\Form\GroupTypeForm",
 *       "delete" = "Drupal\group\Entity\Form\GroupTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\group\Entity\Routing\GroupTypeRouteProvider",
 *     },
 *     "list_builder" = "Drupal\group\Entity\Controller\GroupTypeListBuilder",
 *   },
 *   admin_permission = "administer group",
 *   config_prefix = "type",
 *   bundle_of = "group",
 *   static_cache = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/group/types/add",
 *     "collection" = "/admin/group/types",
 *     "content-plugins" = "/admin/group/types/manage/{group_type}/content",
 *     "delete-form" = "/admin/group/types/manage/{group_type}/delete",
 *     "edit-form" = "/admin/group/types/manage/{group_type}",
 *     "permissions-form" = "/admin/group/types/manage/{group_type}/permissions"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "new_revision",
 *     "creator_membership",
 *     "creator_wizard",
 *     "creator_roles",
 *   }
 * )
 */
class GroupType extends ConfigEntityBundleBase implements GroupTypeInterface {

  /**
   * The machine name of the group type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the group type.
   *
   * @var string
   */
  protected $label;

  /**
   * A brief description of the group type.
   *
   * @var string
   */
  protected $description;

  /**
   * Whether a new revision should be created by default.
   *
   * @var bool
   */
  protected $new_revision = TRUE;

  /**
   * The group creator automatically receives a membership.
   *
   * @var bool
   */
  protected $creator_membership = TRUE;

  /**
   * The group creator must immediately complete their membership.
   *
   * @var bool
   */
  protected $creator_wizard = TRUE;

  /**
   * The IDs of the group roles a group creator should receive.
   *
   * @var string[]
   */
  protected $creator_roles = [];

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles($include_internal = TRUE) {
    $properties = ['group_type' => $this->id()];

    // Exclude internal roles if told to.
    if ($include_internal === FALSE) {
      $properties['internal'] = FALSE;
    }

    return $this->entityTypeManager()
      ->getStorage('group_role')
      ->loadByProperties($properties);
  }

  /**
   * {@inheritdoc}
   */
  public function getRoleIds($include_internal = TRUE) {
    $query = $this->entityTypeManager()
      ->getStorage('group_role')
      ->getQuery()
      ->condition('group_type', $this->id());

    // Exclude internal roles if told to.
    if ($include_internal === FALSE) {
      $query->condition('internal', FALSE);
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getAnonymousRole() {
    return $this->entityTypeManager()
      ->getStorage('group_role')
      ->load($this->getAnonymousRoleId());
  }

  /**
   * {@inheritdoc}
   */
  public function getAnonymousRoleId() {
    return $this->id() . '-anonymous';
  }

  /**
   * {@inheritdoc}
   */
  public function getOutsiderRole() {
    return $this->entityTypeManager()
      ->getStorage('group_role')
      ->load($this->getOutsiderRoleId());
  }

  /**
   * {@inheritdoc}
   */
  public function getOutsiderRoleId() {
    return $this->id() . '-outsider';
  }

  /**
   * {@inheritdoc}
   */
  public function getMemberRole() {
    return $this->entityTypeManager()
      ->getStorage('group_role')
      ->load($this->getMemberRoleId());
  }

  /**
   * {@inheritdoc}
   */
  public function getMemberRoleId() {
    return $this->id() . '-member';
  }

  /**
   * {@inheritdoc}
   */
  public function shouldCreateNewRevision() {
    return $this->new_revision;
  }

  /**
   * {@inheritdoc}
   */
  public function setNewRevision($new_revision) {
    $this->new_revision = $new_revision;
  }

  /**
   * {@inheritdoc}
   */
  public function creatorGetsMembership() {
    return $this->creator_membership;
  }

  /**
   * {@inheritdoc}
   */
  public function creatorMustCompleteMembership() {
    return $this->creator_membership && $this->creator_wizard;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatorRoleIds() {
    return $this->creator_roles;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    // Throw an exception if the group type ID is longer than the limit.
    if (strlen($this->id()) > GroupTypeInterface::ID_MAX_LENGTH) {
      throw new ConfigEntityIdLengthException("Attempt to create a group type with an ID longer than " . GroupTypeInterface::ID_MAX_LENGTH . " characters: {$this->id()}.");
    }

    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if (!$update) {
      // Store the id in a short variable for readability.
      $group_type_id = $this->id();

      // @todo Remove this line when https://www.drupal.org/node/2645202 lands.
      $this->setOriginalId($group_type_id);

      // The code below will create the default group roles, synchronized group
      // roles and the group content types for enforced plugins. It is extremely
      // important that we only run this code when we're not dealing with config
      // synchronization.
      //
      // Any of the config entities created here could still be queued up for
      // import in a combined config import. Therefore, we only create them in
      // \Drupal\group\EventSubscriber\ConfigSubscriber after the entire import
      // has finished.
      if (!$this->isSyncing()) {
        /** @var \Drupal\group\Entity\Storage\GroupRoleStorageInterface $group_role_storage */
        $group_role_storage = $this->entityTypeManager()->getStorage('group_role');

        // Enable enforced content plugins for the new group type.
        $this->getContentEnablerManager()->installEnforced($this);

        // Create internal and synchronized group roles for the new group type.
        $group_role_storage->createInternal([$group_type_id]);
        $group_role_storage->createSynchronized([$group_type_id]);
      }
    }
  }

  /**
   * Returns the group role synchronizer service.
   *
   * @return \Drupal\group\GroupRoleSynchronizerInterface
   *   The group role synchronizer service.
   */
  protected function getGroupRoleSynchronizer() {
    return \Drupal::service('group_role.synchronizer');
  }

  /**
   * Returns the content enabler plugin manager.
   *
   * @return \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   *   The group content plugin manager.
   */
  protected function getContentEnablerManager() {
    return \Drupal::service('plugin.manager.group_content_enabler');
  }

  /**
   * {@inheritdoc}
   */
  public function getInstalledContentPlugins() {
    return $this->getContentEnablerManager()->getInstalled($this);
  }

  /**
   * {@inheritdoc}
   */
  public function hasContentPlugin($plugin_id) {
    $installed = $this->getContentEnablerManager()->getInstalledIds($this);
    return in_array($plugin_id, $installed);
  }

  /**
   * {@inheritdoc}
   */
  public function getContentPlugin($plugin_id) {
    return $this->getInstalledContentPlugins()->get($plugin_id);
  }

}
