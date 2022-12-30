<?php

namespace Drupal\ginvite\Plugin\GroupContentEnabler;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\GroupContentEnablerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\Access\GroupAccessResult;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a content enabler for invitations.
 *
 * @GroupContentEnabler(
 *   id = "group_invitation",
 *   label = @Translation("Group Invitation"),
 *   description = @Translation("Creates invitations to group."),
 *   entity_type_id = "user",
 *   pretty_path_key = "invitee",
 *   reference_label = @Translation("Invitee"),
 *   reference_description = @Translation("Invited user."),
 *   handlers = {
 *     "permission_provider" = "Drupal\ginvite\Plugin\GroupInvitationPermissionProvider",
 *   },
 * )
 */
class GroupInvitation extends GroupContentEnablerBase implements ContainerFactoryPluginInterface {

  /**
   * Invitation created and waiting for user's response.
   */
  const INVITATION_PENDING = 0;

  /**
   * Invitation accepted by user.
   */
  const INVITATION_ACCEPTED = 1;

  /**
   * Invitation rejected by user.
   */
  const INVITATION_REJECTED = 2;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The current user.
   *
   * @var \Drupal\Core\ProxyClass\Config\ConfigInstaller
   */
  protected $configInstaller;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $static = new static($configuration, $plugin_id, $plugin_definition);

    $static->currentUser = $container->get('current_user');
    $static->configInstaller = $container->get('config.installer');
    $static->entityTypeManager = $container->get('entity_type.manager');

