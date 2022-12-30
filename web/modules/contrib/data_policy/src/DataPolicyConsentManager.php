<?php

namespace Drupal\data_policy;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\data_policy\Entity\DataPolicyInterface;
use Drupal\data_policy\Entity\UserConsentInterface;

/**
 * Defines the Data Policy Consent Manager service.
 */
class DataPolicyConsentManager implements DataPolicyConsentManagerInterface {

  use StringTranslationTrait;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The data policy entity.
   *
   * @var \Drupal\data_policy\Entity\DataPolicyInterface
   */
  protected $entity;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a new GDPR Consent Manager service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    EntityRepositoryInterface $entity_repository
  ) {
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function needConsent() {
    return $this->isDataPolicy() && !$this->currentUser->hasPermission('without consent');
  }

  /**
   * {@inheritdoc}
   */
  public function addCheckbox(array &$form) {
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $entity_ids = $this->getEntityIdsFromConsentText();
    $revisions = $this->getRevisionsByEntityIds($entity_ids);
    $links = [];

    $query = $this->database->select('user_consent', 'uc');
    $query->condition('status', TRUE);
    $query->condition('state', UserConsentInterface::STATE_AGREE);
    $query->condition('user_id', $this->currentUser->id());

    $query->join('user_consent__data_policy_revision_id', 'ucr', 'uc.id = ucr.entity_id');
    $query->addField('ucr', 'data_policy_revision_id_value');
    $user_agree_revision_ids = $query->execute()->fetchCol();

    foreach ($revisions as $key => $revision) {
      // Get translation for current revision if exists.
      $revision = $this->entityRepository->getTranslationFromContext($revision);
      $links[$key] = Link::createFromRoute(strtolower($revision->getName()), 'data_policy.data_policy', ['id' => $revision->id()], [
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'title' => $revision->getName(),
            'width' => 700,
            'height' => 700,
          ]),
          'checked' => in_array($revision->getRevisionId(), $user_agree_revision_ids),
        ],
      ]);
    }

    $enforce_consent_text = $this->getConfig('consent_text');
    $items = preg_split('/\R/', $enforce_consent_text);

    // Checkboxes always should be under all existing fields.
    // See social_registration_fields module.
    $form['account']['data_policy'] = [
      '#type' => 'details',
      '#tree' => TRUE,
      '#weight' => 110,
      '#open' => TRUE,
    ];

    foreach ($links as $entity_id => $link) {
      /** @var \Drupal\Core\Link $link */
      $data = [];
      foreach ($items as $item) {
        if (strpos($item, "[id:{$entity_id}") !== FALSE) {
          $data = [
            'text' => $item,
            'required' => strpos($item, "[id:{$entity_id}*]") ? TRUE : FALSE,
          ];
        }
        continue;
      }

      $enforce_consent_text = str_replace(
          ["[id:{$entity_id}*]", "[id:{$entity_id}]"],
          $link->toString(),
          $data['text']
      );

      $form['account']['data_policy']['data_policy_' . $entity_id] = [
        '#type' => 'checkbox',
        '#title' => $enforce_consent_text,
        '#required' => $data['required'],
        '#weight' => 110 + $entity_id,
        '#default_value' => $link->getUrl()->getOption('attributes')['checked'],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveConsent($user_id, $action = NULL, array $values = ['state' => UserConsentInterface::STATE_UNDECIDED]) {
    // This logic determines whether we need to create a new "user_consent"
    // entity or not, depending on whether there are new and active
    // "data_policy" with which the user should agree. Previously, there
    // was a `getState` method for this, but it is not relevant since now we
    // do not have a binding to only one entity.
    // See \Drupal\data_policy\Form\DataPolicyAgreement::submitForm.
    $user_consents = $this->entityTypeManager->getStorage('user_consent')
      ->loadByProperties([
        'user_id' => $user_id,
        'status' => TRUE,
      ]);

    /** @var \Drupal\data_policy\DataPolicyStorageInterface $data_policy_storage */
    $data_policy_storage = $this->entityTypeManager->getStorage('data_policy');
    // Existing states for the current user.
    $existing_states = array_map(function (UserConsentInterface $user_consent) {
      return $user_consent->state->value;
    }, $user_consents);

    if ($action === 'submit') {
      $first = FALSE;

      foreach ($values as $value) {
        $state = $this->getStateNumber($value['state']);
        $is_equals = TRUE;

        foreach ($existing_states as $existing_state) {
          if ($existing_state != $state) {
            $is_equals = FALSE;
            break;
          }
        }
        if (empty($existing_states) || count($existing_states) != count($values)) {
          $is_equals = FALSE;
        }

        // If submitted states for user_consent entities are the same as
        // existing then we just need to do nothing.
        if ($is_equals) {
          return;
        }

        // Set an "unpublished" status for all "user_consent" entities that
        // were active before submit.
        if (!empty($user_consents) && $first === FALSE) {
          foreach ($user_consents as $user_consent) {
            $user_consent->setPublished(FALSE)->save();
            $first = TRUE;
          }
        }

        // Create new "user_consent" entities with active revision from
        // user consent text in the settings tab.
        /** @var \Drupal\data_policy\Entity\DataPolicyInterface $data_policy */
        $data_policy = $data_policy_storage->load($value['entity_id']);
        $is_required = $this->isRequiredEntity($value['entity_id']);
        $this->createUserConsent($data_policy, $user_id, $state, $is_required);
      }
    }
    // See \Drupal\data_policy\Form\DataPolicyAgreement::buildForm.
    elseif ($action === 'visit') {
      $state = $this->getStateNumber($values['state']);
      $entities = $this->getEntityIdsFromConsentText();

      if (!empty($existing_states)) {
        // Existing revisions for the current user.
        $existing_revisions = array_map(function (UserConsentInterface $user_consent) {
          return $user_consent->data_policy_revision_id->value;
        }, $user_consents);

        $revisions = $this->getRevisionsByEntityIds($entities);
        $revision_ids_from_consent_text = array_map(function (DataPolicyInterface $revision) {
          return $revision->getRevisionId();
        }, $revisions);
        // If existing revisions for the current user are different from
        // current revisions (consent text in setting form) then we should
        // create "user_consent" entities with zero state and all entities
        // for the current user before visit the agreement page will be
        // removed from published.
        $diff = array_diff($existing_revisions, $revision_ids_from_consent_text);
        $confirmed = array_flip(array_intersect($existing_revisions, $revision_ids_from_consent_text));

        if (!empty($diff)) {
          // Remove from the publication the old consents, those that were
          // previously agreed - skip.
          foreach ($user_consents as $id => $user_consent) {
            if (in_array($id, $confirmed)) {
              continue;
            }
            $user_consent->setPublished(FALSE)->save();
          }

          // Create new ones, those that were previously agreed - skip.
          foreach (array_diff($revision_ids_from_consent_text, array_flip($confirmed)) as $revision_id) {
            /** @var \Drupal\data_policy\Entity\DataPolicyInterface $data_policy */
            $data_policy = $data_policy_storage->loadRevision($revision_id);
            $is_required = $this->isRequiredEntity($data_policy->id());
            $this->createUserConsent($data_policy, $user_id, $state, $is_required);
          }
        }

        $is_equals = TRUE;
        $skip = TRUE;

        foreach ($existing_states as $existing_state) {
          // If the current state for the current user more then existing in
          // the database then we need to create new entries in the database.
          if ((int) $existing_state < (int) $state) {
            $skip = FALSE;
            break;
          }

          // If existing states are not equal the current user state then we
          // need to create new entries in the database.
          if ($existing_state != $state) {
            $is_equals = FALSE;
            break;
          }
        }
        if ($is_equals || $skip) {
          return;
        }
      }

      foreach ($entities as $entity) {
        /** @var \Drupal\data_policy\Entity\DataPolicyInterface $data_policy */
        $data_policy = $data_policy_storage->load($entity);
        $is_required = $this->isRequiredEntity($entity);
        $this->createUserConsent($data_policy, $user_id, $state, $is_required);
      }
    }
  }

  /**
   * Get the number of bool state.
   *
   * @param bool $state
   *   The state value;.
   *
   * @return int
   *   User consent.
   */
  private function getStateNumber($state) {
    if ($state === TRUE) {
      $state = UserConsentInterface::STATE_AGREE;
    }
    elseif ($state === FALSE) {
      $state = UserConsentInterface::STATE_NOT_AGREE;
    }

    return $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getExistingUserConsents($user_id) {
    return $this->entityTypeManager
      ->getStorage('user_consent')
      ->getQuery()
      ->condition('status', 1)
      ->condition('user_id', $user_id)
      ->execute();
  }

  /**
   * Create the user_consent entity.
   *
   * @param \Drupal\data_policy\Entity\DataPolicyInterface $data_policy
   *   The data policy entity.
   * @param int $user_id
   *   The user id.
   * @param int $state
   *   The state for consent entity.
   * @param int $required
   *   Required status.
   */
  private function createUserConsent(DataPolicyInterface $data_policy, int $user_id, int $state, int $required) {
    $this->entityTypeManager->getStorage('user_consent')
      ->create()
      ->setRevision($data_policy)
      ->setOwnerId($user_id)
      ->set('state', $state)
      ->set('required', $required)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function isDataPolicy() {
    return !empty($this->getEntityIdsFromConsentText());
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIdsFromConsentText(): array {
    $consent_text = $this->getConfig('consent_text');
    preg_match_all("#\[id:(.*)\]#", $consent_text, $matches);

    foreach ($matches[1] as $match) {
      $ids[] = (int) $match;
    }

    return $ids ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function isRequiredEntityInEntities(array $data_policy_ids): bool {
    $consent_text = $this->getConfig('consent_text');
    preg_match_all("#\[id:(.*)\]#", $consent_text, $matches);

    foreach ($matches[1] as $match) {
      if (strpos($match, '*') !== FALSE && in_array((int) $match, $data_policy_ids)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isRequiredEntity($data_policy_id):bool {
    return $this->isRequiredEntityInEntities([$data_policy_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionsByEntityIds(array $entity_ids): array {
    return $this->entityTypeManager->getStorage('data_policy')->loadMultiple($entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig($name) {
    return $this->configFactory->get('data_policy.data_policy')->get($name);
  }

}
