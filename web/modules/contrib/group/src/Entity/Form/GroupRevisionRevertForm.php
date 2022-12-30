<?php

namespace Drupal\group\Entity\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity\Form\RevisionRevertForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a group revision.
 *
 * @internal
 */
class GroupRevisionRevertForm extends RevisionRevertForm {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var static $instance */
    $instance = parent::create($container);
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Core's Entity API should do this for us, but doesn't yet. Therefore, we
    // need to manually set some revision stuff in form submit handlers instead.
    // See: https://www.drupal.org/project/drupal/issues/1863258.
    // @todo Keep an eye on this from time to time and see if we can remove it.
    $this->revision->setRevisionUserId($this->currentUser()->id());
    $this->revision->setRevisionCreationTime($this->time->getRequestTime());
    $this->revision->setChangedTime($this->time->getRequestTime());
    parent::submitForm($form, $form_state);
  }

}
