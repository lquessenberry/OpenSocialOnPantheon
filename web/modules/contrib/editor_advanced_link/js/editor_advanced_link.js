
(function (Drupal, once, $) {

  'use strict';

  Drupal.behaviors.editor_advanced_link = {
    attach: function (context, settings) {
      // Reset modal window position when advanced details element is opened or
      // closed to prevent the element content to be out of the screen.
      once('editor_advanced_link', '.editor-link-dialog details[data-drupal-selector="edit-advanced"]').forEach((details) => {
        details.addEventListener('toggle', () => {
          $("#drupal-modal").dialog({
            position: {
              of: window
            }
          });
        });
      });

      // Add noopener to rel attribute if open link in new window checkbox is
      // checked.
      if (context.querySelector('input[data-drupal-selector="edit-attributes-rel"]')) {
        once('editor_advanced_linktargetrel', 'input[data-drupal-selector="edit-attributes-target"]').forEach((element) => {
          element.addEventListener('change', (evt) => {
            var checkbox = evt.currentTarget;
            var rel_attribute_field = document.querySelector('input[data-drupal-selector="edit-attributes-rel"]');

            var rel_attributes = rel_attribute_field.value.split(' ');
            if (checkbox.checked) {
              rel_attributes.push('noopener');
              Drupal.announce(Drupal.t('The noopener attribute has been added to rel.'));
            }
            else {
              rel_attributes = rel_attributes.filter((value) => value != 'noopener');
              Drupal.announce(Drupal.t('The noopener attribute has been removed from rel.'));
            }

            // Remove empty items.
            rel_attributes = rel_attributes.filter((value) => value.length);
            // Deduplicate items.
            rel_attributes = [...new Set(rel_attributes)];

            rel_attribute_field.value = rel_attributes.join(' ');
          })
        });
      }
    }
  };

}(Drupal, once, jQuery));
