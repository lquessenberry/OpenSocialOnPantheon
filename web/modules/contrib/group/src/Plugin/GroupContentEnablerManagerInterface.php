<?php

namespace Drupal\group\Plugin;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\group\Entity\GroupTypeInterface;

/**
 * Provides a common interface for group content enabler managers.
 */
interface GroupContentEnablerManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface {

  /**
   * Checks whether a certain plugin has a certain handler.
   *
   * @param string $plugin_id
   *   The plugin ID for this handler.
   * @param string $handler_type
   *   The name of the handler.
   *
   * @return bool
   *   Returns TRUE if the plugin has the handler, else FALSE.
   */
  public function hasHandler($plugin_id, $handler_type);

  /**
   * Returns a handler instance for the given plugin and handler.
   *
   * Entity handlers are instantiated once per entity type and then cached
   * in the entity type manager, and so subsequent calls to getHandler() for
   * a particular entity type and handler type will return the same object.
   * This means that properties on a handler may be used as a static cache,
   * although as the handler is common to all entities of the same type,
   * any data that is per-entity should be keyed by the entity ID.
   *
   * @param string $plugin_id
   *   The plugin ID for this handler.
   * @param string $handler_type
   *   The handler type to create an instance for.
   *
   * @return object
   *   A handler instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function getHandler($plugin_id, $handler_type);

  /**
   * Creates new handler instance.
   *
   * \Drupal\group\Plugin\GroupContentEnablerManagerInterface::getHandler() is
   * preferred since that method has additional checking that the class exists
   * and has static caches.
   *
   * @param mixed $class
   *   The handler class to instantiate.
   * @param string $plugin_id
   *   The ID of the plugin the handler is for.
   * @param array $definition
   *   The plugin definition.
   *
   * @return object
   *   A handler instance.
   *
   * @internal
   *   Marked as internal because the plugin definitions will become classes in
   *   a future release to further mimic the entity type system. Do not call
   *   this directly.
   */
  public function createHandlerInstance($class, $plugin_id, array $definition = NULL);

  /**
   * Creates a new access control handler instance.
   *
   * @param string $plugin_id
   *   The plugin ID for this access control handler.
   *
   * @return \Drupal\group\plugin\GroupContentAccessControlHandlerInterface
   *   An access control handler instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the plugin doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the access control handler couldn't be loaded.
   */
  public function getAccessControlHandler($plugin_id);

  /**
   * Creates a new permission provider instance.
   *
   * @param string $plugin_id
   *   The plugin ID for this permission provider.
   *
   * @return \Drupal\group\plugin\GroupContentPermissionProviderInterface
   *   A permission provider instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the plugin doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the permission provider couldn't be loaded.
   */
  public function getPermissionProvider($plugin_id);

  /**
   * Returns a plugin collection of all available content enablers.
   *
   * This collection will not have anything set in the individual plugins'
   * configuration. Do not use any methods on the plugin that require a group
   * type to be set or you may encounter unexpected behavior. Instead, use
   * ::getInstalled() while providing a group type argument to get fully
   * configured instances of the plugins.
   *
   * @return \Drupal\group\Plugin\GroupContentEnablerCollection
   *   A plugin collection with a vanilla instance of every known plugin.
   */
  public function getAll();

  /**
   * Returns a plugin collection of all installed content enablers.
   *
   * Warning: When called without a $group_type argument, this will return a
   * collection of vanilla plugin instances. See ::getAll() for details about
   * vanilla instances.
   *
   * @param \Drupal\group\Entity\GroupTypeInterface $group_type
   *   (optional) The group type to retrieve installed plugin for.
   *
   * @return \Drupal\group\Plugin\GroupContentEnablerCollection
   *   A plugin collection with a vanilla instance of every installed plugin. If
   *   $group_type was provided, the collection will contain fully instantiated
   *   plugins for the provided group type.
   */
  public function getInstalled(GroupTypeInterface $group_type = NULL);

  /**
   * Returns the plugin ID of all content enablers in use.
   *
   * @param \Drupal\group\Entity\GroupTypeInterface $group_type
   *   (optional) The group type to retrieve plugin IDs for.
   *
   * @return string[]
   *   A list of all installed content enabler plugin IDs. If $group_type was
   *   provided, this will only return the installed IDs for that group type.
   */
  public function getInstalledIds(GroupTypeInterface $group_type = NULL);

  /**
   * Returns the ID of all plugins that define access for a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return string[]
   *   The plugin IDs.
   */
  public function getPluginIdsByEntityTypeAccess($entity_type_id);

  /**
   * Installs all plugins which are marked as enforced.
   *
   * @param \Drupal\group\Entity\GroupTypeInterface $group_type
   *   (optional) The group type to install enforced plugins on. Leave blank to
   *   run the installation process for all group types.
   */
  public function installEnforced(GroupTypeInterface $group_type = NULL);

  /**
   * Retrieves all of the group content type IDs for a content plugin.
   *
   * @param string $plugin_id
   *   The ID of the plugin to retrieve group content type IDs for.
   *
   * @return string[]
   *   An array of group content type IDs.
   */
  public function getGroupContentTypeIds($plugin_id);

  /**
   * Retrieves a list of group content type IDs per plugin ID.
   *
   * @return array
   *   An array of group content type ID arrays, keyed by plugin ID.
   */
  public function getPluginGroupContentTypeMap();

  /**
   * Retrieves a list of plugin IDs per group type ID.
   *
   * @return array
   *   An array of content plugin ID arrays, keyed by group type ID.
   */
  public function getGroupTypePluginMap();

  /**
   * Clears the static per group type plugin collection cache.
   *
   * @param \Drupal\group\Entity\GroupTypeInterface $group_type
   *   (optional) The group type to clear the cache for. Leave blank to clear
   *   the cache for all group types.
   */
  public function clearCachedGroupTypeCollections(GroupTypeInterface $group_type = NULL);

  /**
   * Clears static and persistent plugin ID map caches.
   */
  public function clearCachedPluginMaps();

}
