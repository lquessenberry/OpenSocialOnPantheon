/* eslint-disable no-bitwise, no-nested-ternary, no-mutable-exports, comma-dangle, strict */

'use strict';

(($, Drupal, drupalSettings) => {
  Drupal.behaviors.ginSticky = {
    attach: function attach() {
      if (document.querySelectorAll('.region-sticky').length > 0) {
        // Watch sticky header
        const observer = new IntersectionObserver(
          ([e]) => document.querySelector('.region-sticky').classList.toggle('region-sticky--is-sticky', e.intersectionRatio < 1),
          { threshold: [1] }
        );

        if (document.querySelectorAll('.region-sticky-watcher').length > 0) {
          observer.observe(document.querySelector('.region-sticky-watcher'));
        }
      }
    }
  };

  Drupal.behaviors.ginAccent = {
    attach: function attach() {
      // Check Darkmode.
      Drupal.behaviors.ginAccent.checkDarkmode();

      // Set accent color.
      Drupal.behaviors.ginAccent.setAccentColor();

      // Set focus color.
      Drupal.behaviors.ginAccent.setFocusColor();
    },

    setAccentColor: function setAccentColor(preset = null, color = null) {
      const accentColorPreset = preset != null ? preset : drupalSettings.gin.preset_accent_color;
      $('body').attr('data-gin-accent', accentColorPreset);

      if (accentColorPreset === 'custom') {
        Drupal.behaviors.ginAccent.setCustomAccentColor(color);
      }
    },

    setCustomAccentColor: function setCustomAccentColor(color = null, $element = $('body')) {
      // If custom color is set, generate colors through JS.
      const accentColor = color != null ? color : drupalSettings.gin.accent_color;
      if (accentColor) {
        Drupal.behaviors.ginAccent.clearAccentColor($element);

        const strippedAccentColor = accentColor.replace('#', '');
        const darkAccentColor = Drupal.behaviors.ginAccent.mixColor('ffffff', strippedAccentColor, 65).replace('#', '');
        const styles = `<style class="gin-custom-colors">\
          [data-gin-accent="custom"] {\n\
            --colorGinPrimaryRGB: ${Drupal.behaviors.ginAccent.hexToRgb(accentColor)};\n\
            --colorGinPrimaryHover: ${Drupal.behaviors.ginAccent.shadeColor(accentColor, -10)};\n\
            --colorGinPrimaryActive: ${Drupal.behaviors.ginAccent.shadeColor(accentColor, -15)};\n\
            --colorGinAppBackgroundRGB: ${Drupal.behaviors.ginAccent.hexToRgb(Drupal.behaviors.ginAccent.mixColor('ffffff', strippedAccentColor, 97))};\n\
            --colorGinTableHeader: ${Drupal.behaviors.ginAccent.mixColor('ffffff', strippedAccentColor, 85)};\n\
          }\n\
          .gin--dark-mode[data-gin-accent="custom"],\n\
          .gin--dark-mode [data-gin-accent="custom"] {\n\
            --colorGinPrimaryRGB: ${Drupal.behaviors.ginAccent.hexToRgb(darkAccentColor)};\n\
            --colorGinPrimaryHover: ${Drupal.behaviors.ginAccent.mixColor('ffffff', strippedAccentColor, 55)};\n\
            --colorGinPrimaryActive: ${Drupal.behaviors.ginAccent.mixColor('ffffff', strippedAccentColor, 50)};\n\
            --colorGinTableHeader: ${Drupal.behaviors.ginAccent.mixColor('2A2A2D', darkAccentColor, 88)};\n\
          }\n\
          </style>`;

        $element.append(styles);
      }
    },

    clearAccentColor: function clearAccentColor($element = $('body')) {
      $element.find('.gin-custom-colors').remove();
    },

    // https://stackoverflow.com/questions/5623838/rgb-to-hex-and-hex-to-rgb
    hexToRgb: function hexToRgb(hex) {
      var shorthandRegex = /^#?([a-f\d])([a-f\d])([a-f\d])$/i;
      hex = hex.replace(shorthandRegex, function(m, r, g, b) {
        return r + r + g + g + b + b;
      });

      var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
      return result ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}` : null;
    },

    setFocusColor: function setFocusColor(preset = null, color = null) {
      const focusColorPreset = preset != null ? preset : drupalSettings.gin.preset_focus_color;
      $('body').attr('data-gin-focus', focusColorPreset);

      if (focusColorPreset === 'custom') {
       Drupal.behaviors.ginAccent.setCustomFocusColor(color);
      }
    },

    setCustomFocusColor: function setCustomFocusColor(color = null) {
      const accentColor = color != null ? color : drupalSettings.gin.focus_color;

      // Set preset color.
      if (accentColor) {
        Drupal.behaviors.ginAccent.clearFocusColor();

        const strippedAccentColor = accentColor.replace('#', '');
        const darkAccentColor = Drupal.behaviors.ginAccent.mixColor('ffffff', strippedAccentColor, 65);
        const styles = `<style class="gin-custom-focus">\
            [data-gin-focus="custom"] {\n\
              --colorGinFocus: ${accentColor};\n\
            }\n\
            .gin--dark-mode[data-gin-focus="custom"],\n\
            .gin--dark-mode [data-gin-focus="custom"] {\n\
              --colorGinFocus: ${darkAccentColor};\n\
            }\n\
            </style>`;

        $('body').append(styles);
      }
    },

    clearFocusColor: function clearFocusColor() {
      $('.gin-custom-focus').remove();
    },

    checkDarkmode: function checkDarkmode() {
      const darkmodeClass = drupalSettings.gin.darkmode_class;

      // Change to Darkmode.
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (e.matches && localStorage.getItem('Drupal.gin.darkmode') === 'auto') {
          $('html').addClass(darkmodeClass);
        }
      });

      // Change to Lightmode.
      window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', (e) => {
        if (e.matches && localStorage.getItem('Drupal.gin.darkmode') === 'auto') {
          $('html').removeClass(darkmodeClass);
        }
      });
    },

    // https://gist.github.com/jedfoster/7939513
    mixColor: function mixColor(color_1, color_2, weight) {
      function d2h(d) { return d.toString(16); }
      function h2d(h) { return parseInt(h, 16); }

      weight = (typeof(weight) !== 'undefined') ? weight : 50;

      var color = "#";

      for (var i = 0; i <= 5; i += 2) {
        var v1 = h2d(color_1.substr(i, 2)),
            v2 = h2d(color_2.substr(i, 2)),
            val = d2h(Math.floor(v2 + (v1 - v2) * (weight / 100.0)));

        while(val.length < 2) { val = '0' + val; }
        color += val;
      }

      return color;
    },

    shadeColor: function shadeColor(color, percent) {
      const num = parseInt(color.replace('#', ''), 16);
      const amt = Math.round(2.55 * percent);
      const R = (num >> 16) + amt;
      const B = ((num >> 8) & 0x00ff) + amt;
      const G = (num & 0x0000ff) + amt;

      return `#${(
        0x1000000
        + (R < 255 ? (R < 1 ? 0 : R) : 255) * 0x10000
        + (B < 255 ? (B < 1 ? 0 : B) : 255) * 0x100
        + (G < 255 ? (G < 1 ? 0 : G) : 255)
      )
        .toString(16)
        .slice(1)}`;
    },
  };
})(jQuery, Drupal, drupalSettings);
