<?php

namespace Drupal\flag\Tests;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flag\FlagInterface;
use Drupal\simpletest\WebTestBase;
use Drupal\flag\Entity\Flag;
use Drupal\user\RoleInterface;
use Drupal\user\Entity\Role;
use Drupal\Core\Template\Attribute;

/**
 * Provides common methods for Flag tests.
 */
abstract class FlagTestBase extends WebTestBase {

  use FlagCreateTrait;
  use StringTranslationTrait;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * A user with Flag admin rights.
   *
   * @var AccountInterface
   */
  protected $adminUser;

  /**
   * The node type to use in the test.
   *
   * @var string
   */
  protected $nodeType = 'article';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Get the Flag Service.
    $this->flagService = \Drupal::service('flag');

    // Place the title block, otherwise some tests fail.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content']);

    // Create content type.
    $this->drupalCreateContentType(['type' => $this->nodeType]);

    // Create the admin user.
    $this->adminUser = $this->createUser([
      'administer flags',
      'administer flagging display',
      'administer flagging fields',
      'administer node display',
      'administer modules',
      'administer nodes',
      'create ' . $this->nodeType . ' content',
      'edit any ' . $this->nodeType . ' content',
      'delete any ' . $this->nodeType . ' content',
    ]);
  }

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'views',
    'node',
    'user',
    'flag',
    'node',
    'field_ui',
    'text',
    'block',
    'contextual',
    'flag_event_test',
  );

  /**
   * Creates a flag entity using the admin UI.
   *
   * If you do not provide any bundles in $edit, all bundles for $entity_type
   * are assumed.
   *
   * @param string|null $entity_type
   *   (optional) A string containing the flaggable entity type, by default
   *   'node'.
   * @param array $edit
   *   (optional) An array of form field names and values. If omitted, random
   *   strings will be used for the flag ID, label, short and long text.
   * @param string|null $link_type
   *   (optional) A string containing the link type ID. Is omitted, assumes
   *   'reload'.
   *
   * @return \Drupal\flag\FlagInterface|null
   *   The created flag entity.
   */
  protected function createFlagWithForm($entity_type = 'node', $edit = [], $link_type = 'reload') {
    // Submit the flag add page.
    $this->drupalPostForm('admin/structure/flags/add', [
      'flag_entity_type' => $this->getFlagType($entity_type),
    ], $this->t('Continue'));

    // Set the link type.
    $this->drupalPostAjaxForm(NULL, ['link_type' => $link_type], 'link_type');

    // Create an array of defaults.
    $default_edit = [
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
      'flag_short' => $this->randomString(),
      'unflag_short' => $this->randomString(),
      'flag_long' => $this->randomString(16),
      'unflag_long' => $this->randomString(16),
      'flag_message' => $this->randomString(32),
      'unflag_message' => $this->randomString(32),
      'unflag_denied_text' => $this->randomString(),
    ];

    // Merge the default values with the edit array.
    $final_edit = array_merge($default_edit, $edit);

    // Submit the flag details form.
    $this->drupalPostForm(NULL, $final_edit, $this->t('Create Flag'));

    // Load the new flag we created.
    $flag = Flag::load($final_edit['id']);

    // Make sure that we actually did get a flag entity.
    $this->assertTrue($flag instanceof Flag);

    // Return the flag.
    return $flag;
  }

  /**
   * Grants flag and unflag permission to the given flag.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag on which to grant permissions.
   * @param array|string $role_id
   *   (optional) The ID of the role to grant permissions. If omitted, the
   *   authenticated role is assumed.
   * @param bool $can_flag
   *   (optional) TRUE to grant the role flagging permission, FALSE to not grant
   *   flagging permission to the role. If omitted, TRUE is assumed.
   * @param bool $can_unflag
   *   Optional TRUE to grant the role unflagging permission, FALSE to not grant
   *   unflagging permission to the role. If omitted, TRUE is assumed.
   */
  protected function grantFlagPermissions(FlagInterface $flag,
                                      $role_id = RoleInterface::AUTHENTICATED_ID,
                                      $can_flag = TRUE,
                                      $can_unflag = TRUE) {

    // Grant the flag permissions to the authenticated role, so that both
    // users have the same roles and share the render cache.
    $role = Role::load($role_id);
    if ($can_flag) {
      $role->grantPermission('flag ' . $flag->id());
    }

    if ($can_unflag) {
      $role->grantPermission('unflag ' . $flag->id());
    }

    $role->grantPermission('access contextual links');

    $role->save();
  }

  /**
   * Asserts that a contextual link placeholder with the given id exists.
   *
   * @see \Drupal\contextual\Tests\ContextualDynamicContextTest::assertContextualLinkPlaceHolder().
   *
   * @param string $id
   *   A contextual link id.
   *
   * @return bool
   *   The result of the assertion.
   */
  protected function assertContextualLinkPlaceHolder($id) {
    return $this->assertRaw('<div' . new Attribute(array('data-contextual-id' => $id)) . '></div>', format_string('Contextual link placeholder with id @id exists.', array('@id' => $id)));
  }

  /**
   * Asserts that a contextual link placeholder with the given id exists.
   *
   * @see \Drupal\contextual\Tests\ContextualDynamicContextTest::assertContextualLinkPlaceHolder().
   *
   * @param string $id
   *   A contextual link id.
   *
   * @return bool
   *   The result of the assertion.
   */
  protected function assertNoContextualLinkPlaceholder($id) {
    return $this->assertNoRaw('<div' . new Attribute(array('data-contextual-id' => $id)) . '></div>', format_string('Contextual link placeholder with id @id exists.', array('@id' => $id)));
  }

}
