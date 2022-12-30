<?php

namespace Drupal\Tests\group\Functional;

/**
 * Tests the behavior of the group type form.
 *
 * @group group
 */
class GroupTypeFormTest extends GroupBrowserTestBase {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->entityFieldManager = $this->container->get('entity_field.manager');
  }

  /**
   * Gets the global (site) permissions for the group creator.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getGlobalPermissions() {
    return [
      'administer group',
    ] + parent::getGlobalPermissions();
  }

  /**
   * Tests that a group type has option to change the title field label.
   */
  public function testCustomGroupTitleFieldLabel() {
    $this->drupalGet('/admin/group/types/add');
    $this->assertSession()->statusCodeEquals(200);

    $submit_button = 'Save group type';
    $this->assertSession()->buttonExists($submit_button);

    $group_type_id = 'my_first_group_type';
    $title_field_label = 'Title for foo';
    $edit = [
      'Name' => 'My first group type',
      'id' => $group_type_id,
      'Title field label' => $title_field_label,
    ];
    $this->submitForm($edit, $submit_button);

    $fields = $this->entityFieldManager->getFieldDefinitions('group', $group_type_id);
    $this->assertEquals($title_field_label, $fields['label']->getLabel());
  }

}
