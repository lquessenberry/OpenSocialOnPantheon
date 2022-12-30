/**
 * @file
 * Select-All Button functionality.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.views_bulk_operations = {
    attach: function (context, settings) {
      once('vbo-init', '.vbo-view-form', context).forEach(Drupal.viewsBulkOperationsFrontUi);
    }
  };

  /**
   * Views Bulk Operation selection object.
   */
  Drupal.viewsBulkOperationsSelection = {
    view_id: '',
    display_id: '',
    list: {},
    $summary: null,
    $actionSelect: null,
    vbo_form: null,

    /**
     * Bind event handlers to an element.
     *
     * @param {jQuery} element
     */
    bindEventHandlers: function ($element, index) {
      if ($element.length) {
        var selectionObject = this;
        $element.on('keypress', function (event) {
          // Emulate click action for enter key.
          if (event.which === 13) {
            event.preventDefault();
            event.stopPropagation();
            selectionObject.update(!this.checked, index, $(this).val());
            $(this).trigger('click');
          }
          if (event.which === 32) {
            selectionObject.update(!this.checked, index, $(this).val());
          }
        });
        $element.on('click', function (event) {
          // Act only on left button click.
          if (event.which === 1) {
            selectionObject.update(this.checked, index, $(this).val());
          }
        });
      }
    },

    bindCheckboxes: function () {
      var selectionObject = this;
      var checkboxes = $('.form-checkbox', this.vbo_form);
      checkboxes.on('change', function (event) {
        selectionObject.toggleButtonsState();
      });
    },

    toggleButtonsState: function() {
      // If no rows are checked, disable any form submit actions.
      var checkedCheckboxes = $('.form-checkbox:checked', this.vbo_form);
      var buttons = $('[id^="edit-actions"] input[type="submit"], [id^="edit-actions"] button[type="submit"]', this.vbo_form);
      var selectedAjaxItems = $('.vbo-info-list-wrapper li', this.vbo_form);
      var anyItemsSelected = selectedAjaxItems.length || checkedCheckboxes.length;
      if (this.$actionSelect.length) {
        var has_selection = anyItemsSelected && this.$actionSelect.val() !== '';
        buttons.prop('disabled', !has_selection);
      }
      else {
        buttons.prop('disabled', !anyItemsSelected);
      }
    },

    bindActionSelect: function () {
      if (this.$actionSelect.length) {
        var selectionObject = this;
        this.$actionSelect.on('change', function (event) {
          selectionObject.toggleButtonsState();
        });
      }
    },

    /**
     * Perform an AJAX request to update selection.
     *
     * @param {bool} state
     * @param {mixed} index
     * @param {string} value
     */
    update: function (state, index, value) {
      if (value === undefined) {
        value = null;
      }
      if (this.view_id.length && this.display_id.length) {
        // TODO: prevent form submission when ajaxing.

        var selectionObject = this;
        var list = {}, op = '';
        if (index === 'selection_method_change') {
          var op = state ? 'method_exclude' : 'method_include';
          if (state) {
            list = this.list[index];
          }
        }
        else {
          if (value && value != 'on') {
            list[value] = this.list[index][value];
          }
          else {
            list = this.list[index];
          }
          op = state ? 'add' : 'remove';
        }

        var $summary = this.$summary;
        var $selectionInfo = this.$selectionInfo;
        var target_uri = drupalSettings.path.baseUrl + drupalSettings.path.pathPrefix + 'views-bulk-operations/ajax/' + this.view_id + '/' + this.display_id;

        $.ajax(target_uri, {
          method: 'POST',
          data: {
            list: list,
            op: op
          },
          success: function (data) {
            $selectionInfo.html(data.selection_info);
            $summary.text(Drupal.formatPlural(data.count, 'Selected 1 item', 'Selected @count items'));
            selectionObject.toggleButtonsState();
          }
        });
      }
    }
  }

  /**
   * Callback used in {@link Drupal.behaviors.views_bulk_operations}.
   */
  Drupal.viewsBulkOperationsFrontUi = function (element) {
    var $vboForm = $(element);
    var $viewsTables = $('.vbo-table', $vboForm);
    var $primarySelectAll = $('.vbo-select-all', $vboForm);
    var tableSelectAll = [];
    Drupal.viewsBulkOperationsSelection.vbo_form = $vboForm;
    Drupal.viewsBulkOperationsSelection.$actionSelect = $('select[name="action"]', $vboForm);

    // When grouping is enabled, there can be multiple tables.
    if ($viewsTables.length) {
      $viewsTables.each(function (index) {
        tableSelectAll[index] = $vboForm.find('.select-all input').first();
      });
      var $tableSelectAll = $(tableSelectAll);
    }

    // Add AJAX functionality to row selector checkboxes.
    var $multiSelectElement = $vboForm.find('.vbo-multipage-selector').first();
    if ($multiSelectElement.length) {

      Drupal.viewsBulkOperationsSelection.$selectionInfo = $multiSelectElement.find('.vbo-info-list-wrapper').first();
      Drupal.viewsBulkOperationsSelection.$summary = $multiSelectElement.find('summary').first();
      Drupal.viewsBulkOperationsSelection.view_id = $multiSelectElement.attr('data-view-id');
      Drupal.viewsBulkOperationsSelection.display_id = $multiSelectElement.attr('data-display-id');

      // Get the list of all checkbox values and add AJAX callback.
      Drupal.viewsBulkOperationsSelection.list = [];

      var $contentWrappers;
      if ($viewsTables.length) {
        $contentWrappers = $viewsTables;
      }
      else {
        $contentWrappers = $([$vboForm]);
      }

      $contentWrappers.each(function (index) {
        var $contentWrapper = $(this);
        Drupal.viewsBulkOperationsSelection.list[index] = {};

        $contentWrapper.find('.views-field-views-bulk-operations-bulk-form input[type="checkbox"]').each(function () {
          var value = $(this).val();
          if (value != 'on') {
            Drupal.viewsBulkOperationsSelection.list[index][value] = value;
            Drupal.viewsBulkOperationsSelection.bindEventHandlers($(this), index);
          }
        });

        // Bind event handlers to select all checkbox.
        if ($viewsTables.length && tableSelectAll.length) {
          Drupal.viewsBulkOperationsSelection.bindEventHandlers(tableSelectAll[index], index);
        }
      });
    }

    // Initialize all selector if the primary select all and
    // view table elements exist.
    if ($primarySelectAll.length) {
      $primarySelectAll.on('change', function (event) {
        var value = this.checked;

        // Select / deselect all checkboxes in the view.
        // If there are table select all elements, use that.
        if (tableSelectAll.length) {
          tableSelectAll.forEach(function (element) {
            if (element.get(0).checked != value) {
              element.click();
            }
          });
        }

        // Also handle checkboxes that may still have different values.
        $vboForm.find('.views-field-views-bulk-operations-bulk-form input[type="checkbox"]').each(function () {
          if (this.checked != value) {
            $(this).click();
          }
        });

        // Clear the selection information if exists.
        $vboForm.find('.vbo-info-list-wrapper').each(function () {
          $(this).html('');
        });
      });

      if ($multiSelectElement.length) {
        Drupal.viewsBulkOperationsSelection.bindEventHandlers($primarySelectAll, 'selection_method_change');
      }
    }
    Drupal.viewsBulkOperationsSelection.bindCheckboxes();
    Drupal.viewsBulkOperationsSelection.bindActionSelect();
    Drupal.viewsBulkOperationsSelection.toggleButtonsState();
  };

})(jQuery, Drupal);
