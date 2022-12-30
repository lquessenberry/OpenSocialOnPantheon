/* eslint-disable func-names, no-mutable-exports, comma-dangle, strict */

'use strict';

(($, Drupal, drupalSettings) => {
  Drupal.behaviors.ginEditForm = {
    attach: function attach() {
      const form = document.querySelector('.region-content form');
      const sticky = $('.gin-sticky').clone(true, true);
      const newParent = document.querySelector('.region-sticky__items__inner');

      if (newParent && newParent.querySelectorAll('.gin-sticky').length === 0) {
        sticky.appendTo($(newParent));

        // Input Elements
        const actionButtons = newParent.querySelectorAll('button[type="submit"], input[type="submit"]');

        if (actionButtons.length > 0) {
          actionButtons
            .forEach((el) => {
              el.setAttribute('form', form.getAttribute('id'));
              el.setAttribute('id', el.getAttribute('id') + '--gin-edit-form');
            });
        }

        // Make Published Status reactive
        const statusToggle = document.querySelectorAll('.field--name-status [name="status[value]"]');

        if (statusToggle.length > 0) {
          statusToggle.forEach((publishedState) => {
            publishedState.addEventListener('click', (event) => {
              const value = event.target.checked;
              // Sync value
              statusToggle.forEach((publishedState) => {
                publishedState.checked = value;
              });
            });
          });
        }

        setTimeout(() => {
          sticky.addClass('gin-sticky--visible');
        });
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
