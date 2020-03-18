/**
 * @file
 * JavaScript functionality for the private message notification block.
 */

/*global jQuery, Drupal, drupalSettings, window*/
/*jslint white:true, this, browser:true*/

(function ($, Drupal, drupalSettings, window) {

  "use strict";

  var initialized, notificationWrapper, refreshRate, checkingCount;

  /**
   * Trigger Ajax Commands.
   */
  function triggerCommands(data) {
    var ajaxObject = Drupal.ajax({
      url: "",
      base: false,
      element: false,
      progress: false
    });

    // Trigger any any ajax commands in the response.
    ajaxObject.success(data, "success");
  }

  function updateCount(unreadThreadCount) {
    if (unreadThreadCount) {
      notificationWrapper.addClass("unread-threads");
    }
    else {
      notificationWrapper.removeClass("unread-threads");
    }

    notificationWrapper.find(".private-message-page-link").text(unreadThreadCount);
  }

  /**
   * Retrieve the new unread thread count from the server using AJAX.
   */
  function checkCount() {
    if (!checkingCount) {
      checkingCount = true;

      $.ajax({
        url:drupalSettings.privateMessageNotificationBlock.newMessageCountCallback,
        success:function (data) {
          triggerCommands(data);

          checkingCount = false;
          window.setTimeout(checkCount, refreshRate);
        }
      });
    }
  }

  /**
   * Initializes the script.
   */
  function init() {
    if (!initialized) {
      initialized = true;

      if (drupalSettings.privateMessageNotificationBlock.ajaxRefreshRate) {
        notificationWrapper = $(".private-message-notification-wrapper");
        refreshRate = drupalSettings.privateMessageNotificationBlock.ajaxRefreshRate * 1000;
        if (refreshRate) {
          window.setTimeout(checkCount, refreshRate);
        }
      }
    }
  }

  Drupal.behaviors.privateMessageNotificationBlock = {
    attach:function () {

      init();

      Drupal.AjaxCommands.prototype.privateMessageUpdateUnreadThreadCount = function (ajax, response) {
        // Stifles jSlint warning.
        ajax = ajax;

        updateCount(response.unreadThreadCount);
      };
    }
  };
}(jQuery, Drupal, drupalSettings, window));
