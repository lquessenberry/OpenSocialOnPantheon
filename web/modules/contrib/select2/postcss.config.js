const postcssAutoPrefixer = require('autoprefixer');
const postcssCustomProperties = require('postcss-custom-properties');
const postcssCalc = require('postcss-calc');
const postcssDiscardComments = require('postcss-discard-comments');

module.exports = {
  plugins: [
    postcssCustomProperties({
      preserve: false,
      importFrom: '../../../core/themes/claro/css/base/variables.pcss.css'
    }),
    postcssCalc,
    postcssDiscardComments(),
    postcssAutoPrefixer
  ]
}

