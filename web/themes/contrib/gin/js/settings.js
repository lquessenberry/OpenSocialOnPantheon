/* eslint-disable func-names, no-mutable-exports, comma-dangle, strict */

'use strict';

(($, Drupal, drupalSettings) => {
  Drupal.behaviors.ginSettings = {
    attach: function attach(context) {
      // Watch Darkmode setting has changed.
      $('input[name="enable_darkmode"]', context).change(function () {
        const darkmode = $(this).val();
        const accentColorPreset = $('[data-drupal-selector="edit-preset-accent-color"] input:checked').val();
        const focusColorPreset = $('select[name="preset_focus_color"]').val();

        // Toggle Darkmode.
        Drupal.behaviors.ginSettings.darkmode(darkmode);

        // Set custom color if 'custom' is set.
        if (accentColorPreset === 'custom') {
          const accentColorSetting = $('input[name="accent_color"]', context).val();

          Drupal.behaviors.ginAccent.setCustomAccentColor(accentColorSetting);
        } else {
          Drupal.behaviors.ginAccent.setAccentColor(accentColorPreset);
        }

        // Toggle Focus color.
        if (focusColorPreset === 'custom') {
          const focusColorSetting = $('input[name="focus_color"]', context).val();

          Drupal.behaviors.ginAccent.setCustomFocusColor(focusColorSetting);
        } else {
          Drupal.behaviors.ginAccent.setFocusColor(focusColorPreset);
        }
      });

      // Watch Accent color setting has changed.
      $('[data-drupal-selector="edit-preset-accent-color"] input', context).change(function () {
        const accentColorPreset = $(this).val();

        // Update.
        Drupal.behaviors.ginAccent.clearAccentColor();
        Drupal.behaviors.ginAccent.setAccentColor(accentColorPreset);

        // Set custom color if 'custom' is set.
        if (accentColorPreset === 'custom') {
          const accentColorSetting = $('input[name="accent_color"]').val();

          Drupal.behaviors.ginAccent.setCustomAccentColor(accentColorSetting);
        }
      });

      // Watch Accent color picker has changed.
      $('input[name="accent_picker"]', context).change(function () {
        const accentColorSetting = $(this).val();

        // Sync fields.
        $('input[name="accent_color"]', context).val(accentColorSetting);

        // Update.
        Drupal.behaviors.ginAccent.setCustomAccentColor(accentColorSetting);
      });

      // Watch Accent color setting has changed.
      $('input[name="accent_color"]', context).change(function () {
        const accentColorSetting = $(this).val();

        // Sync fields.
        $('input[name="accent_picker"]', context).val(accentColorSetting);

        // Update.
        Drupal.behaviors.ginAccent.setCustomAccentColor(accentColorSetting);
      });

      // Watch Focus color setting has changed.
      $('select[name="preset_focus_color"]', context).change(function () {
        const focusColorPreset = $(this).val();

        // Update.
        Drupal.behaviors.ginAccent.clearFocusColor();
        Drupal.behaviors.ginAccent.setFocusColor(focusColorPreset);

        // Set custom color if 'custom' is set.
        if (focusColorPreset === 'custom') {
          const focusColorSetting = $('input[name="focus_color"]').val();

          Drupal.behaviors.ginAccent.setCustomFocusColor(focusColorSetting);
        }
      });

      // Watch Focus color picker has changed.
      $('input[name="focus_picker"]', context).change(function () {
        const focusColorSetting = $(this).val();

        // Sync fields.
        $('input[name="focus_color"]', context).val(focusColorSetting);

        // Update.
        Drupal.behaviors.ginAccent.setCustomFocusColor(focusColorSetting);
      });

      // Watch Accent color setting has changed.
      $('input[name="focus_color"]', context).change(function () {
        const focusColorSetting = $(this).val();

        // Sync fields.
        $('input[name="focus_picker"]', context).val(focusColorSetting);

        // Update.
        Drupal.behaviors.ginAccent.setCustomFocusColor(focusColorSetting);
      });

      // Watch Hight contrast mode setting has changed.
      $('input[name="high_contrast_mode"]', context).change(function () {
        const highContrastMode = $(this).is(':checked');

        // Update.
        Drupal.behaviors.ginSettings.setHighContrastMode(highContrastMode);
      });

      // Watch user settings has changed.
      $('input[name="enable_user_settings"]', context).change(function () {
        const active = $(this).is(':checked');

        let darkmodeSetting = $('input[name="enable_darkmode"]:checked').val();
        let accentColorSetting = $('input[name="accent_color"]', context).val();
        let accentColorPreset = $('[data-drupal-selector="edit-preset-accent-color"] input:checked').val();
        let highContrastMode = $('input[name="high_contrast_mode"]').is(':checked');

        // User setting disabled, use default settings instead.
        if (!active) {
          darkmodeSetting = drupalSettings.gin.default_darkmode;
          accentColorSetting = drupalSettings.gin.default_accent_color;
          accentColorPreset = drupalSettings.gin.default_preset_accent_color;
          highContrastMode = drupalSettings.gin.default_high_contrast_mode;
        }

        // Update.
        Drupal.behaviors.ginSettings.darkmode(darkmodeSetting);
        Drupal.behaviors.ginAccent.setAccentColor(accentColorPreset, accentColorSetting);
        Drupal.behaviors.ginSettings.setHighContrastMode(highContrastMode);
      });

      // Watch save
      $('[data-drupal-selector="edit-submit"]', context).click(function () {
        // Reset darkmode localStorage.
        localStorage.setItem('Drupal.gin.darkmode', '');
      });
    },

    darkmode: function darkmode(darkmodeParam = null) {
      const darkmodeEnabled = darkmodeParam != null ? darkmodeParam : drupalSettings.gin.darkmode;
      const darkmodeClass = drupalSettings.gin.darkmode_class;

      if (
        darkmodeEnabled == 1 ||
        (darkmodeEnabled === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches)
      ) {
        $('html').addClass(darkmodeClass);
      }
      else {
        $('html').removeClass(darkmodeClass);
      }

      // Reset localStorage.
      localStorage.setItem('Drupal.gin.darkmode', '');

      // Change to Darkmode.
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (e.matches && $('input[name="enable_darkmode"]:checked').val() === 'auto') {
          $('html').addClass(darkmodeClass);
        }
      });

      // Change to Lightmode.
      window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', (e) => {
        if (e.matches && $('input[name="enable_darkmode"]:checked').val() === 'auto') {
          $('html').removeClass(darkmodeClass);
        }
      });
    },

    setHighContrastMode: function setHighContrastMode(param = null) {
      const enabled = param != null ? param : drupalSettings.gin.highcontrastmode;
      const className = drupalSettings.gin.highcontrastmode_class;

      // Needs to check for both: backwards compatibility.
      if (enabled === true || enabled === 1) {
        $('body').addClass(className);
      }
      else {
        $('body').removeClass(className);
      }
    },
  };
})(jQuery, Drupal, drupalSettings);
