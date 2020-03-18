/**
 * @file
 *
 * HTML color selector.
 *
 * Alters field_suffix form element after change to the color field.
 */

(function ($) {
  'use strict';
  Drupal.behaviors.imageEffectsHtmlColorSelector = {
    attach: function (context, settings) {
      $('.image-effects-html-color-selector .form-color', context).once('image-effects-html-color-selector').each(function (index) {
        $(this).on('change', function (event) {
          var suffix = $(this).parents('.image-effects-html-color-selector').find('.field-suffix').get(0);
          $(suffix).text(this.value.toUpperCase());
        });
      });
    }
  };
})(jQuery);
