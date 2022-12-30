(($, Drupal) => {
  Drupal.behaviors.tableSelect = {
    attach: function attach(context) {
      $(context)
        .find('th.select-all')
        .closest('table')
        .once('table-select')
        .each(Drupal.tableSelect);
    }
  };

  Drupal.tableSelect = function () {
    if ($(this).find('td input[type="checkbox"]').length === 0) {
      return;
    }

    var table = this;
    var checkboxes = 0;
    var lastChecked = 0;
    var $table = $(table);
    var strings = {
      selectAll: Drupal.t('Select all rows in this table'),
      selectNone: Drupal.t('Deselect all rows in this table')
    };
    var setClass = 'is-sticky';
    var $stickyHeader = $table
      .parents('form')
      .find('[data-drupal-selector*="edit-header"]');

    var updateSelectAll = function updateSelectAll(state) {
      $table
        .prev('table.sticky-header')
        .addBack()
        .find('th.select-all input[type="checkbox"]')
        .each(function () {
          var $checkbox = $(this);
          var stateChanged = $checkbox.prop('checked') !== state;

          $checkbox.attr('title', state ? strings.selectNone : strings.selectAll);

          if (stateChanged) {
            $checkbox.prop('checked', state).trigger('change');
          }
        });
    };
    var updateSticky = function updateSticky(state) {
      if (state === true) {
        $stickyHeader.addClass(setClass);
      }
      else {
        $stickyHeader.removeClass(setClass);
      }
    };

    $table
      .find('th.select-all')
      .prepend($(Drupal.theme('checkbox')).attr('title', strings.selectAll))
      .on('click', (event) => {
        if ($(event.target).is('input[type="checkbox"]')) {
          checkboxes.each(function () {
            var $checkbox = $(this);
            var stateChanged = $checkbox.prop('checked') !== event.target.checked;

            if (stateChanged) {
              $checkbox.prop('checked', event.target.checked).trigger('change');
            }

            $checkbox.closest('tr').toggleClass('selected', this.checked);
          });

          updateSelectAll(event.target.checked);
          updateSticky(event.target.checked);
        }
      });

    checkboxes = $table
      .find('td input[type="checkbox"]:enabled')
      .on('click', function (e) {
        $(this)
          .closest('tr')
          .toggleClass('selected', this.checked);

        if (e.shiftKey && lastChecked && lastChecked !== e.target) {
          Drupal.tableSelectRange($(e.target).closest('tr')[0], $(lastChecked).closest('tr')[0], e.target.checked);
        }

        updateSelectAll(checkboxes.length === checkboxes.filter(':checked').length);
        updateSticky(Boolean(Number(checkboxes.filter(':checked').length)));

        lastChecked = e.target;
      });

    updateSelectAll(checkboxes.length === checkboxes.filter(':checked').length);
    updateSticky(Boolean(Number(checkboxes.filter(':checked').length)));
  };

  Drupal.tableSelectRange = function (from, to, state) {
    var mode = from.rowIndex > to.rowIndex ? 'previousSibling' : 'nextSibling';

    for (var i = from[mode]; i; i = i[mode]) {
      var $i = $(i);

      if (i.nodeType !== 1) {
        continue;
      }

      $i.toggleClass('selected', state);
      $i.find('input[type="checkbox"]').prop('checked', state);

      if (to.nodeType) {
        if (i === to) {
          break;
        }
      }
      else if ($.filter(to, [i]).r.length) {
        break;
      }
    }
  };

  Drupal.behaviors.ginTableCheckbox = {
    attach: function (context) {
      if ( $("table td .checkbox-toggle", context).length > 0 ) {
        $("table td .checkbox-toggle", context).once().bind('click', function () {
          var checkBoxes = $(this).siblings("input");
          checkBoxes.prop("checked", !checkBoxes.prop("checked"));
        });
      }
    }
  };
})(jQuery, Drupal);
