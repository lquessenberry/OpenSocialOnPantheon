<?php

namespace Drupal\group\Plugin\GroupContentEnabler;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\group\Access\GroupAccessResult;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\Plugin\GroupContentEnablerBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Url;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a content enabler for users.
 *
 * @GroupContentEnabler(
 *   id = "group_membership",
 *   label = @Translation("Group membership"),
 *   description = @Translation("Adds users to groups as members."),
 *   entity_type_id = "user",
 *   pretty_path_key = "member",
 *   reference_label = @Translation("User"),
 *   reference_description = @Translation("The user you want to make a member"),
 *   enforced = TRUE,
 *   handlers = {
 *     "access" = "Drupal\group\Plugin\GroupContentAccessControlHandler",
 *     "permission_provider" = "Drupal\group\Plugin\GroupMembershipPermissionProvider",
 *   },
 *   admin_permission = "administer members"
 * )
 */
class GroupMembership extends GroupContentEnablerBase {

  /**
   * {@inheritdoc}
   */
  public function getGroupOperations(GroupInterface $group) {
    $account = \Drupal::currentUser();
    $operations = [];

    if ($group->getMember($account)) {
      if ($group->hasPermission('leave group', $account)) {
        $operations['group-leave'] = [
          'title' => $this->t('Leave group'),
          'url' => new Url('entity.group.leave', ['group' => $group->id()]),
          'weight' => 99,
        ];
      }
    }
    elseif ($group->hasPermission('join group', $account)) {
      $operations['group-join'] = [
        'title' => $this->t('Join group'),
        'url' => new Url('entity.group.join', ['group' => $group->id()]),
        'weight' => 0,
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupOperationsCacheableMetadata() {
    // We cannot use the user.is_group_member:%group_id cache context for the
    // join and leave operations, because they end up in the group operations
    // block, which is shown for most likely every group in the system. Instead,
    // we cache per user, meaning the block will be auto-placeholdered in most
    // set-ups.
    // @todo With the new VariationCache, we can use the above context.
    $cacheable_metadata = new CacheableMetadata();
    $cacheable_metadata->setCacheContexts(['user']);
    return $cacheable_metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityReferenceSettings() {
    $settings = parent::getEntityReferenceSettings();
    $settings['handler_settings']['include_anonymous'] = FALSE;
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function postInstall() {
    // Only create config objects while config import is not in progress.
    if (!\Drupal::isConfigSyncing()) {
      $group_content_type_id = $this->getContentTypeConfigId();

      // Add the group_roles field to the newly added group content type. The
      // field storage for this is defined in the config/install folder. The
      // default handler for 'group_role' target entities in the 'group_type'
      // handler group is GroupTypeRoleSelection.
      FieldConfig::create([
        'field_storage' => FieldStorageConfig::loadByName('group_content', 'group_roles'),
        'bundle' => $group_content_type_id,
        'label' => $this->t('Roles'),
        'settings' => [
          'handler' => 'group_type:group_role',
          'handler_settings' => [
            'group_type_id' => $this->getGroupTypeId(),
          ],
        ],
      ])->save();

      // Build the 'default' display ID for both the entity form and view mode.
      $default_display_id = "group_content.$group_content_type_id.default";

      // Build or retrieve the 'default' form mode.
      if (!$form_display = EntityFormDisplay::load($default_display_id)) {
        $form_display = EntityFormDisplay::create([
          'targetEntityType' => 'group_content',
          'bundle' => $group_content_type_id,
          'mode' => 'default',
          'status' => TRUE,
        ]);
      }

      // Build or retrieve the 'default' view mode.
      if (!$view_display = EntityViewDisplay::load($default_display_id)) {
        $view_display = EntityViewDisplay::create([
          'targetEntityType' => 'group_content',
          'bundle' => $group_content_type_id,
          'mode' => 'default',
          'status' => TRUE,
        ]);
      }

      // Assign widget settings for the 'default' form mode.
      $form_display->setComponent('group_roles', [
        'type' => 'options_buttons',
      ])->save();

      // Assign display settings for the 'default' view mode.
      $view_display->setComponent('group_roles', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'settings' => [
          'link' => 0,
        ],
      ])->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config['entity_cardinality'] = 1;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Disable the entity cardinality field as the functionality of this module
    // relies on a cardinality of 1. We don't just hide it, though, to keep a UI
    // that's consistent with other content enabler plugins.
    $info = $this->t("This field has been disabled by the plugin to guarantee the functionality that's expected of it.");
    $form['entity_cardinality']['#disabled'] = TRUE;
    $form['entity_cardinality']['#description'] .= '<br /><em>' . $info . '</em>';

    return $form;
  }

}
