CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Installation
 * Configuration

INTRODUCTION
------------

The Data Policy module helps site owners or administrators with informing their
users about which (personal) data is collected.

Next to providing functionality for informing users it also has the ability to
add a data policy. It can be configured that users are prompted to accept the
latest active data policy.

 * For a full description of the module, visit the project page:
   https://drupal.org/project/data_policy

 * To submit bug reports and feature suggestions, or to track changes:
   https://drupal.org/project/issues/data_policy

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit:
   https://drupal.org/documentation/install/modules-themes/modules-7
   for further information.

CONFIGURATION
-------------

 * Add the Data Policy Inform block to the region you want it to be shown at.
   Go to /admin/structure/block and click on "Place block" in a region. In the
   overview click on the Data Policy Inform block.

 * Navigate to /admin/config/system/inform-consent and add the pages you would
   like to have the explanation shown on.

 * Go to /admin/config/people/data-policy to create a new data policy entity.
   You can also add new revisions for each entity which you can make active.
   A data policy cannot be made inactive, be sure to create a new revision
   if you want to change something to an active revision.

 * At /admin/config/people/data-policy/settings you can choose if you want to
   force users to accept your (latest) data policy. If it is set to enforce and
   users do not agree they will be redirected to the account cancel page.
   Also on this page, you can set a new "enforce consent text" and add multiple
   data policy entities, an active revision will be used for each entity.
   To add multiple entities, stick to the format: [id:entity_id].
   Entity ID you can find `/admin/config/people/data-policy` in `DATA POLICY ID`
   column.

 * Visit /admin/reports/data-policy-agreements for an overview of users that
   saw, agreed to or did not agree to your data policy (or policies).
