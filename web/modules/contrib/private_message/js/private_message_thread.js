/**
 * @file
 * Adds JavaScript functionality to priveate message threads.
 */

/*global jQuery, Drupal, drupalSettings, window*/
/*jslint white:true, this, browser:true*/

Drupal.PrivateMessages = {};

(function ($, Drupal, drupalSettings, window) {

  "use strict";

  var initialized, threadWrapper, currentThreadId, originalThreadId, loadingPrev, loadingNew, container, timeout, refreshRate, dimmer, loadingThread;

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

  function showDimmer(callback) {
    if (!dimmer) {
      dimmer = $("<div/>", {id:"private-message-thread-dimmer"}).appendTo(threadWrapper);
    }

    dimmer.fadeTo(500, 0.8, callback);
  }

  function hideDimmer() {
    if (dimmer) {
      dimmer.fadeOut(500);
    }
  }

  var loadPreviousListenerHandler = function (e) {
    e.preventDefault();

    if (!loadingPrev) {
      loadingPrev = true;

      var threadId, oldestId;

      threadId = threadWrapper.children(".private-message-thread:first").attr("data-thread-id");

      container.find(".private-message").each(function () {
        if (!oldestId || Number($(this).attr("data-message-id")) < oldestId) {
          oldestId = Number($(this).attr("data-message-id"));
        }
      });

      $.ajax({
        url:drupalSettings.privateMessageThread.previousMessageCheckUrl,
        data: {threadid:threadId, messageid:oldestId},
        success:function (data) {
          loadingPrev = false;
          triggerCommands(data);
        }
      });
    }
  };

  function loadPreviousListener(context) {
    $(context).find("#load-previous-messages").once("load-previous-private-messages-listener").each(function () {
      $(this).click(loadPreviousListenerHandler);
    });
  }

  function insertNewMessages(messages) {
    var html = $("<div/>").html(messages).contents().css("display", "none");

    if (drupalSettings.privateMessageThread.messageOrder === "asc") {
      html.appendTo(container);
    }
    else {
      html.prependTo(container);
    }

    html.slideDown(300);

    Drupal.attachBehaviors(html[0]);
  }

  function insertPreviousMessages(messages) {
    var html = $("<div/>").html(messages).contents().css("display", "none");

    if (drupalSettings.privateMessageThread.messageOrder === "asc") {
      html.prependTo(container);
    }
    else {
      html.appendTo(container);
    }

    html.slideDown(300);

    Drupal.attachBehaviors(html[0]);
  }

  function getNewMessages() {
    if (!loadingNew) {
      var threadId, newestId = 0;

      loadingNew = true;

      threadId = threadWrapper.children(".private-message-thread:first").attr("data-thread-id");

      container.find(".private-message").each(function () {
        if (Number($(this).attr("data-message-id")) > newestId) {
          newestId = Number($(this).attr("data-message-id"));
        }
      });

      if (refreshRate) {
        $.ajax({
          url:drupalSettings.privateMessageThread.newMessageCheckUrl,
          data: {threadid:threadId, messageid:newestId},
          success:function (data) {
            triggerCommands(data);

            loadingNew = false;

             // Check for new messages again.
            timeout = window.setTimeout(getNewMessages, refreshRate);
          }
        });
      }
    }
  }

  function insertThread(thread) {
    var newThread, originalThread;

    newThread = $("<div/>").html(thread).find(".private-message-thread:first");
    originalThread = threadWrapper.children(".private-message-thread:first");
    Drupal.detachBehaviors(threadWrapper[0]);
    newThread.insertAfter(originalThread);
    originalThread.remove();

    Drupal.attachBehaviors(threadWrapper[0]);

    hideDimmer();
  }

  function loadThread(threadId, pushHistory) {
    if (!loadingThread && threadId !== currentThreadId) {
      loadingThread = true;

      window.clearTimeout(timeout);

      showDimmer();

      $.ajax({
        url:drupalSettings.privateMessageThread.loadThreadUrl,
        data:{id:threadId},
        success:function (data) {
          triggerCommands(data);

          if (Drupal.PrivateMessages.setActiveThread) {
            Drupal.PrivateMessages.setActiveThread(threadId);
          }

          loadingThread = false;

          timeout = window.setTimeout(getNewMessages, refreshRate);
        }
      });

      if (pushHistory) {
        Drupal.history.push({threadId:threadId}, $("title").text(), "/private_messages/" + threadId);
      }
    }
  }

  function init() {
    var loadPreviousButton;

    if (!initialized) {
      initialized = true;

      threadWrapper = $(".private-message-thread").parent();
      refreshRate = drupalSettings.privateMessageThread.refreshRate;
      container = threadWrapper.find(".private-message-thread-messages:first .private-message-wrapper:first").parent();

      loadPreviousButton = $("<div/>", {id:"load-previous-messages-button-wrapper"}).append($("<a/>", {href:"#", id:"load-previous-messages"}).text(Drupal.t("Load Previous")));

      if (drupalSettings.privateMessageThread.messageOrder === "asc") {
        loadPreviousButton.addClass("load-previous-position-before").insertBefore(container);
      }
      else {
        loadPreviousButton.addClass("load-previous-position-after").insertAfter(container);
      }

      originalThreadId = threadWrapper.children(".private-message-thread:first").attr("data-thread-id");

      if (refreshRate) {
        timeout = window.setTimeout(getNewMessages, refreshRate);
      }

      if (Drupal.PrivateMessages.setActiveThread) {
        Drupal.PrivateMessages.setActiveThread(originalThreadId);
      }
    }
  }

  Drupal.behaviors.privateMessageThread = {
    attach:function (context) {
      init();
      loadPreviousListener(context);
      currentThreadId = threadWrapper.children(".private-message-thread:first").attr("data-thread-id");
      container = threadWrapper.find(".private-message-thread-messages:first .private-message-wrapper:first").parent();

      Drupal.AjaxCommands.prototype.insertPrivateMessages = function (ajax, response) {
        // Stifles jSlint warning.
        ajax = ajax;

        if (response.insertType === "new") {
          insertNewMessages(response.messages);
        }
        else {
          if (response.messages) {
            insertPreviousMessages(response.messages);
          }
          else {
            $("#load-previous-messages").parent().slideUp(300, function () {
              $(this).remove();
            });
          }
        }
      };

      Drupal.AjaxCommands.prototype.loadNewPrivateMessages = function () {

        window.clearTimeout(timeout);

        getNewMessages();
      };

      Drupal.AjaxCommands.prototype.privateMessageInsertThread = function (ajax, response) {
        // Stifle jslint warning.
        ajax = ajax;

        if (response.thread && response.thread.length) {
          insertThread(response.thread);
        }
      };

      Drupal.PrivateMessages.loadThread = function (threadId) {
        loadThread(threadId, true);
      };
    },
    detach:function (context) {
      $(context).find("#load-previous-messages").unbind("click", loadPreviousListenerHandler);
    }
  };

  window.onpopstate = function (e) {
    if (e.state&& e.state.threadId) {
      loadThread(e.state.threadId);
    }
    else {
      loadThread(originalThreadId);
    }
  };

}(jQuery, Drupal, drupalSettings, window));
