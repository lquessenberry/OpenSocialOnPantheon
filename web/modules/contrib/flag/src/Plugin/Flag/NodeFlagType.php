<?php

namespace Drupal\flag\Plugin\Flag;

use Drupal\Core\Session\AccountInterface;
use Drupal\flag\Plugin\Flag\EntityFlagType;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a flag type for all content entities.
 *
 * @FlagType(
 *   id = "entity:node",
 *   title = @Translation("Content"),
 *   entity_type = "node",
 *   provider = "node"
 * )
 */
class NodeFlagType extends EntityFlagType {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $options = parent::defaultConfiguration();
    // Use own display settings in the meanwhile.
    $options += [
      'access_author' => '',
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /* Options form extras for node flags. */

    $form['access']['access_author'] = [
      '#type' => 'radios',
      '#title' => $this->t('Flag access by content authorship'),
      '#options' => [
        '' => $this->t('No additional restrictions'),
        'own' => $this->t('Users may only flag content they own'),
        'others' => $this->t('Users may only flag content of others'),
      ],
      '#default_value' => $this->getAccessAuthorSetting(),
      '#description' => $this->t("Restrict access to this flag based on the user's ownership of the content. Users must also have access to the flag through the role settings."),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['access_author'] = $form_state->getValue('access_author');
  }

  /**
   * Returns the flag type access author setting.
   *
   * @return string
   *   The access author setting can be one of three values:
   *   - '' = No additional restrictions.
   *   - 'own' = Users may only flag content they own.
   *   - 'others' = Users may only flag content of others.
   */
  public function getAccessAuthorSetting() {
    return $this->configuration['access_author'];
  }
}
