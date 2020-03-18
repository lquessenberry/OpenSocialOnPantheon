<?php

namespace Drupal\data_policy\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\data_policy\Entity\DataPolicy;
use Drupal\data_policy\DataPolicyConsentManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DataPolicyAgreement.
 *
 * @ingroup data_policy
 */
class DataPolicyAgreement extends FormBase {

  /**
   * The Data Policy consent manager.
   *
   * @var \Drupal\data_policy\DataPolicyConsentManagerInterface
   */
  protected $dataPolicyConsentManager;

  /**
   * The redirect destination helper.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $destination;

  /**
   * DataPolicyAgreement constructor.
   *
   * @param \Drupal\data_policy\DataPolicyConsentManagerInterface $data_policy_manager
   *   The Data Policy consent manager.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $destination
   *   The redirect destination helper.
   */
  public function __construct(DataPolicyConsentManagerInterface $data_policy_manager, RedirectDestinationInterface $destination) {
    $this->dataPolicyConsentManager = $data_policy_manager;
    $this->destination = $destination;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('data_policy.manager'),
      $container->get('redirect.destination')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'data_policy_data_policy_agreement';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->dataPolicyConsentManager->saveConsent($this->currentUser()->id());

    $this->dataPolicyConsentManager->addCheckbox($form);

    // Add a message that the data policy was updated.
    $entity_id = $this->config('data_policy.data_policy')->get('entity_id');
    $timestamp = DataPolicy::load($entity_id)->getChangedTime();
    $date = \Drupal::service('date.formatter')->format($timestamp, 'html_date');
    $form['date'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'info' => [
          [
            '#type' => 'html_tag',
            '#tag' => 'strong',
            '#value' => t('Our data policy has been updated on %date', [
              '%date' => $date,
            ]),
          ],
        ],
      ],
      '#weight' => -1,
    ];

    if (!empty($this->config('data_policy.data_policy')->get('enforce_consent'))) {
      $form['data_policy']['#weight'] = 1;

      $link = Link::createFromRoute($this->t('the account cancellation'), 'entity.user.cancel_form', [
        'user' => $this->currentUser()->id(),
      ]);

      $form['not_agree'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Agreement to the data policy is required for continue using this platform. If you do not agree with the data policy, you will be guided to @url process.', [
          '@url' => $link->toString(),
        ]),
        '#theme_wrappers' => [
          'form_element',
        ],
        '#weight' => 0,
      ];
    }

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $agree = !empty($form_state->getValue('data_policy'));
    $enforce = $this->config('data_policy.data_policy')->get('enforce_consent');

    $this->dataPolicyConsentManager->saveConsent($this->currentUser()->id(), $agree);

    // If the user agrees or does not agree (but it is not enforced), check if
    // we should redirect him to the front page.
    if ($agree || (!$agree && empty($enforce))) {
      if ($this->destination->get() === '/data-policy-agreement') {
        $form_state->setRedirect('<front>');
      }
    }

    // If the user does not agree and it is enforced, we will redirect him to
    // the cancel account page.
    if (!$agree && !empty($enforce)) {
      $this->getRequest()->query->remove('destination');

      $form_state->setRedirect('entity.user.cancel_form', [
        'user' => $this->currentUser()->id(),
      ]);
    }
  }

}