    return $static;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupOperations(GroupInterface $group) {
    $operations = [];

    if ($group->hasPermission('invite users to group', $this->currentUser)) {
      $operations['invite-user'] = [
        'title' => $this->t('Invite user'),
        'url' => new Url('entity.group_content.add_form', [
          'group' => $group->id(),
          'plugin_id' => 'group_invitation',
        ]),
        'weight' => 0,
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function createAccess(GroupInterface $group, AccountInterface $account) {
    return GroupAccessResult::allowedIfHasGroupPermission($group, $account, 'invite users to group');
  }

  /**
   * {@inheritdoc}
   */
  protected function viewAccess(GroupContentInterface $group_content, AccountInterface $account) {
    $group = $group_content->getGroup();
    return GroupAccessResult::allowedIfHasGroupPermission($group, $account, 'view group invitations');
  }

  /**
   * {@inheritdoc}
   */
  protected function updateAccess(GroupContentInterface $group_content, AccountInterface $account) {
    // Close access to edit group invitations.
    // It will not be supported for now.
    return GroupAccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  protected function deleteAccess(GroupContentInterface $group_content, AccountInterface $account) {
    $group = $group_content->getGroup();

    // Allow members to delete their own group content.
    if ($group_content->getOwnerId() == $account->id()) {
      return GroupAccessResult::allowedIfHasGroupPermission($group, $account, 'delete own invitations');
    }

    return GroupAccessResult::allowedIfHasGroupPermission($group, $account, 'delete any invitation');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $body_message = 'Hi there!' . "\n\n";
    $body_message .= '[current-user:name] has invited you to become a member of the group [group:title] on [site:name].' . "\n";
    $body_message .= 'If you wish to accept the invitation, you need to create an account first.' . "\n\n";
    $body_message .= 'Please visit the following address in order to do so: [group_content:register_link]' . "\n";
    $body_message .= 'Kind regards,' . "\n";
    $body_message .= 'The [site:name] team';

    $body_message_existing_user = 'Hi there!' . "\n\n";
    $body_message_existing_user .= '[current-user:name] has invited you to become a member of the group [group:title] on [site:name].' . "\n";
    $body_message_existing_user .= 'If you wish to accept the invitation, go to My invitations tab in user profile.' . "\n\n";
    $body_message_existing_user .= 'Please visit the following address in order to do so: [group_content:my_invitations_link]' . "\n";
    $body_message_existing_user .= 'Kind regards,' . "\n";
    $body_message_existing_user .= 'The [site:name] team';

    $body_message_cancel = 'Hi there!' . "\n\n";
    $body_message_cancel .= 'Your invitation to the group [group:title] on [site:name] has been cancelled' . "\n";
    $body_message_cancel .= 'Kind regards,' . "\n";
    $body_message_cancel .= 'The [site:name] team';

    return [
      'group_cardinality' => 0,
      'entity_cardinality' => 0,
      'use_creation_wizard' => 0,
      'unblock_invitees' => 1,
      'invitation_subject' => 'You have a pending group invitation',
      'invitation_body' => $body_message,
      'existing_user_invitation_subject' => 'You have a pending group invitation',
      'existing_user_invitation_body' => $body_message_existing_user,
      'send_email_existing_users' => 0,
      'cancel_user_invitation_subject' => 'Your invitation is no longer available',
      'cancel_user_invitation_body' => $body_message_cancel,
      'send_cancel_email' => FALSE,
      'invitation_bypass_form' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function postInstall() {
    if (!$this->configInstaller->isSyncing()) {
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

      // Add email field.
      FieldConfig::create([
        'field_storage' => FieldStorageConfig::loadByName('group_content', 'invitee_mail'),
        'bundle' => $group_content_type_id,
        'label' => $this->t('Invitee mail'),
        'required' => TRUE,
      ])->save();

      // Add Status field.
      FieldConfig::create([
        'field_storage' => FieldStorageConfig::loadByName('group_content', 'invitation_status'),
        'bundle' => $group_content_type_id,
        'label' => $this->t('Invitation status'),
        'required' => TRUE,
        'default_value' => [
          [
            'value' => self::INVITATION_PENDING,
          ],
        ],
      ])->save();

      // Build the 'default' display ID for both the entity form and view mode.
      $default_display_id = "group_content.$group_content_type_id.default";

      $entity_form_display_storage = $this->entityTypeManager->getStorage('entity_form_display');

      // Build or retrieve the 'default' form mode.
      if (!$form_display = $entity_form_display_storage->load($default_display_id)) {
        $form_display = $entity_form_display_storage->create([
          'targetEntityType' => 'group_content',
          'bundle' => $group_content_type_id,
          'mode' => 'default',
          'status' => TRUE,
        ]);
      }

      $entity_view_display_storage = $this->entityTypeManager->getStorage('entity_view_display');
      // Build or retrieve the 'default' view mode.
      if (!$view_display = $entity_view_display_storage->load($default_display_id)) {
        $view_display = $entity_view_display_storage->create([
          'targetEntityType' => 'group_content',
          'bundle' => $group_content_type_id,
          'mode' => 'default',
          'status' => TRUE,
        ]);
      }

      // Assign widget settings for the 'default' form mode.
      $form_display
        ->setComponent('group_roles', [
          'type' => 'options_buttons',
        ])
        ->setComponent('invitee_mail', [
          'type' => 'email_default',
          'weight' => -1,
          'settings' => [
            'placeholder' => 'example@example.com',
          ],
        ])
        ->removeComponent('entity_id')
        ->removeComponent('path')
        ->save();

      // Assign display settings for the 'default' view mode.
      $view_display
        ->setComponent('group_roles', [
          'label' => 'above',
          'type' => 'entity_reference_label',
          'settings' => [
            'link' => 0,
          ],
        ])
        ->setComponent('invitee_mail', [
          'type' => 'email_mailto',
        ])
        ->setComponent('invitation_status', [
          'type' => 'number_integer',
        ])
        ->save();
    }
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

    $form['group_cardinality']['#disabled'] = TRUE;
    $form['group_cardinality']['#description'] .= '<br /><em>' . $info . '</em>';

    $form['unblock_invitees'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Unblock registered users coming from an invitation'),
      '#default_value' => $this->getConfiguration()['unblock_invitees'],
      '#disabled' => !$this->currentUser->hasPermission('administer account settings'),
    ];

    $form['invitation_bypass_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Accept invitations immediately'),
      '#description' => $this->t('When accepting an invitation, the group membership entity form will be rendered. Enabling this option will bypass this step and the membership will be generated immediately. This is especially useful if your membership entity provides no configuration at all and the invitation accept route renders an empty form with a single submit button.'),
      '#default_value' => $this->getConfiguration()['invitation_bypass_form'],
    ];

    $form['invitation_expire'] = [
      '#type' => 'number',
      '#title' => $this->t('Expire invites'),
      '#default_value' => !empty($this->getConfiguration()['invitation_expire']) ? $this->getConfiguration()['invitation_expire'] : '',
      '#description' => $this->t('Automatically remove open invites after the specified days. If left empty invites will not expire.'),
    ];

    // Invitation Email Configuration.
    $form['invitation_email_config'] = [
      '#type' => 'vertical_tabs',
    ];

    $form['invitation_email'] = [
      '#type' => 'details',
      '#title' => $this->t('Invitation e-mail'),
      '#group' => 'invitation_email_config',
      '#open' => TRUE,
    ];
    $form['invitation_email']['invitation_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $this->getConfiguration()['invitation_subject'],
      '#maxlength' => 180,
    ];
    $form['invitation_email']['invitation_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#default_value' => $this->getConfiguration()['invitation_body'],
      '#rows' => 15,
    ];

    $form['existing_user_invitation_email'] = [
      '#type' => 'details',
      '#title' => $this->t('Invitation e-mail for registered users'),
      '#group' => 'invitation_email_config',
    ];
    $form['existing_user_invitation_email']['existing_user_invitation_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $this->getConfiguration()['existing_user_invitation_subject'],
      '#maxlength' => 180,
    ];
    $form['existing_user_invitation_email']['existing_user_invitation_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#default_value' => $this->getConfiguration()['existing_user_invitation_body'],
      '#rows' => 15,
    ];
    $form['existing_user_invitation_email']['send_email_existing_users'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send invitation e-mail to already registered users'),
      '#default_value' => $this->getConfiguration()['send_email_existing_users'],
    ];

    $form['cancel_user_invitation_email'] = [
      '#type' => 'details',
      '#title' => $this->t('Invitation cancelled notification'),
      '#group' => 'invitation_email_config',
    ];
    $form['cancel_user_invitation_email']['cancel_user_invitation_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $this->getConfiguration()['cancel_user_invitation_subject'],
      '#maxlength' => 180,
    ];
    $form['cancel_user_invitation_email']['cancel_user_invitation_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#default_value' => $this->getConfiguration()['cancel_user_invitation_body'],
      '#rows' => 15,
    ];
    $form['cancel_user_invitation_email']['send_cancel_email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send notification when an invitation is cancelled'),
      '#default_value' => $this->getConfiguration()['send_cancel_email'],
    ];

    $form['token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['group', 'user', 'group_content'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['unblock_invitees'] = $this->currentUser->hasPermission('administer account settings') ? $form_state->getValue('unblock_invitees') : 0;
    $this->configuration['invitation_expire'] = $form_state->getValue('invitation_expire');
    $this->configuration['invitation_subject'] = $form_state->getValue('invitation_subject');
    $this->configuration['invitation_body'] = $form_state->getValue('invitation_body');
    $this->configuration['existing_user_invitation_subject'] = $form_state->getValue('existing_user_invitation_subject');
    $this->configuration['existing_user_invitation_body'] = $form_state->getValue('existing_user_invitation_body');
    $this->configuration['send_email_existing_users'] = $form_state->getValue('send_email_existing_users');
    $this->configuration['invitation_bypass_form'] = $form_state->getValue('invitation_bypass_form');
    $this->configuration['cancel_user_invitation_subject'] = $form_state->getValue('cancel_user_invitation_subject');
    $this->configuration['cancel_user_invitation_body'] = $form_state->getValue('cancel_user_invitation_body');
    $this->configuration['send_cancel_email'] = $form_state->getValue('send_cancel_email');
  }

}
