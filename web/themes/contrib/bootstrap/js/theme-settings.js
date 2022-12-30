(function ($, Drupal, Bootstrap) {
  /*global jQuery:false */
  /*global Drupal:false */
  "use strict";

  /**
   * Provide vertical tab summaries for Bootstrap settings.
   */
  Drupal.behaviors.bootstrapSettingSummaries = {
    attach: function (context) {
      var $context = $(context);

      // General.
      $context.find('[data-drupal-selector="edit-general"]').drupalSetSummary(function () {
        var summary = [];
        // Buttons.
        var size = $context.find('select[name="button_size"] :selected');
        if (size.val()) {
          summary.push(Drupal.t('@size Buttons', {
            '@size': size.text()
          }));
        }

        // Images.
        var shape = $context.find('select[name="image_shape"] :selected');
        if (shape.val()) {
          summary.push(Drupal.t('@shape Images', {
            '@shape': shape.text()
          }));
        }
        if ($context.find(':input[name="image_responsive"]').is(':checked')) {
          summary.push(Drupal.t('Responsive Images'));
        }

        // Tables.
        if ($context.find(':input[name="table_responsive"]').is(':checked')) {
          summary.push(Drupal.t('Responsive Tables'));
        }

        return summary.join(', ');

      });

      // Components.
      $context.find('[data-drupal-selector="edit-components"]').drupalSetSummary(function () {
        var summary = [];
        // Breadcrumbs.
        var breadcrumb = parseInt($context.find('select[name="breadcrumb"]').val(), 10);
        if (breadcrumb) {
          summary.push(Drupal.t('Breadcrumbs'));
        }
        // Navbar.
        var navbar = 'Navbar: ' + $context.find('select[name="navbar_position"] :selected').text();
        if ($context.find('input[name="navbar_inverse"]').is(':checked')) {
          navbar += ' (' + Drupal.t('Inverse') + ')';
        }
        summary.push(navbar);
        return summary.join(', ');
      });

      // JavaScript.
      var $jQueryUiBridge = $context.find('input[name="modal_jquery_ui_bridge"]');
      $jQueryUiBridge.once('bs.jquery.ui.dialog.bridge').each(function () {
        $jQueryUiBridge
          .off('change.bs.jquery.ui.dialog.bridge')
          .on('change.bs.jquery.ui.dialog.bridge', function (e) {
            if ($jQueryUiBridge[0].checked) {
              return;
            }

            var disable = false;
            var title = Drupal.t('<p><strong>Warning: Disabling the jQuery UI Dialog bridge may have major consequences.</strong></p>');
            var message = Drupal.t('<p>If you are unfamiliar with how to properly handle Bootstrap modals and jQuery UI dialogs together, it is highly recommended this remains <strong>enabled</strong>.</p> <p>Are you sure you want to disable this?</p>');
            var callback = function () {
              if (!disable) {
                $jQueryUiBridge[0].checked = true;
                $jQueryUiBridge.trigger('change');
              }
            };

            if (!$.fn.dialog) {
              disable = window.confirm(Bootstrap.stripHtml(title + ' ' + message));
              callback();
            }
            else {
              $('<div title="Disable jQuery UI Dialog Bridge?"><div class="alert alert-danger alert-sm">' + title + '</div>' + message + '</div>')
                .appendTo('body')
                .dialog({
                  modal: true,
                  close: callback,
                  buttons: [
                    {
                      text: Drupal.t('Disable'),
                      classes: {
                        'ui-button': 'btn-danger',
                      },
                      click: function () {
                        disable = true;
                        $(this).dialog('close');
                      }
                    },
                    {
                      text: 'Cancel',
                      primary: true,
                      click: function () {
                        $(this).dialog('close');
                      }
                    }
                  ]
                });
            }
          });
      });

      $context.find('[data-drupal-selector="edit-javascript"]').drupalSetSummary(function () {
        var summary = [];
        if ($context.find('input[name="modal_enabled"]').is(':checked')) {
          if ($jQueryUiBridge.is(':checked')) {
            summary.push(Drupal.t('Modals (Bridged)'));
          }
          else {
            summary.push(Drupal.t('Modals'));
          }
        }
        if ($context.find('input[name="popover_enabled"]').is(':checked')) {
          summary.push(Drupal.t('Popovers'));
        }
        if ($context.find('input[name="tooltip_enabled"]').is(':checked')) {
          summary.push(Drupal.t('Tooltips'));
        }
        return summary.join(', ');
      });

      // CDN.
      $context.find('[data-drupal-selector="edit-cdn"]').drupalSetSummary(function () {
        var summary = [];
        var $cdnProvider = $context.find('select[name="cdn_provider"] :selected');
        if ($cdnProvider.length) {
          var provider = $cdnProvider.text();

          var $version = $context.find('select[name="cdn_version"] :selected');
          if ($version.length && $version.val().length) {
            provider += ' - ' + $version.text();
            var $theme = $context.find('select[name="cdn_theme"] :selected');
            if ($theme.length) {
              provider += ' (' + $theme.text() + ')';
            }
          }
          else if ($cdnProvider.val() === 'custom') {
            var $urls = $context.find('textarea[name="cdn_custom"]');
            var urls = ($urls.val() + '').split(/\r\n|\n/).filter(Boolean);
            provider += ' (' + Drupal.formatPlural(urls.length, '1 URL', '@count URLs') + ')';
          }

          summary.push(provider);
        }
        return summary.join(', ');
      });


      // Advanced.
      $context.find('[data-drupal-selector="edit-advanced"]').drupalSetSummary(function () {
        var summary = [];
        var deprecations = [];
        if ($context.find('input[name="include_deprecated"]').is(':checked')) {
          deprecations.push(Drupal.t('Included'));
        }
        deprecations.push($context.find('input[name="suppress_deprecated_warnings"]').is(':checked') ? Drupal.t('Warnings Suppressed') : Drupal.t('Warnings Shown'));
        summary.push(Drupal.t('Deprecations: @value', {
          '@value': deprecations.join(', '),
        }));
        return summary.join(', ');
      });
    }
  };

  /**
   * Provide Bootstrap Bootswatch preview.
   */
  Drupal.behaviors.bootstrapBootswatchPreview = {
    attach: function (context) {
      var $context = $(context);
      var $preview = $context.find('#bootstrap-theme-preview');
      $preview.once('bootstrap-theme-preview').each(function () {
        // Construct the "Bootstrap Theme" preview here since it's not actually
        // a Bootswatch theme, but rather one provided by Bootstrap itself.
        // Unfortunately getbootstrap.com does not have HTTPS enabled, so the
        // preview image cannot be protocol relative.
        // @todo Make protocol relative if/when Bootstrap enables HTTPS.
        $preview.append('<a id="bootstrap-theme-preview-bootstrap_theme" class="bootswatch-preview element-invisible" href="https://getbootstrap.com/docs/3.4/examples/theme/" target="_blank"><img class="img-responsive" src="//getbootstrap.com/docs/3.4/examples/screenshots/theme.jpg" alt="' + Drupal.t('Preview of the Bootstrap theme') + '" /></a>');

        // Retrieve the Bootswatch theme preview images.
        // @todo This should be moved into PHP.
        $.ajax({
          url: 'https://bootswatch.com/api/3.json',
          dataType: 'json',
          success: function (json) {
            var themes = json.themes;
            for (var i = 0, len = themes.length; i < len; i++) {
              $preview.append('<a id="bootstrap-theme-preview-' + themes[i].name.toLowerCase() + '" class="bootswatch-preview element-invisible" href="' + themes[i].preview + '" target="_blank"><img class="img-responsive" src="' + themes[i].thumbnail.replace(/^http:/, 'https:') + '" alt="' + Drupal.t('Preview of the @title Bootswatch theme', { '@title': themes[i].name }) + '" /></a>');
            }
          },
          complete: function () {
            $preview.parent().find('select[name="cdn_theme"]').bind('change', function () {
              $preview.find('.bootswatch-preview').addClass('visually-hidden');
              var theme = $(this).val();
              if (theme && theme.length) {
                $preview.find('#bootstrap-theme-preview-' + theme).removeClass('visually-hidden');
              }
            }).change();
          }
        });
      });
    }
  };

  /**
   * Provide Bootstrap navbar preview.
   */
  Drupal.behaviors.bootstrapContainerPreview = {
    attach: function (context) {
      var $context = $(context);
      var $container = $context.find('#edit-container');
      $container.once('container-preview').each(function () {
        $container.find('[name="fluid_container"]').on('change.bootstrap', function () {
          if ($(this).is(':checked')) {
            $context.find('.container').removeClass('container').addClass('container-fluid');
          }
          else {
            $context.find('.container-fluid').removeClass('container-fluid').addClass('container');
          }
        });
      });
    }
  };

  /**
   * Provide Bootstrap navbar preview.
   */
  Drupal.behaviors.bootstrapNavbarPreview = {
    attach: function (context) {
      var $context = $(context);
      var $preview = $context.find('#edit-navbar');
      $preview.once('navbar').each(function () {
        var $body = $context.find('body');
        var $navbar = $context.find('#navbar.navbar');
        $preview.find('select[name="navbar_position"]').bind('change', function () {
          var $position = $(this).find(':selected').val();
          $navbar.removeClass('navbar-fixed-bottom navbar-fixed-top navbar-static-top container');
          if ($position.length) {
            $navbar.addClass('navbar-'+ $position);
          }
          else {
            $navbar.addClass('container');
          }
          // Apply appropriate classes to body.
          $body.removeClass('navbar-is-fixed-top navbar-is-fixed-bottom navbar-is-static-top');
          switch ($position) {
            case 'fixed-top':
              $body.addClass('navbar-is-fixed-top');
              break;

            case 'fixed-bottom':
              $body.addClass('navbar-is-fixed-bottom');
              break;

            case 'static-top':
              $body.addClass('navbar-is-static-top');
              break;
          }
        });
        $preview.find('input[name="navbar_inverse"]').bind('change', function () {
          $navbar.toggleClass('navbar-inverse navbar-default');
        });
      });
    }
  };

})(jQuery, Drupal, Drupal.bootstrap);
