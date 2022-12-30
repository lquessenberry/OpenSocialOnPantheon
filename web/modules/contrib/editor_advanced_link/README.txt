CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Recommended modules
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

Enhances the link Dialog in CKEditor.
Allows to define the following attributes:
- title
- class
- id
- target
- rel

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/editor_advanced_link

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/editor_advanced_link


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


RECOMMENDED MODULES
-------------------

 * Editor File upload (https://www.drupal.org/project/editor_file):
   Allows to create link to uploaded files in the text editor easily.
 * Linkit (https://www.drupal.org/project/linkit):
   Provides an easy interface for internal and external linking with WYSIWYG
   editors by using an autocomplete field.
 * CKEditor Entity Link (https://www.drupal.org/project/ckeditor_entity_link):
   It is an alternative to Linkit that also provides an easy interface for
   internal linking within the editor.


INSTALLATION
------------

Install the module as you would normally install a contributed Drupal module.
Visit https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
-------------

Install as usual then:

    - go to the "Text formats and editor" admin page (admin/config/content/formats)
    - configure your text format
    - if the "Limit allowed HTML tags and correct faulty HTML" filter is disabled
      you dont have anything to do with this text format
    - else, add the "title", "class", "id", "target" and/or the "rel" attributes to
      the "allowed HTML tags" field (only those whitelisted will show up in the dialog)


MAINTAINERS
-----------

Current maintainers:

 * Edouard Cunibil (DuaelFr) - https://www.drupal.org/u/duaelfr

This project has been sponsored by:
 * Happyculture (paid contribution time) - https://happyculture.coop
