
                                   ()
  ┌───────┐                        /\
  │       │                   ()--'  '--()
  │  a:o  │  acolono.com        `.    .'       Shariff Module
  │       │                      / .. \
  └───────┘                     ()'  '()


This module implements the Shariff sharing buttons by heise online:
https://github.com/heiseonline/shariff

Shariff enables website users to share their favorite content without
compromising their privacy.

It consists of two parts: a simple JavaScript client library and an
optional server-side component. The latter fetches the number of likes,
tweets and plus-ones.

The base shariff Drupal module implements the JavaScript library to
display the buttons as a block and a pseudo field.


-- REQUIREMENTS --

* Shariff Library (at least v2.0.1)
  https://github.com/heiseonline/shariff

-- INSTALLATION manually --

1) Download the Drupal shariff module and place it in your modules folder.

2) Download the library from https://github.com/heiseonline/shariff and place
it in the Drupal root libraries folder.
So the JavaScript and the CSS files should be available under
"DRUPAL_ROOT/libraries/shariff/shariff.complete.js",
"DRUPAL_ROOT/libraries/shariff/shariff.min.css" and
"DRUPAL_ROOT/libraries/shariff/shariff.complete.css".

When you use the Complete CSS variant, you also need the fontawesome font files (fa-*), that are included in the
library.

You only need those files and at least v2.0.1 of the library.

-- INSTALLATION using Composer --

Prerequisite: You have defined Drupal.org as Composer repository accordingly:
https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies#drupal-packagist

The Shariff library is not listed on packagist.org (https://github.com/heiseonline/shariff/issues/198),
so manual steps are required in order to install it through this method.

1) First, copy the following snippet into your project's composer.json file so the correct package is downloaded:

"repositories": {
  "shariff-library": {
    "type": "package",
    "package": {
      "name": "heiseonline/shariff",
      "version": "2.0.4",
      "type": "drupal-library",
      "dist": {
        "url": "https://github.com/heiseonline/shariff/releases/download/2.0.4/shariff-2.0.4.zip",
          "type": "zip"
      },
      "require": {
        "composer/installers": "^1.2.0"
      }
    }
  }
}

Probably you want to update the library version to use the latest one.

2) Next, the following snippet must be added into your project's composer.json
file so the javascript library is installed into the correct location:

"extra": {
  "installer-paths": {
    "libraries/{$name}": ["type:drupal-library"]
  }
}

If there are already 'repositories' and/or 'extra' entries in the
composer.json, merge these new entries with the already existing entries.

3) After that, run:

$ composer require heiseonline/shariff
$ composer require drupal/shariff

The first uses the manual entries you made to install the JavaScript library,
the second adds the Drupal module.

Note: the requirement on the library is not in the module's composer.json
because that would cause problems with automated testing.

-- CONFIGURATION --

1) Activate the module.

2) Set your default settings under /admin/config/services/shariff. When you
have Font Awesome already loaded on your site be sure to choose the Minimal
CSS option (so that shariff.min.css without Font Awesome will be loaded).

3) Now you can add the buttons as a block or as a field. Just click on
"Place block" on the block layout overview page.
The field is available under "Manage Display" in your content type settings.
