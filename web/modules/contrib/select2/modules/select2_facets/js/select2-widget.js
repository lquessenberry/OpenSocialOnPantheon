/**
 * @file
 * Init select2 widget.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.facets = Drupal.facets || {};

  /**
   * Add event handler to all select2 widgets.
   */
  Drupal.facets.initSelect2 = function () {
    $('.js-facets-select2.js-facets-widget')
      .once('js-facets-select2-widget-on-selection-change')
      .each(function () {
        var $select2_widget = $(this);

        $select2_widget.on('select2:select select2:unselect', function (item) {
          $select2_widget.trigger('facets_filter', [item.params.data.id]);
        });

        $select2_widget.on('facets_filtering.select2', function () {
          $select2_widget.prop('disabled', true);
        });
      });
  };

  /**
   * Behavior to register select2 widget to be used for facets.
   */
  Drupal.behaviors.facetsSelect2Widget = {
    attach: function (context, settings) {
      Drupal.facets.initSelect2(context, settings);
    }
  };

})(jQuery, Drupal);
