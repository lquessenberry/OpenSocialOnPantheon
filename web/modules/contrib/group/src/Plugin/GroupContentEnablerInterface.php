<?php

namespace Drupal\group\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Defines an interface for pluggable GroupContentEnabler back-ends.
 *
 * @see \Drupal\group\Annotation\GroupContentEnabler
 * @see \Drupal\group\GroupContentEnablerManager
 * @see \Drupal\group\Plugin\GroupContentEnablerBase
 * @see plugin_api
 */
interface GroupContentEnablerInterface extends PluginInspectionInterface, DerivativeInspectionInterface, ConfigurableInterface, DependentPluginInterface, PluginFormInterface {

  /**
   * Returns the plugin provider.
   *
   * @return string
   *   The plugin provider.
   */
  public function getProvider();

  /**
   * Returns the administrative label for the plugin.
   *
   * @return string
   *   The plugin label.
   */
  public function getLabel();

  /**
   * Returns the administrative description for the plugin.
   *
   * @return string
   *   The plugin description.
   */
  public function getDescription();

  /**
   * Returns the entity type ID the plugin supports.
   *
   * @return string
   *   The entity type ID.
   */
  public function getEntityTypeId();

  /**
   * Returns the entity bundle the plugin supports.
   *
   * @return string|false
   *   The bundle name or FALSE in case it supports all bundles.
   */
  public function getEntityBundle();

  /**
   * Returns the pretty path key for use in path aliases.
   *
   * @return string
   *   The plugin-provided pretty path key, defaults to 'content'.
   */
  public function getPrettyPathKey();

  /**
   * Returns the amount of groups the same content can be added to.
   *
   * @return int
   *   The group content's group cardinality.
   */
  public function getGroupCardinality();

  /**
   * Returns the amount of times the same content can be added to a group.
   *
   * @return int
   *   The group content's entity cardinality.
   */
  public function getEntityCardinality();

  /**
   * Returns the group type the plugin was instantiated for.
   *
   * @return \Drupal\group\Entity\GroupTypeInterface|null
   *   The group type, if set in the plugin configuration.
   */
  public function getGroupType();

  /**
   * Returns the ID of the group type the plugin was instantiated for.
   *
   * @return string|null
   *   The group type ID, if set in the plugin configuration.
   */
  public function getGroupTypeId();

  /**
   * Returns whether this plugin defines entity access.
   *
   * @return bool
   *   Whether this plugin defines entity access.
   *
   * @see \Drupal\group\Annotation\GroupContentEnabler::$entity_access
   */
  public function definesEntityAccess();

  /**
   * Returns whether this plugin is always on.
   *
   * @return bool
   *   The 'enforced' status.
   */
  public function isEnforced();

  /**
   * Returns whether this plugin can only be (un)installed through code.
   *
   * @return bool
   *   The 'code_only' status.
   */
  public function isCodeOnly();

  /**
   * Retrieves the label for a piece of group content.
   *
   * @param \Drupal\group\Entity\GroupContentInterface $group_content
   *   The group content entity to retrieve the label for.
   *
   * @return string
   *   The label as expected by \Drupal\Core\Entity\EntityInterface::label().
   */
  public function getContentLabel(GroupContentInterface $group_content);

  /**
   * Returns a safe, unique configuration ID for a group content type.
   *
   * By default we use GROUP_TYPE_ID-PLUGIN_ID-DERIVATIVE_ID, but feel free to
   * use any other means of identifying group content types.
   *
   * Please do not return any invalid characters in the ID as it will crash the
   * website. Refer to ConfigBase::validateName() for valid characters.
   *
   * @return string
   *   The safe ID to use as the configuration name.
   *
   * @see \Drupal\Core\Config\ConfigBase::validateName()
   */
  public function getContentTypeConfigId();

  /**
   * Returns the administrative label for a group content type.
   *
   * @return string
   *   The group content type label.
   */
  public function getContentTypeLabel();

  /**
   * Returns the administrative description for a group content type.
   *
   * @return string
   *   The group content type description.
   */
  public function getContentTypeDescription();

  /**
   * Provides a list of operations for a group.
   *
   * These operations can be implemented in numerous ways by extending modules.
   * Out of the box, Group provides a block that shows the available operations
   * to a user visiting a route with a group in its URL.
   *
   * Do not forget to specify cacheable metadata if you need to. This can be
   * done in ::getGroupOperationsCacheableMetadata().
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to generate the operations for.
   *
   * @return array
   *   An associative array of operation links to show when in a group context,
   *   keyed by operation name, containing the following key-value pairs:
   *   - title: The localized title of the operation.
   *   - url: An instance of \Drupal\Core\Url for the operation URL.
   *   - weight: The weight of the operation.
   *
   * @see ::getGroupOperationsCacheableMetadata()
   */
  public function getGroupOperations(GroupInterface $group);

