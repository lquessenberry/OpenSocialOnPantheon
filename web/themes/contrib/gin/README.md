CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Troubleshooting
 * Maintainers


INTRODUCTION
------------

The Gin Admin Theme is an extension of the Claro admin theme which includes some
UX changes which are currently out of scope for Claro and some customizations we
always deliver for our clients.

Gin can be used in a headless environment, as it provides a nice login screen
which is missing in the default Drupal admin themes.


REQUIREMENTS
------------

This theme requires Drupal core >= 8.8.0 that includes the Claro theme.


INSTALLATION
------------

 * Install as you would normally install a contributed Drupal theme. Visit
   https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
-------------

 * Navigate to Admin > Appearance
 * Set Claro as the default frontend & admin theme to temporarily install the
   subtheme
 * On the same page, click "Install" under Gin
 * At the bottom of the page, switch the Administration theme to Gin
 * Change back to your old default frontend theme


TROUBLESHOOTING
---------------

- Setup Gin locally that you can compile CSS & JS files.

* `nvm use && npm i`

- Run dev env with watcher and debug output (development process)

* `npm run dev`

- Compile assets (for dev branch)

* `npm run build`


MAINTAINERS
-----------

Current maintainers:

  * Sascha Eggenberger (saschaeggi) - https://www.drupal.org/u/saschaeggi
