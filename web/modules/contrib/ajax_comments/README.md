CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Help and assistance
 * Configuration
 * Troubleshooting
 * Maintainers


INTRODUCTION
------------

Provides ajax comments to Drupal sites (commenting like a social networking
sites: Facebook, Google+, vk.com etc).

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/ajax_comments

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/ajax_comments


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install the AJAX Comments module as you would normally install a contributed
   Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.


HELP AND ASSISTANCE
-------------------

Help is available in the issue queue. If you are asking for help, please
provide the following information with your request:

 * The comment settings for your node type
 * The ajax comments settings
 * The browsers you are testing with and version (Firefox 3.6.10, IE8, etc.)
 * Any relevant information from the Firebug console or a similar Javascript
   debugger
 * Screenshots or screencast videos showing your errors are helpful
 * If you are using the default textareas or any 3rd party Javascript editors
   like CKeditor, etc.
 * Any additional details that the module authors can use to reproduce this
   with a default installation of Drupal with the Garland theme.


CONFIGURATION
-------------

  1. Navigate to `Administration > Extend` and enable the module.
  2. Navigate to `Administration > Configuration > Content Authoring >
     AJAX comments` for configuration.


TROUBLESHOOTING
---------------

 * If you have themed your comment output, make sure that everything is
   wrapped to the ".comment" class in your "comment.tpl.php".
 * IMPORTANT: If you have the "Comment Notify" module installed, please also
   install http://drupal.org/project/queue_mail to prevent server errors
   during comment submitting.
 * The module may conflict with Devel. It has been causing lags when a
   comment is submitting.
 * Try testing with Javascript Optimization disabled and see if it makes a
   difference. (/admin/settings/performance)
 * If you are using the FCKEditor with the wysiwyg module, you should
   upgrade to FCKEditor 2.x or higher. Anything less than 2.x will not work
   properly with the module.
 * If you have having issues, first try the module with a clean Drupal
   install with the default theme. As this is Javascript, it relies upon
   certain assumptions in the theme files. For example, there have been
   reports of issues where custom themes remove <h2> or <h3> tags which
   cause the module to become inoperable.
 * If you are using a 3rd party editor such as Wysiwyg or CKeditor, try
   disabling them and first troubleshooting with just the textareas. That
   will help to narrow down any issues related to the editor.


MAINTAINERS
-----------

 * Alexander Shvets (neochief) - https://www.drupal.org/u/neochief
 * Dan Muzyka (danmuzyka) - https://www.drupal.org/u/danmuzyka
 * Andrew Belousoff (formatC'vt) - https://www.drupal.org/u/formatcvt
 * Volkan Flörchinger (muschpusch) - https://www.drupal.org/u/muschpusch
 * acouch - https://www.drupal.org/u/acouch
 * Anton Kuzmenko (qzmenko) - https://www.drupal.org/u/qzmenko