  /**
   * Provides the cacheable metadata for this plugin's group operations.
   *
   * The operations set in ::getGroupOperations() may have some cacheable
   * metadata that needs to be set but can't be because the links set in an
   * Operations render element are simple associative arrays. This method allows
   * you to specify the cacheable metadata regardless.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheable metadata for the group operations.
   *
   * @see ::getGroupOperations()
   */
  public function getGroupOperationsCacheableMetadata();

  /**
   * Provides a list of operations for the content enabler plugin.
   *
   * These operations will be merged with the ones already available on the
   * group type content configuration page: (un)install, manage fields, etc.
   *
   * @return array
   *   An associative array of operation links to show on the group type content
   *   administration UI, keyed by operation name, containing the following
   *   key-value pairs:
   *   - title: The localized title of the operation.
   *   - url: An instance of \Drupal\Core\Url for the operation URL.
   *   - weight: The weight of this operation.
   */
  public function getOperations();

  /**
   * Provides a list of group permissions the plugin exposes.
   *
   * If you have some group permissions that would only make sense when your
   * plugin is installed, you may define those here. They will not be shown on
   * the permission configuration form unless the plugin is installed.
   *
   * @return array
   *   An array of group permissions, see GroupPermissionHandlerInterface for
   *   the structure of a group permission.
   *
   * @see GroupPermissionHandlerInterface::getPermissions()
   *
   * @deprecated in Group 1.0, will be removed before Group 2.0. Retrieve the
   *   permission_provider handler from the plugin manager instead.
   */
  public function getPermissions();

  /**
   * Performs access check for the create target entity operation.
   *
   * This method is supposed to be overwritten by extending classes that
   * do their own custom access checking.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to check for target entity creation access.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @deprecated in Group 1.0, will be removed before Group 2.0. Retrieve the
   *   access handler from the plugin manager instead.
   */
  public function createEntityAccess(GroupInterface $group, AccountInterface $account);

  /**
   * Performs access check for the create operation.
   *
   * This method is supposed to be overwritten by extending classes that
   * do their own custom access checking.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to check for content creation access.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @deprecated in Group 1.0, will be removed before Group 2.0. Retrieve the
   *   access handler from the plugin manager instead.
   */
  public function createAccess(GroupInterface $group, AccountInterface $account);

  /**
   * Checks access to an operation on a given group content entity.
   *
   * Use \Drupal\group\Plugin\GroupContentEnablerInterface::createAccess() to
   * check access to create a group content entity.
   *
   * @param \Drupal\group\Entity\GroupContentInterface $group_content
   *   The group content for which to check access.
   * @param string $operation
   *   The operation access should be checked for. Usually one of "view",
   *   "update" or "delete".
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @deprecated in Group 1.0, will be removed before Group 2.0. Retrieve the
   *   access handler from the plugin manager instead.
   */
  public function checkAccess(GroupContentInterface $group_content, $operation, AccountInterface $account);

  /**
   * Returns the label for the entity reference field.
   *
   * This allows you to specify the label for the entity reference field
   * pointing to the entity that is to become group content.
   *
   * @return string|null
   *   The label for the entity reference field or NULL if none was set.
   */
  public function getEntityReferenceLabel();

  /**
   * Returns the description for the entity reference field.
   *
   * This allows you to specify the description for the entity reference field
   * pointing to the entity that is to become group content.
   *
   * @return string|null
   *   The description for the entity reference field or NULL if none was set.
   */
  public function getEntityReferenceDescription();

  /**
   * Returns a list of entity reference field settings.
   *
   * This allows you to provide some handler settings for the entity reference
   * field pointing to the entity that is to become group content. You could
   * even change the handler being used, all without having to alter the bundle
   * field settings yourself through an alter hook.
   *
   * @return array
   *   An associative array where the keys are valid entity reference field
   *   setting names and the values are the corresponding setting for each key.
   *   Often used keys are 'target_type', 'handler' and 'handler_settings'.
   */
  public function getEntityReferenceSettings();

  /**
   * Runs tasks after the group content type for this plugin has been created.
   *
   * A good example of what you might want to do here, is the installation of
   * extra locked fields on the group content type. You can find an example in
   * \Drupal\group\Plugin\GroupContentEnabler\GroupMembership::postInstall().
   */
  public function postInstall();

}
