<?php

namespace Drupal\profile\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityDescriptionInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the profile type entity class.
 *
 * @ConfigEntityType(
 *   id = "profile_type",
 *   label = @Translation("Profile type"),
 *   handlers = {
 *     "list_builder" = "Drupal\profile\ProfileTypeListBuilder",
 *     "form" = {
 *       "default" = "Drupal\profile\Form\ProfileTypeForm",
 *       "add" = "Drupal\profile\Form\ProfileTypeForm",
 *       "edit" = "Drupal\profile\Form\ProfileTypeForm",
 *       "delete" = "Drupal\profile\Form\ProfileTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer profile types",
 *   config_prefix = "type",
 *   bundle_of = "profile",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "registration",
 *     "multiple",
 *     "roles",
 *     "weight",
 *     "status",
 *     "langcode",
 *     "use_revisions",
 *     "description"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/people/profiles/add",
 *     "delete-form" = "/admin/config/people/profiles/manage/{profile_type}/delete",
 *     "edit-form" = "/admin/config/people/profiles/manage/{profile_type}",
 *     "admin-form" = "/admin/config/people/profiles/manage/{profile_type}",
 *     "collection" = "/admin/config/people/profiles"
 *   }
 * )
 */
class ProfileType extends ConfigEntityBundleBase implements ProfileTypeInterface {

  /**
   * The primary identifier of the profile type.
   *
   * @var int
   */
  protected $id;

  /**
   * The universally unique identifier of the profile type.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The human-readable name of the profile type.
   *
   * @var string
   */
  protected $label;

  /**
   * A brief description of the profile type.
   *
   * @var string
   */
  protected $description;

  /**
   * Whether the profile type is shown during registration.
   *
   * @var bool
   */
  protected $registration = FALSE;

  /**
   * Whether the profile type allows multiple profiles.
   *
   * @var bool
   */
  protected $multiple = FALSE;

  /**
   * Which roles a user needs to have to attach profiles of this type.
   *
   * @var array
   */
  protected $roles = [];

  /**
   * The weight of the profile type compared to others.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * Should profiles of this type always generate revisions.
   *
   * @var bool
   */
  protected $use_revisions = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getRegistration() {
    return $this->registration;
  }

  /**
   * {@inheritdoc}
   */
  public function setRegistration($registration) {
    $this->registration = $registration;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple() {
    return $this->multiple;
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple($multiple) {
    $this->multiple = $multiple;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles() {
    return $this->roles;
  }

  /**
   * {@inheritdoc}
   */
  public function setRoles(array $roles) {
    $this->roles = $roles;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->weight = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldCreateNewRevision() {
    return $this->use_revisions;
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
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Rebuild module data to generate bundle permissions and link tasks.
    if (!$update) {
      system_rebuild_module_data();
    }
  }
}
