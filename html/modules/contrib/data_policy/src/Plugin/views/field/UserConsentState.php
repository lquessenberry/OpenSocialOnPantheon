<?php

namespace Drupal\data_policy\Plugin\views\field;

use Drupal\data_policy\Entity\UserConsentInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to display user consent entity state.
 *
 * @ViewsField("user_consent_state")
 */
class UserConsentState extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $states = [
      UserConsentInterface::STATE_AGREE => $this->t('Agree'),
      UserConsentInterface::STATE_NOT_AGREE => $this->t('Not agree'),
      UserConsentInterface::STATE_UNDECIDED => $this->t('Undecided'),
    ];

    $value = $this->getValue($values);

    return $this->sanitizeValue($states[$value]);
  }

}
