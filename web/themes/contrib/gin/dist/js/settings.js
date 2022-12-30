(($, Drupal, drupalSettings) => {
  Drupal.behaviors.ginSettings = {
    attach: function(context) {
      $('input[name="enable_darkmode"]', context).change((function() {
        const darkmode = $(this).val(), accentColorPreset = $('[data-drupal-selector="edit-preset-accent-color"] input:checked').val(), focusColorPreset = $('select[name="preset_focus_color"]').val();
        if (Drupal.behaviors.ginSettings.darkmode(darkmode), "custom" === accentColorPreset) {
          const accentColorSetting = $('input[name="accent_color"]', context).val();
          Drupal.behaviors.ginAccent.setCustomAccentColor(accentColorSetting);
        } else Drupal.behaviors.ginAccent.setAccentColor(accentColorPreset);
        if ("custom" === focusColorPreset) {
          const focusColorSetting = $('input[name="focus_color"]', context).val();
          Drupal.behaviors.ginAccent.setCustomFocusColor(focusColorSetting);
        } else Drupal.behaviors.ginAccent.setFocusColor(focusColorPreset);
      })), $('[data-drupal-selector="edit-preset-accent-color"] input', context).change((function() {
        const accentColorPreset = $(this).val();
        if (Drupal.behaviors.ginAccent.clearAccentColor(), Drupal.behaviors.ginAccent.setAccentColor(accentColorPreset), 
        "custom" === accentColorPreset) {
          const accentColorSetting = $('input[name="accent_color"]').val();
          Drupal.behaviors.ginAccent.setCustomAccentColor(accentColorSetting);
        }
      })), $('input[name="accent_picker"]', context).change((function() {
        const accentColorSetting = $(this).val();
        $('input[name="accent_color"]', context).val(accentColorSetting), Drupal.behaviors.ginAccent.setCustomAccentColor(accentColorSetting);
      })), $('input[name="accent_color"]', context).change((function() {
        const accentColorSetting = $(this).val();
        $('input[name="accent_picker"]', context).val(accentColorSetting), Drupal.behaviors.ginAccent.setCustomAccentColor(accentColorSetting);
      })), $('select[name="preset_focus_color"]', context).change((function() {
        const focusColorPreset = $(this).val();
        if (Drupal.behaviors.ginAccent.clearFocusColor(), Drupal.behaviors.ginAccent.setFocusColor(focusColorPreset), 
        "custom" === focusColorPreset) {
          const focusColorSetting = $('input[name="focus_color"]').val();
          Drupal.behaviors.ginAccent.setCustomFocusColor(focusColorSetting);
        }
      })), $('input[name="focus_picker"]', context).change((function() {
        const focusColorSetting = $(this).val();
        $('input[name="focus_color"]', context).val(focusColorSetting), Drupal.behaviors.ginAccent.setCustomFocusColor(focusColorSetting);
      })), $('input[name="focus_color"]', context).change((function() {
        const focusColorSetting = $(this).val();
        $('input[name="focus_picker"]', context).val(focusColorSetting), Drupal.behaviors.ginAccent.setCustomFocusColor(focusColorSetting);
      })), $('input[name="high_contrast_mode"]', context).change((function() {
        const highContrastMode = $(this).is(":checked");
        Drupal.behaviors.ginSettings.setHighContrastMode(highContrastMode);
      })), $('input[name="enable_user_settings"]', context).change((function() {
        const active = $(this).is(":checked");
        let darkmodeSetting = $('input[name="enable_darkmode"]:checked').val(), accentColorSetting = $('input[name="accent_color"]', context).val(), accentColorPreset = $('[data-drupal-selector="edit-preset-accent-color"] input:checked').val(), highContrastMode = $('input[name="high_contrast_mode"]').is(":checked");
        active || (darkmodeSetting = drupalSettings.gin.default_darkmode, accentColorSetting = drupalSettings.gin.default_accent_color, 
        accentColorPreset = drupalSettings.gin.default_preset_accent_color, highContrastMode = drupalSettings.gin.default_high_contrast_mode), 
        Drupal.behaviors.ginSettings.darkmode(darkmodeSetting), Drupal.behaviors.ginAccent.setAccentColor(accentColorPreset, accentColorSetting), 
        Drupal.behaviors.ginSettings.setHighContrastMode(highContrastMode);
      })), $('[data-drupal-selector="edit-submit"]', context).click((function() {
        localStorage.setItem("Drupal.gin.darkmode", "");
      }));
    },
    darkmode: function() {
      let darkmodeParam = arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : null;
      const darkmodeEnabled = null != darkmodeParam ? darkmodeParam : drupalSettings.gin.darkmode, darkmodeClass = drupalSettings.gin.darkmode_class;
      1 == darkmodeEnabled || "auto" === darkmodeEnabled && window.matchMedia("(prefers-color-scheme: dark)").matches ? $("html").addClass(darkmodeClass) : $("html").removeClass(darkmodeClass), 
      localStorage.setItem("Drupal.gin.darkmode", ""), window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", (e => {
        e.matches && "auto" === $('input[name="enable_darkmode"]:checked').val() && $("html").addClass(darkmodeClass);
      })), window.matchMedia("(prefers-color-scheme: light)").addEventListener("change", (e => {
        e.matches && "auto" === $('input[name="enable_darkmode"]:checked').val() && $("html").removeClass(darkmodeClass);
      }));
    },
    setHighContrastMode: function() {
      let param = arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : null;
      const enabled = null != param ? param : drupalSettings.gin.highcontrastmode, className = drupalSettings.gin.highcontrastmode_class;
      !0 === enabled || 1 === enabled ? $("body").addClass(className) : $("body").removeClass(className);
    }
  };
})(jQuery, Drupal, drupalSettings);