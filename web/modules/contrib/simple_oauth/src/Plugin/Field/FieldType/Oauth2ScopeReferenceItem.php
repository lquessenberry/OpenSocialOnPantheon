<?php

namespace Drupal\simple_oauth\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\simple_oauth\Oauth2ScopeInterface;

/**
 * Plugin implementation of the 'oauth2_scope_reference' field type.
 *
 * @FieldType(
 *   id = "oauth2_scope_reference",
 *   label = @Translation("OAuth2 scope reference"),
 *   description = @Translation("An entity field containing a oauth2_scope reference."),
 *   category = @Translation("Reference"),
 *   default_widget = "oauth2_scope_reference",
 *   list_class = "\Drupal\simple_oauth\Plugin\Field\FieldType\Oauth2ScopeReferenceItemList",
 * )
 */
class Oauth2ScopeReferenceItem extends FieldItemBase implements Oauth2ScopeReferenceItemInterface, OptionsProviderInterface {

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'scope_id';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['scope_id'] = DataDefinition::create('string')
      ->setLabel(t('Scope ID'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'scope_id' => [
          'description' => 'The scope id',
          'type' => 'varchar',
          'length' => 255,
        ],
      ],
      'indexes' => ['scope_id' => ['scope_id']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('scope_id')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public function getScope(): ?Oauth2ScopeInterface {
    if (empty($this->scope_id)) {
      return NULL;
    }

    /** @var \Drupal\simple_oauth\Oauth2ScopeAdapterInterface $scope_provider */
    $scope_provider = \Drupal::service('simple_oauth.oauth2_scope.provider');

    return $scope_provider->load($this->scope_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleValues(AccountInterface $account = NULL) {
    return array_keys($this->getPossibleOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleOptions(AccountInterface $account = NULL) {
    /** @var \Drupal\simple_oauth\Oauth2ScopeAdapterInterface $scope_provider */
    $scope_provider = \Drupal::service('simple_oauth.oauth2_scope.provider');
    $scopes = $scope_provider->loadMultiple();

    return array_map(function (Oauth2ScopeInterface $scope) {
      return $scope->getName();
    }, $scopes);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableValues(AccountInterface $account = NULL) {
    return array_keys($this->getPossibleOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableOptions(AccountInterface $account = NULL) {
    return $this->getPossibleOptions($account);
  }

}
