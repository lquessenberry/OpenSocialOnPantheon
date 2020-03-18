/**
 * @file
 * Defines Javascript behaviors for the flag module.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.flagsSummary = {
    attach: function (context) {
      var $context = $(context);
      $context.find('details[data-drupal-selector="edit-flag"]').drupalSetSummary(function (context) {
        var checkedBoxes = $(context).find('input:checkbox:checked');
        if (checkedBoxes.length === 0) {
          return Drupal.t('No flags');
        }
        var getTitle = function () {return this.title; };
        return checkedBoxes.map(getTitle).toArray().join(', ');
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
