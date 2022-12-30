(($, Drupal, drupalSettings) => {
  Drupal.behaviors.ginCKEditor = {
    attach: function(context) {
      if (window.CKEDITOR && void 0 !== CKEDITOR) {
        if (drupalSettings.path.currentPath.indexOf("admin/config/content/formats/manage") > -1) return;
        const variablesCss = drupalSettings.gin.variables_css_path, accentCss = drupalSettings.gin.accent_css_path, contentsCss = drupalSettings.gin.ckeditor_css_path, accentColorPreset = drupalSettings.gin.preset_accent_color, accentColor = drupalSettings.gin.accent_color, darkmodeClass = drupalSettings.gin.darkmode_class;
        (1 == localStorage.getItem("Drupal.gin.darkmode") || "auto" === localStorage.getItem("Drupal.gin.darkmode") && window.matchMedia("(prefers-color-scheme: dark)").matches) && (CKEDITOR.config.bodyClass = darkmodeClass), 
        void 0 === CKEDITOR.config.contentsCss && CKEDITOR.config.contentsCss.push(variablesCss, accentCss, contentsCss), 
        void 0 === CKEDITOR.config.contextmenu_contentsCss && (CKEDITOR.config.contextmenu_contentsCss = new Array, 
        void 0 === CKEDITOR.skinName && (CKEDITOR.skinName = CKEDITOR.skin.name), CKEDITOR.config.contextmenu_contentsCss.push(CKEDITOR.skin.getPath("editor"), variablesCss, accentCss, contentsCss)), 
        $(CKEDITOR.instances, context).once("gin_ckeditor").each((function(index, value) {
          CKEDITOR.on("instanceReady", (function() {
            Object.entries(value).forEach((_ref => {
              let [key, editor] = _ref;
              $(editor.document.$).find("body").attr("data-gin-accent", accentColorPreset), "custom" === accentColorPreset && accentColor && Drupal.behaviors.ginAccent.setCustomAccentColor(accentColor, $(editor.document.$).find("head")), 
              editor.on("mode", (function() {
                "wysiwyg" == this.mode && ($(editor.document.$).find("body").attr("data-gin-accent", accentColorPreset), 
                "custom" === accentColorPreset && accentColor && Drupal.behaviors.ginAccent.setCustomAccentColor(accentColor, $(editor.document.$).find("head")), 
                "auto" === localStorage.getItem("Drupal.gin.darkmode") && (window.matchMedia("(prefers-color-scheme: dark)").matches ? $(editor.document.$).find("body").addClass(darkmodeClass) : $(editor.document.$).find("body").removeClass(darkmodeClass)));
              })), editor.on("menuShow", (function() {
                const darkModeClass = 1 == localStorage.getItem("Drupal.gin.darkmode") || "auto" === localStorage.getItem("Drupal.gin.darkmode") && window.matchMedia("(prefers-color-scheme: dark)").matches ? darkmodeClass : "";
                $("body > .cke_menu_panel > iframe").contents().find("body").addClass(darkModeClass).attr("data-gin-accent", accentColorPreset), 
                "custom" === accentColorPreset && accentColor && Drupal.behaviors.ginAccent.setCustomAccentColor(accentColor, $("body > .cke_menu_panel > iframe").contents().find("head"));
              })), window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", (e => {
                e.matches && "auto" === localStorage.getItem("Drupal.gin.darkmode") && ($(editor.document.$).find("body").addClass(darkmodeClass), 
                $("body > .cke_menu_panel > iframe").contents().find("body").addClass(darkmodeClass));
              })), window.matchMedia("(prefers-color-scheme: light)").addEventListener("change", (e => {
                e.matches && "auto" === localStorage.getItem("Drupal.gin.darkmode") && ($(editor.document.$).find("body").removeClass(darkmodeClass), 
                $("body > .cke_menu_panel > iframe").contents().find("body").removeClass(darkmodeClass));
              }));
            }));
          }));
        }));
      }
    }
  };
})(jQuery, Drupal, drupalSettings);