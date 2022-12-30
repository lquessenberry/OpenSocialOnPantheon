(function ($, Drupal) {
  Drupal.behaviors.MediaLibrarySelectAll = {
    attach: function attach(context) {
      var $view = $('.js-media-library-view[data-view-display-id="page"]', context).once('media-library-select-all');
      if ($view.length && $view.find('.js-media-library-item').length) {
        var $checkbox = $(Drupal.theme('checkbox')).on('click', function (_ref) {
          var currentTarget = _ref.currentTarget;

          var $checkboxes = $(currentTarget).closest('.js-media-library-view').find('.js-media-library-item input[type="checkbox"]');
          $checkboxes.prop('checked', $(currentTarget).prop('checked')).trigger('change');

          var announcement = $(currentTarget).prop('checked') ? Drupal.t('All @count items selected', {
            '@count': $checkboxes.length
          }) : Drupal.t('Zero items selected');
          Drupal.announce(announcement);
        });
        var $label = $('<label class="media-library-select-all"></label>').text(Drupal.t('Select all media'));
        $label.prepend($checkbox);
        $view.find('.js-media-library-item').first().before($label);
      }

      // Media Library select
      $('.media-library-view .form-checkbox', context).on('click', function() {
        var $bulkOperations = $(this).parents('.media-library-view').find('[data-drupal-selector*="edit-header"]');

        if ($('.media-library-view .form-checkbox:checked', context).length > 0) {
          $bulkOperations.addClass('is-sticky');
        } else {
          $bulkOperations.removeClass('is-sticky');
        }
      });

      // Media Library select
      $('.media-library-view .media-library-item__click-to-select-trigger', context).on('click', function() {
        var $bulkOperations = $(this).parents('.media-library-view').find('[data-drupal-selector*="edit-header"]');

        if ($('.media-library-view .form-checkbox:checked', context).length > 0) {
          $bulkOperations.addClass('is-sticky');
        } else {
          $bulkOperations.removeClass('is-sticky');
        }

        var selectAll = $('.media-library-select-all input');
        var checkboxes = $('.media-library-view .media-library-item input');

        if (selectAll.filter(':checked').length === 1 && checkboxes.length !== checkboxes.filter(':checked').length) {
          selectAll.prop('checked', false).trigger('change');
        }
      });
    }
  };
})(jQuery, Drupal);
