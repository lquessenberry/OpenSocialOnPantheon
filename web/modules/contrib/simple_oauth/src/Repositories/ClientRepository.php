<?php

namespace Drupal\simple_oauth\Repositories;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Drupal\simple_oauth\Entities\ClientEntity;

/**
 * The client repository.
 */
class ClientRepository implements ClientRepositoryInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The password hashing service.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected PasswordInterface $passwordChecker;

  /**
   * Constructs a ClientRepository object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PasswordInterface $password_checker) {
    $this->entityTypeManager = $entity_type_manager;
    $this->passwordChecker = $password_checker;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientEntity($clientIdentifier) {
    $client_drupal_entities = $this->entityTypeManager
      ->getStorage('consumer')
      ->loadByProperties(['client_id' => $clientIdentifier]);

    // Check if the client is registered.
    if (empty($client_drupal_entities)) {
      return NULL;
    }
    $client_drupal_entity = reset($client_drupal_entities);

    return new ClientEntity($client_drupal_entity);
  }

  /**
   * {@inheritdoc}
   */
  public function validateClient($clientIdentifier, $clientSecret, $grantType) {
    $client_entity = $this->getClientEntity($clientIdentifier);
    if (!$client_entity) {
      return FALSE;
    }
    $client_drupal_entity = $client_entity->getDrupalEntity();

    // For the client credentials grant type a default user is required.
    if ($grantType === 'client_credentials' && !$client_drupal_entity->get('user_id')->entity) {
      throw OAuthServerException::serverError('Invalid default user for client.');
    }

    $secret_field = $client_drupal_entity->get('secret');

    // Determine whether the client is public. Note that if a client secret is
    // provided it should be validated, even if the client is non-confidential.
    // The client_credentials grant is specifically special-cased.
    // @see https://datatracker.ietf.org/doc/html/rfc6749#section-4.4
    if (!$client_drupal_entity->get('confidential')->value &&
      $secret_field->isEmpty() &&
      empty($client_secret) &&
      $grantType !== 'client_credentials') {
      return TRUE;
    }

    // Check if a secret has been provided for this client and validate it.
    // @see https://datatracker.ietf.org/doc/html/rfc6749#section-3.2.1
    return !(!$secret_field->isEmpty()) || $clientSecret && $this->passwordChecker->check($clientSecret, $client_drupal_entity->get('secret')->value);
  }

}
