<?php

namespace Drupal\override_node_options\Access\FormAccess;

use Drupal\Core\Form\FormStateInterface;

interface FormAccessOverrideInterface {

  public static function access(array &$form, FormStateInterface $form_state);

}
