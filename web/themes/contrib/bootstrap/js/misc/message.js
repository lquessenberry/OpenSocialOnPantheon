/**
 * @file
 * message.js
 */
(function ($, Drupal) {

  /**
   * Retrieves the classes for a specific message type.
   *
   * @param {String} type
   *   The type of message.
   *
   * @return {String}
   *   The classes to add, space separated.
   */
  Drupal.Message.getMessageTypeClass = function (type) {
    var classes = this.getMessageTypeClasses();
    return 'alert alert-' + (classes[type] || 'success');
  };

  /**
   * Helper function to map Drupal types to Bootstrap classes.
   *
   * @return {Object<String, String>}
   *   A map of classes, keyed by message type.
   */
  Drupal.Message.getMessageTypeClasses = function () {
    return {
      status: 'success',
      error: 'danger',
      warning: 'warning',
      info: 'info',
    };
  };

  /**
   * Retrieves a label for a specific message type.
   *
   * @param {String} type
   *   The type of message.
   *
   * @return {String}
   *   The message type label.
   */
  Drupal.Message.getMessageTypeLabel = function (type) {
    var labels = this.getMessageTypeLabels();
    return labels[type];
  };

  /**
   * @inheritDoc
   */
  Drupal.Message.getMessageTypeLabels = function () {
    return {
      status: Drupal.t('Status message'),
      error: Drupal.t('Error message'),
      warning: Drupal.t('Warning message'),
      info: Drupal.t('Informative message'),
    };
  };

  /**
   * Retrieves the aria-role for a specific message type.
   *
   * @param {String} type
   *   The type of message.
   *
   * @return {String}
   *   The message type role.
   */
  Drupal.Message.getMessageTypeRole = function (type) {
    var labels = this.getMessageTypeRoles();
    return labels[type];
  };

  /**
   * Map of the message type aria-role values.
   *
   * @return {Object<String, String>}
   *   A map of roles, keyed by message type.
   */
  Drupal.Message.getMessageTypeRoles = function () {
    return {
      status: 'status',
      error: 'alert',
      warning: 'alert',
      info: 'status',
    };
  };

  /**
   * @inheritDoc
   */
  Drupal.theme.message = function (message, options) {
    options = options || {};
    var wrapper = Drupal.theme('messageWrapper', options.id || (new Date()).getTime(), options.type || 'status');

    if (options.dismissible === void 0 || !!options.dismissible) {
      wrapper.classList.add('alert-dismissible');
      wrapper.appendChild(Drupal.theme('messageClose'));
    }

    wrapper.appendChild(Drupal.theme('messageContents', message && message.text));

    return wrapper;
  };

  /**
   * Themes the message container.
   *
   * @param {String} id
   *   The message identifier.
   * @param {String} type
   *   The type of message.
   *
   * @return {HTMLElement}
   *   A constructed HTMLElement.
   */
  Drupal.theme.messageWrapper = function (id, type) {
    var wrapper = document.createElement('div');
    var label = Drupal.Message.getMessageTypeLabel(type);
    wrapper.setAttribute('class', Drupal.Message.getMessageTypeClass(type));
    wrapper.setAttribute('role', Drupal.Message.getMessageTypeRole(type));
    wrapper.setAttribute('aria-label', label);
    wrapper.setAttribute('data-drupal-message-id', id);
    wrapper.setAttribute('data-drupal-message-type', type);
    if (label) {
      wrapper.appendChild(Drupal.theme('messageLabel', label));
    }
    return wrapper;
  };

  /**
   * Themes the message close button.
   *
   * @return {HTMLElement}
   *   A constructed HTMLElement.
   */
  Drupal.theme.messageClose = function () {
    var element = document.createElement('button');
    element.setAttribute('class', 'close');
    element.setAttribute('type', 'button');
    element.setAttribute('role', 'button');
    element.setAttribute('data-dismiss', 'alert');
    element.setAttribute('aria-label', Drupal.t('Close'));
    element.innerHTML = '<span aria-hidden="true">&times;</span>';
    return element;
  };

  /**
   * Themes the message container.
   *
   * @param {String} label
   *   The message label.
   *
   * @return {HTMLElement}
   *   A constructed HTMLElement.
   */
  Drupal.theme.messageLabel = function (label) {
    var element = document.createElement('h2');
    element.setAttribute('class', 'sr-only');
    element.innerHTML = label;
    return element;
  };

  /**
   * Themes the message contents.
   *
   * @param {String} html
   *   The message identifier.
   *
   * @return {HTMLElement}
   *   A constructed HTMLElement.
   */
  Drupal.theme.messageContents = function (html) {
    var element = document.createElement('p');
    element.innerHTML = '' + html;
    return element;
  }

})(window.jQuery, window.Drupal);
