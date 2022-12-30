/**
 * @file
 * Select2 integration.
 */
(function ($, drupalSettings) {
  'use strict';

  Drupal.behaviors.select2_publish = {
    attach: function (context) {
      $('.select2-widget', context).on('select2-init', function (e) {
        if (typeof $(e.target).data('select2-publish-default') === 'undefined') {
          return;
        }
        var config = $(e.target).data('select2-config');

        var parentCreateTagHandler = config.createTag;
        config.createTag = function (params) {
          var term = parentCreateTagHandler(params);
          if (term) {
            term.published = $(e.target).data('select2-publish-default');
          }
          return term;
        };

        var templateHandlerWrapper = function (parentHandler) {
          return function (option, item) {
            if (parentHandler) {
              parentHandler(option, item);
            }
            if (item) {
              var published = (option.published === true || $(option.element).attr('data-published') === 'true');
              $(item).addClass(published ? 'published' : 'unpublished');
            }
            return option.text;
          };
        };

        config.templateSelection = templateHandlerWrapper(config.templateSelection);
        config.templateResult = templateHandlerWrapper(config.templateResult);

        $(e.target).data('select2-config', config);
      });
    }
  };

})(jQuery, drupalSettings);
