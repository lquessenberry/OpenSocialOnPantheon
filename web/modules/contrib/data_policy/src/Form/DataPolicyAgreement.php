<?php

namespace Drupal\data_policy\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\data_policy\DataPolicyConsentManagerInterface;
use Drupal\data_policy\Entity\DataPolicyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Policy agreement form.
 *
 * @ingroup data_policy
 */
class DataPolicyAgreement extends FormBase {
  use StringTranslationTrait;

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
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * DataPolicyAgreement constructor.
   *
   * @param \Drupal\data_policy\DataPolicyConsentManagerInterface $data_policy_manager
   *   The Data Policy consent manager.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $destination
   *   The redirect destination helper.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(
    DataPolicyConsentManagerInterface $data_policy_manager,
    RedirectDestinationInterface $destination,
    DateFormatterInterface $date_formatter
  ) {
    $this->dataPolicyConsentManager = $data_policy_manager;
    $this->destination = $destination;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('data_policy.manager'),
      $container->get('redirect.destination'),
      $container->get('date.formatter')
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
    $this->dataPolicyConsentManager->addCheckbox($form);
    $this->dataPolicyConsentManager->saveConsent($this->currentUser()->id(), 'visit');

    // Add a message that the data policy was updated.
    $entity_ids = $this->dataPolicyConsentManager->getEntityIdsFromConsentText();
    $revisions = $this->dataPolicyConsentManager->getRevisionsByEntityIds($entity_ids);
    $timestamps = array_map(function (DataPolicyInterface $revision) {
      return $revision->getChangedTime();
    }, $revisions);
    $timestamp = max($timestamps);
    $date = $this->dateFormatter->format($timestamp, 'html_date');

    $form['date'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'info' => [
          [
            '#type' => 'html_tag',
            '#tag' => 'strong',
            '#value' => $this->t('Our data policy has been updated on %date', [
              '%date' => $date,
            ]),
          ],
        ],
      ],
      '#weight' => -2,
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
        '#weight' => -1,
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
    $values = $form_state->getValue('data_policy');
    $result = [];

    foreach ($values as $name => $value) {
      $result[$name] = [
        'value' => $value,
        'required' => $form['account']['data_policy'][$name]['#required'],
        'entity_id' => (int) filter_var($name, FILTER_SANITIZE_NUMBER_INT),
        'state' => !empty($values[$name]),
      ];
    }

    $this->dataPolicyConsentManager->saveConsent($this->currentUser()->id(), 'submit', $result);

    foreach ($result as $item) {
      // If the user does not agree and it is enforced, we will redirect to
      // the cancel account page.
      if ($item['required'] && !$item['value']) {
        $this->getRequest()->query->remove('destination');

        $form_state->setRedirect('entity.user.cancel_form', [
          'user' => $this->currentUser()->id(),
        ]);
      }
    }

    // If the user agrees or does not agree (but it is not enforced), check if
    // we should redirect to the front page.
    if ($this->destination->get() === '/data-policy-agreement') {
      $form_state->setRedirect('<front>');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $errors = $form_state->getErrors();

    if (!empty($errors)) {
      $form_state->clearErrors();

      foreach ($errors as $id => $error) {
        /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $g */
        if (strpos($id, 'data_policy') !== FALSE) {
          $name = Markup::create($error->getArguments()['@name']);
          $form_state->setErrorByName($id, $this->t('@name field is required.', ['@name' => $name]));
          continue;
        }

        $form_state->setErrorByName($id, $error);
      }
    }

    parent::validateForm($form, $form_state);
  }

}
