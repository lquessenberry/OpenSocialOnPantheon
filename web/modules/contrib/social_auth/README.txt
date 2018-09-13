INTRODUCTION
------------

This project is part of the Drupal Social Initiative
(https://groups.drupal.org/social-initiative).

Social Auth is part of the Social API. It provides a common interface for
creating modules related to user registration/login through social networks'
accounts.

 * This module defines a path /admin/config/social-api/social-auth which
   displays a table of implementers (modules to register/login through social
   networks' accounts).

 * It also provides a block Social Auth Login which contains links to login
   users through the enabled social networks' module clients

 * Alternatively, site builders can place (and theme) a link to 
   user/login/{social_network} wherever on the site. This path are added by the
   implementers. For instance Social Auth Facebook will add the path
   user/login/facebook

REQUIREMENTS
------------

This module requires the following modules:

 * Social API (https://www.drupal.org/project/social_api)

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. See:
   https://www.drupal.org/node/1897420 for further information.

CONFIGURATION
-------------

 * A table of implementers will be displayed at Administration » Configuration »
   Social API Settings » User authentication. However, it will be empty as we 
   have not enabled an implementer yet.

 * You should install implementer modules to get this module start working.

 * You can place a Social Auth Login block at Administration » Structure »
   Block layout.

 * You can find a more comprehensive guide in the Social Auth documentation
   (https://www.drupal.org/node/2763731)

MAINTAINERS
-----------

Current maintainers:
 * gvso - https://www.drupal.org/u/gvso

Supporting organizations:

 * Google Summer of Code (https://www.drupal.org/google-summer-of-code-0)
