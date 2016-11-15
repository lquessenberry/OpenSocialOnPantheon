<?php

namespace Drupal\flag\Plugin\Flag;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a flag type for comments.
 *
 * @FlagType(
 *   id = "entity:comment",
 *   title = @Translation("Comment"),
 *   entity_type = "comment",
 *   provider = "comment"
 * )
 */
class CommentFlagType extends EntityFlagType {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $options = parent::defaultConfiguration();
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

    /* Options form extras for comment flags. */

    $form['access']['access_author'] = [
      '#type' => 'radios',
      '#title' => $this->t('Flag access by content authorship'),
      '#options' => [
        '' => $this->t('No additional restrictions'),
        'comment_own' => $this->t('Users may only flag own comments'),
        'comment_others' => $this->t('Users may only flag comments by others'),
        'node_own' => $this->t('Users may only flag comments of nodes they own'),
        'node_others' => $this->t('Users may only flag comments of nodes by others'),
      ],
      '#default_value' => $this->configuration['access_author'],
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
   * {@inheritdoc}
   */
  public function getAccessAuthorSetting() {
    return $this->configuration['access_author'];
  }
}
