<?php

namespace Drupal\field_group;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Static methods for fieldgroup formatters.
 */
class FormatterHelper implements TrustedCallbackInterface {

  /**
   * Return an array of field_group_formatter options.
   */
  public static function formatterOptions($type) {
    $options = &drupal_static(__FUNCTION__);

    if (!isset($options)) {
      $options = [];

      $manager = \Drupal::service('plugin.manager.field_group.formatters');
      $formatters = $manager->getDefinitions();

      foreach ($formatters as $formatter) {
        if (in_array($type, $formatter['supported_contexts'])) {
          $options[$formatter['id']] = $formatter['label'];
        }
      }
    }

    return $options;
  }

  /**
   * Pre render callback for rendering groups on entities without theme hook.
   *
   * @param array $element
   *   Entity being rendered.
   *
   * @return array
   */
  public static function entityViewPrender(array $element) {
    field_group_build_entity_groups($element, 'view');
    return $element;
  }

  /**
   * Process callback for field groups.
   *
   * @param array $element
   *   Form that is being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $form
   *   The complete form structure.
   *
   * @return array
   */
  public static function formProcess(array &$element, FormStateInterface $form_state = NULL, array &$form = []) {
    if (empty($element['#field_group_form_process'])) {
      $element['#field_group_form_process'] = TRUE;
      if (empty($element['#fieldgroups'])) {
        return $element;
      }

      // Create all groups and keep a flat list of references to these groups.
      $group_references = [];
      foreach ($element['#fieldgroups'] as $group_name => $group) {
        if (!isset($element[$group_name])) {
          $element[$group_name] = [];
        }

        $group_parents = $element['#array_parents'];
        if (empty($group->parent_name)) {
          if (isset($group->region)) {
            $group_parents[] = $group->region;
          }
        }
        else {
          $group_parents[] = $group->parent_name;
        }
        $group_references[$group_name] = &$element[$group_name];
        $element[$group_name]['#group'] = implode('][', $group_parents);

        // Use array parents to set the group name. This will cover multilevel forms (eg paragraphs).
        $parents = $element['#array_parents'];
        $parents[] = $group_name;
        $element[$group_name]['#parents'] = $parents;
        $group_children_parent_group = implode('][', $parents);
        foreach ($group->children as $child) {
          if (!empty($element[$child]['#field_group_ignore'])) {
            continue;
          }
          $element[$child]['#group'] = $group_children_parent_group;
        }
      }

      foreach ($element['#fieldgroups'] as $group_name => $group) {
        $field_group_element = &$element[$group_name];

        // Let modules define their wrapping element.
        // Note that the group element has no properties, only elements.
        foreach (Drupal::moduleHandler()->getImplementations('field_group_form_process') as $module) {
          // The intention here is to have the opportunity to alter the
          // elements, as defined in hook_field_group_formatter_info.
          // Note, implement $element by reference!
          $function = $module . '_field_group_form_process';
          $function($field_group_element, $group, $element);
        }

        // Allow others to alter the pre_render.
        Drupal::moduleHandler()->alter('field_group_form_process', $field_group_element, $group, $element);
      }

      // Allow others to alter the complete processed build.
      Drupal::moduleHandler()->alter('field_group_form_process_build', $element, $form_state, $form);
    }

    return $element;
  }


  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['entityViewPrender', 'formProcess'];
  }


}
