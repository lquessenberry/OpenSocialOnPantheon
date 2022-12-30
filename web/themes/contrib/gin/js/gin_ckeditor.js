/* eslint-disable func-names, no-mutable-exports, comma-dangle, strict */

'use strict';

(($, Drupal, drupalSettings) => {
  Drupal.behaviors.ginCKEditor = {
    attach: function attach(context) {
      if (window.CKEDITOR && CKEDITOR !== undefined) {
        // If on CKEditor config, do nothing.
        if (drupalSettings.path.currentPath.indexOf('admin/config/content/formats/manage') > -1) {
          return;
        }

        // Get configs.
        const variablesCss = drupalSettings.gin.variables_css_path;
        const accentCss = drupalSettings.gin.accent_css_path;
        const contentsCss = drupalSettings.gin.ckeditor_css_path;
        const accentColorPreset = drupalSettings.gin.preset_accent_color;
        const accentColor = drupalSettings.gin.accent_color;
        const darkmodeClass = drupalSettings.gin.darkmode_class;

        // Class for Darkmode.
        if (
          localStorage.getItem('Drupal.gin.darkmode') == 1 ||
          localStorage.getItem('Drupal.gin.darkmode') === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches
        ) {
          CKEDITOR.config.bodyClass = darkmodeClass;
        }

        // Content stylesheets.
        if (CKEDITOR.config.contentsCss === undefined) {
          CKEDITOR.config.contentsCss.push(
            variablesCss,
            accentCss,
            contentsCss
          );
        }

        // Contextmenu stylesheets.
        if (CKEDITOR.config.contextmenu_contentsCss === undefined) {
          CKEDITOR.config.contextmenu_contentsCss = new Array();

          // Check if skinName is set.
          if (typeof CKEDITOR.skinName === 'undefined') {
            CKEDITOR.skinName = CKEDITOR.skin.name;
          }

          CKEDITOR.config.contextmenu_contentsCss.push(
            CKEDITOR.skin.getPath('editor'),
            variablesCss,
            accentCss,
            contentsCss
          );
        }

        $(CKEDITOR.instances, context).once('gin_ckeditor').each(function(index, value) {
          CKEDITOR.on('instanceReady', function() {
            Object.entries(value).forEach(([key, editor]) => {
              // Initial accent color.
              $(editor.document.$)
                .find('body')
                .attr('data-gin-accent', accentColorPreset);

              if (accentColorPreset === 'custom' && accentColor) {
                Drupal.behaviors.ginAccent.setCustomAccentColor(accentColor, $(editor.document.$).find('head'));
              }

              // Change from Code to Editor.
              editor.on('mode', function() {
                if (this.mode == 'wysiwyg') {
                  $(editor.document.$)
                    .find('body')
                    .attr('data-gin-accent', accentColorPreset);

                  if (accentColorPreset === 'custom' && accentColor) {
                    Drupal.behaviors.ginAccent.setCustomAccentColor(accentColor, $(editor.document.$).find('head'));
                  }

                  if (localStorage.getItem('Drupal.gin.darkmode') === 'auto') {
                    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                      $(editor.document.$)
                        .find('body')
                        .addClass(darkmodeClass);
                    } else {
                      $(editor.document.$)
                        .find('body')
                        .removeClass(darkmodeClass);
                    }
                  }
                }
              });

              // Contextual menu.
              editor.on('menuShow', function() {
                const darkModeClass = localStorage.getItem('Drupal.gin.darkmode') == 1 || localStorage.getItem('Drupal.gin.darkmode') === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches
                  ? darkmodeClass
                  : '';
                $('body > .cke_menu_panel > iframe')
                  .contents()
                  .find('body')
                  .addClass(darkModeClass)
                  .attr('data-gin-accent', accentColorPreset);

                if (accentColorPreset === 'custom' && accentColor) {
                  Drupal.behaviors.ginAccent.setCustomAccentColor(accentColor, $('body > .cke_menu_panel > iframe').contents().find('head'));
                }
              });

              // Toggle Darkmode.
              window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (e.matches && localStorage.getItem('Drupal.gin.darkmode') === 'auto') {
                  $(editor.document.$)
                    .find('body')
                    .addClass(darkmodeClass);

                  $('body > .cke_menu_panel > iframe')
                    .contents()
                    .find('body')
                    .addClass(darkmodeClass);
                }
              });

              // Change to Lightmode.
              window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', (e) => {
                if (e.matches && localStorage.getItem('Drupal.gin.darkmode') === 'auto') {
                  $(editor.document.$)
                    .find('body')
                    .removeClass(darkmodeClass);

                  $('body > .cke_menu_panel > iframe')
                    .contents()
                    .find('body')
                    .removeClass(darkmodeClass);
                }
              });
            });
          });
        });
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
