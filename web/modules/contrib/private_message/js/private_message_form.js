/**
 * @file
 * Adds JS functionality to the Private Message form.
 */

/*global jQuery, Drupal, drupalSettings, window*/
/*jslint white:true, this, browser:true*/

(function ($, Drupal) {

  "use strict";

  function submitKeyPress(e) {
    var keyCode;

    keyCode = e.keyCode || e.which;
    if (keyCode === 13) {
      $(this).mousedown();
    }
  }

  /**
   * Event handler for the submit button on the private message form.
   */
  function submitButtonListener(context) {
    $(context).find(".private-message-add-form .form-actions .form-submit").once("private-message-form-submit-button-listener").each(function () {
      $(this).keydown(submitKeyPress);
    });
  }

  Drupal.behaviors.privateMessageForm = {
    attach:function (context) {
      submitButtonListener(context);
    },
    detach:function (context) {
      // Remove event handlers when the submit button is removed from the page.
      $(context).find(".private-message-add-form .form-actions .form-submit").unbind("keydown", submitKeyPress);
    }
  };

}(jQuery, Drupal));
