<?php

namespace Drupal\override_node_options\Access\FormAccess;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeForm;

class GrantRevisionAccess implements FormAccessOverrideInterface {

  public static function access(array &$form, FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();

    if ($form_object instanceof NodeForm) {
      /** @var AccountProxyInterface $user */
      $user = \Drupal::currentUser();

      if ($user->hasPermission('administer nodes')) {
        return;
      }

      $node_type = $form_object->getEntity()->bundle();

      $form['revision']['#access'] = $user->hasPermission("override $node_type revision option")
        || $user->hasPermission('override all revision option');
    }
  }

}
