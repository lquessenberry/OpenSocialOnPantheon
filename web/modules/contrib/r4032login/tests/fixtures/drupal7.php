<?php
// phpcs:ignoreFile
/**
 * @file
 * A database agnostic dump for testing purposes.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$connection->schema()->createTable('system', array(
  'fields' => array(
    'filename' => array(
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '255',
      'default' => '',
    ),
    'name' => array(
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '255',
      'default' => '',
    ),
    'type' => array(
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '12',
      'default' => '',
    ),
    'owner' => array(
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '255',
      'default' => '',
    ),
    'status' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => '0',
    ),
    'bootstrap' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => '0',
    ),
    'schema_version' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'small',
      'default' => '-1',
    ),
    'weight' => array(
      'type' => 'int',
      'not null' => TRUE,
      'size' => 'normal',
      'default' => '0',
    ),
    'info' => array(
      'type' => 'blob',
      'not null' => FALSE,
      'size' => 'normal',
    ),
  ),
  'primary key' => array(
    'filename',
  ),
  'indexes' => array(
    'system_list' => array(
      'status',
      'bootstrap',
      'type',
      'weight',
      'name',
    ),
    'type_name' => array(
      'type',
      'name',
    ),
  ),
  'mysql_character_set' => 'utf8mb3',
));

$connection->insert('system')
->fields(array(
  'filename',
  'name',
  'type',
  'owner',
  'status',
  'bootstrap',
  'schema_version',
  'weight',
  'info',
))
->values(array(
  'filename' => 'modules/system/system.module',
  'name' => 'system',
  'type' => 'module',
  'owner' => '',
  'status' => '1',
  'bootstrap' => '0',
  'schema_version' => '7084',
  'weight' => '0',
  'info' => 'a:14:{s:4:"name";s:6:"System";s:11:"description";s:54:"Handles general site configuration for administrators.";s:7:"package";s:4:"Core";s:7:"version";s:4:"7.82";s:4:"core";s:3:"7.x";s:5:"files";a:6:{i:0;s:19:"system.archiver.inc";i:1;s:15:"system.mail.inc";i:2;s:16:"system.queue.inc";i:3;s:14:"system.tar.inc";i:4;s:18:"system.updater.inc";i:5;s:11:"system.test";}s:8:"required";b:1;s:9:"configure";s:19:"admin/config/system";s:7:"project";s:6:"drupal";s:9:"datestamp";s:10:"1626883669";s:5:"mtime";i:1626883669;s:12:"dependencies";a:0:{}s:3:"php";s:5:"5.2.4";s:9:"bootstrap";i:0;}',
))
->values(array(
  'filename' => 'sites/all/modules/contrib/r4032login/r4032login.module',
  'name' => 'r4032login',
  'type' => 'module',
  'owner' => '',
  'status' => '1',
  'bootstrap' => '0',
  'schema_version' => '7000',
  'weight' => '0',
  'info' => 'a:11:{s:4:"name";s:26:"Redirect 403 to User Login";s:11:"description";s:78:"Redirect anonymous users from 403 Access Denied pages to the /user/login page.";s:4:"core";s:3:"7.x";s:9:"configure";s:36:"admin/config/system/site-information";s:5:"mtime";i:1639728369;s:12:"dependencies";a:0:{}s:7:"package";s:5:"Other";s:7:"version";N;s:3:"php";s:5:"5.2.4";s:5:"files";a:0:{}s:9:"bootstrap";i:0;}',
))
->execute();

$connection->schema()->createTable('variable', array(
  'fields' => array(
    'name' => array(
      'type' => 'varchar',
      'not null' => TRUE,
      'length' => '128',
      'default' => '',
    ),
    'value' => array(
      'type' => 'blob',
      'not null' => TRUE,
      'size' => 'big',
    ),
  ),
  'primary key' => array(
    'name',
  ),
  'mysql_character_set' => 'utf8mb3',
));

$connection->insert('variable')
->fields(array(
  'name',
  'value',
))
->values(array(
  'name' => 'r4032login_access_denied_message',
  'value' => 's:49:"Access denied. You must log in to view this page.";',
))
->values(array(
  'name' => 'r4032login_access_denied_message_type',
  'value' => 's:7:"warning";',
))
->values(array(
  'name' => 'r4032login_default_redirect_code',
  'value' => 's:3:"301";',
))
->values(array(
  'name' => 'r4032login_display_denied_message',
  'value' => 'i:1;',
))
->values(array(
  'name' => 'r4032login_match_noredirect_pages',
  'value' => "s:24:\"blog/*\r\nnode/15\r\nnode/16\";",
))
->values(array(
  'name' => 'r4032login_redirect_authenticated_users_to',
  'value' => 's:7:"<front>";',
))
->values(array(
  'name' => 'r4032login_redirect_to_destination',
  'value' => 'i:1;',
))
->values(array(
  'name' => 'r4032login_user_login_path',
  'value' => 's:10:"user/login";',
))
->execute();
