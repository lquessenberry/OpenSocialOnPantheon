/**
 * @file
 * The Lazy-load behavior.
 */

(function (Drupal) {

  'use strict';

  Drupal.behaviors.lazy = {
    attach: function (context, settings) {
      var utils = {
        extend: function (obj, src) {
          Object.keys(src).forEach(function (key) {
            obj[key] = src[key];
          });
          return obj;
        },
        once: function (selector, context) {
          return (context || document).querySelector(selector);
        },
        loadScript: function (url) {
          if (document.querySelectorAll('script[src="' + url + '"]').length == 0) {
            var script = document.createElement('script'),
              scripts = document.getElementsByTagName('script')[0];
            script.src = url;
            script.async = true;
            scripts.parentNode.insertBefore(script, scripts);
          }
        }
      };

      if (utils.once('body', context)) {
        var lazysizes = settings.lazy.lazysizes || {};

        if (!settings.lazy.preferNative) {
          // 1. Lazysizes configuration.
          window.lazySizesConfig = window.lazySizesConfig || {};
          window.lazySizesConfig = utils.extend(window.lazySizesConfig, lazysizes);
          // 2. Load all selected lazysizes plugins.
          if (!Object.entries) {
            Object.entries = function (obj) {
              var ownProps = Object.keys(obj),
                i = ownProps.length,
                resArray = new Array(i);
              while (i--) {
                resArray[i] = [ownProps[i], obj[ownProps[i]]];
              }
              return resArray;
            };
          }
          var min = settings.lazy.minified ? '.min' : '';
          Object.entries(lazysizes.plugins).forEach(function (path) {
            utils.loadScript(settings.lazy.libraryPath + '/plugins/' + path[1] + min + '.js');
          });
          // 3. Load the lazysizes library.
          utils.loadScript(settings.lazy.libraryPath + '/lazysizes' + min + '.js');
        }
      }
    }
  };

})(Drupal);
