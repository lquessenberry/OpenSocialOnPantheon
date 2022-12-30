(($, Drupal, drupalSettings) => {
  Drupal.behaviors.ginEditForm = {
    attach: function() {
      const form = document.querySelector(".region-content form"), sticky = $(".gin-sticky").clone(!0, !0), newParent = document.querySelector(".region-sticky__items__inner");
      if (newParent && 0 === newParent.querySelectorAll(".gin-sticky").length) {
        sticky.appendTo($(newParent));
        const actionButtons = newParent.querySelectorAll('button[type="submit"], input[type="submit"]');
        actionButtons.length > 0 && actionButtons.forEach((el => {
          el.setAttribute("form", form.getAttribute("id")), el.setAttribute("id", el.getAttribute("id") + "--gin-edit-form");
        }));
        const statusToggle = document.querySelectorAll('.field--name-status [name="status[value]"]');
        statusToggle.length > 0 && statusToggle.forEach((publishedState => {
          publishedState.addEventListener("click", (event => {
            const value = event.target.checked;
            statusToggle.forEach((publishedState => {
              publishedState.checked = value;
            }));
          }));
        })), setTimeout((() => {
          sticky.addClass("gin-sticky--visible");
        }));
      }
    }
  };
})(jQuery, Drupal, drupalSettings);