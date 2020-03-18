/**
 * @file
 *
 * JQuery Colorpicker color selector.
 *
 * Alters field_suffix form element after change to the color field.
 */

(function ($) {
  'use strict';
  Drupal.behaviors.imageEffectsJqueryColorpickerColorSelector = {
    attach: function (context, settings) {
      $('.image-effects-jquery-colorpicker-color-selector .image-effects-jquery-colorpicker', context).once('image-effects-jquery-colorpicker-color-selector').each(function (index) {
        $(this).parent().append('<span class="image-effects-color-suffix">#' + this.value.toUpperCase() + '</div>');
        $(this).on('change', function (event) {
          var suffix = $(this).parent().find('.image-effects-color-suffix').get(0);
          $(suffix).text('#' + this.value.toUpperCase());
        });
      });
    }
  };
})(jQuery);
