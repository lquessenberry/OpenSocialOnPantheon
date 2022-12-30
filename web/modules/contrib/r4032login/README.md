CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

Redirect the HTTP 403 error page to the Drupal /user/login page with a message
that reads, "Access denied. You must login to view this page." Also, a redirect
to the desired page is appended in the url query string so that, once login is
successful, the user is taken directly where they were originally trying to go.

Makes for a much more user-friendly Drupal.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/r4032login

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/r4032login


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install the Redirect 403 to User Login module as you would normally install a
   contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module.
    2. Navigate to Administration > Basic site settings to configure.
    3. Save configuration.


MAINTAINERS
-----------

 * Brent Dunn - https://www.drupal.org/u/bdone
