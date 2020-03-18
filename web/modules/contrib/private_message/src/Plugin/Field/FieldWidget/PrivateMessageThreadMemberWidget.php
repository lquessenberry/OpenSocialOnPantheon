<?php

namespace Drupal\private_message\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the private message thread member widget.
 *
 * @FieldWidget(
 *   id = "private_message_thread_member_widget",
 *   label = @Translation("Private message members autocomplete"),
 *   field_types = {
 *     "entity_reference"
 *   },
 * )
 */
class PrivateMessageThreadMemberWidget extends EntityReferenceAutocompleteWidget implements ContainerFactoryPluginInterface {

  /**
   * The CSRF token generator service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfTokenGenerator;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a PrivateMessageThreadMemberWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfTokenGenerator
   *   The CSRF token generator service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    CsrfTokenGenerator $csrfTokenGenerator,
    AccountProxyInterface $currentUser,
    RequestStack $requestStack
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->csrfTokenGenerator = $csrfTokenGenerator;
    $this->currentUser = $currentUser;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('csrf_token'),
      $container->get('current_user'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getTargetEntityTypeId() == 'private_message_thread' && $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'user';
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'max_members' => 0,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   *
   * The settings summary is returned empty, as the parent settings have no
   * effect on this form.
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    unset($summary[0]);

    $summary[] = $this->t('Maximum thread members: @count', ['@count' => $this->getSetting('max_members')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    // This setting has no bearing on this widget, so it is removed.
    unset($form['match_operator']);

    $form['max_members'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number of thread members'),
      '#description' => $this->t('The maximum number of members that can be added to the private message conversation. Set to zero (0) to allow unlimited members'),
      '#default_value' => $this->getSetting('max_members'),
      '#min' => 0,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    if ($this->currentUser->hasPermission('access user profiles')) {
      $recipient_id = $this->requestStack->getCurrentRequest()->get('recipient');
      if ($recipient_id) {
        $recipient = user_load($recipient_id);
        if ($recipient) {
          $element['target_id']['#default_value'] = $recipient;
        }
      }
    }

    $max_members = $this->getSetting('max_members');
    if ($max_members) {
      $element['#element_validate'][] = [__CLASS__, 'validateFormElement'];
      $element['#max_members'] = $this->getSetting('max_members');
    }

    $element['#attached']['library'][] = 'private_message/members_widget';
    $url = Url::fromRoute('private_message.members_widget_callback');
    $token = $this->csrfTokenGenerator->get($url->getInternalPath());
    $url->setOptions(['absolute' => TRUE, 'query' => ['token' => $token]]);

    $element['#attached']['drupalSettings']['privateMessageMembersWidget']['callbackPath'] = $url->toString();
    $element['#attached']['drupalSettings']['privateMessageMembersWidget']['placeholder'] = $this->getSetting('placeholder');
    $element['#attached']['drupalSettings']['privateMessageMembersWidget']['fieldSize'] = $this->getSetting('size');
    $element['#attached']['drupalSettings']['privateMessageMembersWidget']['maxMembers'] = $max_members;

    $validate_username_url = Url::fromRoute('private_message.ajax_callback', ['op' => 'validate_private_message_member_username']);
    $validate_username_token = $this->csrfTokenGenerator->get($validate_username_url->getInternalPath());
    $validate_username_url->setOptions(['absolute' => TRUE, 'query' => ['token' => $validate_username_token]]);
    $element['#attached']['drupalSettings']['privateMessageMembersWidget']['validateUsernameUrl'] = $validate_username_url->toString();

    return $element;
  }

  /**
   * Validates the form element for number of users.
   *
   * Validates the form element to ensure that no more than the maximum number
   * of allowed users has been entered. This is because the field itself is
   * created as an unlimited cardinality field, but the widget allows for
   * setting a maximum number of users.
   */
  public static function validateFormElement(array $element, FormStateInterface $form_state) {
    $input_exists = FALSE;
    $parents = $element['#parents'];
    array_pop($parents);
    $value = NestedArray::getValue($form_state->getValues(), $parents, $input_exists);
    unset($value['add_more']);
    if (count($value) > $element['#max_members']) {
      $form_state->setError($element, t('Private messages threads cannot have more than @count members', ['@count' => $element['#max_members']]));
    }
  }

}
