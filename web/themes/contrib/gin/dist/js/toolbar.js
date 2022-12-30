(($, Drupal, drupalSettings) => {
  Drupal.behaviors.ginToolbarToggle = {
    attach: function(context) {
      localStorage.getItem("Drupal.toolbar.trayVerticalLocked") && localStorage.removeItem("Drupal.toolbar.trayVerticalLocked"), 
      "true" === localStorage.getItem("Drupal.gin.toolbarExpanded") ? ($("body").attr("data-toolbar-menu", "open"), 
      $(".toolbar-menu__trigger").addClass("is-active")) : ($("body").attr("data-toolbar-menu", ""), 
      $(".toolbar-menu__trigger").removeClass("is-active")), $(".toolbar-menu__trigger", context).on("click", (function(e) {
        e.preventDefault(), $(this).toggleClass("is-active");
        let active = "true";
        $(this).hasClass("is-active") ? $("body").attr("data-toolbar-menu", "open") : ($("body").attr("data-toolbar-menu", ""), 
        active = "false", $(".gin-toolbar-inline-styles").remove()), localStorage.setItem("Drupal.gin.toolbarExpanded", active);
        const event = new CustomEvent("toolbar-toggle", {
          detail: "true" === active
        });
        document.dispatchEvent(event);
      })), $("#gin-toolbar-bar .toolbar-item", context).on("click", (function() {
        $("body").attr("data-toolbar-tray", $(this).data("toolbar-tray")), $(document).ready((() => {
          $(".sticky-header").each((function() {
            $(this).width($(".sticky-table").width());
          }));
        }));
      }));
    }
  };
})(jQuery, Drupal, drupalSettings);