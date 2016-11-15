<?php

namespace Drupal\flag\Tests;

use Drupal\flag\Tests\FlagTestBase;

/**
 * Tests the Flag admin UI.
 *
 * @group flag
 */
class AdminUITest extends FlagTestBase {


  /**
   * The entity query service.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQueryManager;

  /**
   * The label of the flag to create for the test.
   *
   * @var string
   */
  protected $label = 'Test label 123';

  /**
   * The ID of the flag created for the test.
   *
   * @var string
   */
  protected $flagId = 'test_label_123';

  /**
   * The flag used for the test.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flag;

  /**
   * The node for test flagging.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * The ID of the entity to created for the test.
   *
   * @var int
   */
  protected $nodeId;

  /**
   * Text used in construction of the flag.
   *
   * @var string
   */
  protected $flagShortText = 'Flag this stuff';

  /**
   * Text used in construction of the flag.
   *
   * @var string
   */
  protected $unflagShortText = 'Unflag this stuff';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityQueryManager = $this->container->get('entity.query');

    $this->drupalLogin($this->adminUser);

    // Create a node to flag.
    $this->node = $this->drupalCreateNode(['type' => $this->nodeType]);
    $this->nodeId = $this->node->id();
  }

  /**
   * Test basic flag admin.
   */
  public function testFlagAdmin() {
    $this->doFlagAdd();
    $this->doFlagEdit();

    $this->doFlagDisable();
    $this->doFlagEnable();

    $this->doFlagReset();

    $this->doFlagChangeWeights();

    $this->doFlagDelete();
  }

  /**
   * Flag creation.
   */
  public function doFlagAdd() {
    // Test with minimal value requirement.
    $this->drupalPostForm('admin/structure/flags/add', [], $this->t('Continue'));
    // Check for fieldset titles.
    $this->assertText(t('Messages'));
    $this->assertText(t('Flag access'));
    $this->assertText(t('Display options'));

    $edit = [
      'label' => $this->label,
      'id' => $this->flagId,
      'bundles[' . $this->nodeType . ']' => $this->nodeType,
      'flag_short' => $this->flagShortText,
      'unflag_short' => $this->unflagShortText,
    ];
    $this->drupalPostForm(NULL, $edit, $this->t('Create Flag'));

    $this->assertText(t('Flag @this_label has been added.', ['@this_label' => $this->label]));

    $this->flag = $this->flagService->getFlagById($this->flagId);

    $this->assertNotNull($this->flag, 'The flag was created.');

    $this->grantFlagPermissions($this->flag);
  }

  /**
   * Check the flag edit form.
   */
  public function doFlagEdit() {
    $this->drupalGet('admin/structure/flags/manage/' . $this->flagId);

    $elements = $this->xpath('//input[@id=:id]', array(':id' => 'edit-global-0'));
    $this->assertTrue(isset($elements[0]) && !empty($elements[0]['disabled']), 'The global form element is disabled when editing the flag.');
  }

  /**
   * Disable the flag and ensure the link does not appear on entities.
   */
  public function doFlagDisable() {
    $this->drupalGet('admin/structure/flags');
    $this->assertText(t('Enabled'));

    $this->drupalPostForm('admin/structure/flags/manage/' . $this->flagId . '/disable', [], $this->t('Disable'));
    $this->assertResponse(200);

    $this->drupalGet('admin/structure/flags');
    $this->assertText(t('Disabled'));

    $this->drupalGet('node/' . $this->nodeId);
    $this->assertNoText($this->flagShortText);
  }

  /**
   * Enable the flag and ensure it appears on target entities.
   */
  public function doFlagEnable() {
    $this->drupalGet('admin/structure/flags');
    $this->assertText(t('Disabled'));

    $this->drupalPostForm('admin/structure/flags/manage/' . $this->flagId . '/enable', [], $this->t('Enable'));
    $this->assertResponse(200);

    $this->drupalGet('admin/structure/flags');
    $this->assertText(t('Enabled'));

    $this->drupalGet('node/' . $this->nodeId);
    $this->assertText($this->flagShortText);
  }

  /**
   * Reset the flag and ensure the flaggings are deleted.
   */
  public function doFlagReset() {
    // Flag the node.
    $this->flagService->flag($this->flag, $this->node, $this->adminUser);

    $ids_before = $this->entityQueryManager->get('flagging')
      ->condition('flag_id', $this->flag->id())
      ->condition('entity_type', 'node')
      ->condition('entity_id', $this->node->id())
      ->execute();

    $this->assertEqual(count($ids_before), 1, "The flag has one flagging.");

    // Go to the reset form for the flag.
    $this->drupalGet('admin/structure/flags/manage/' . $this->flag->id() . '/reset');

    $this->assertText($this->t('Are you sure you want to reset the Flag'));

    $this->drupalPostForm(NULL, [], $this->t('Reset'));

    $ids_after = $this->entityQueryManager->get('flagging')
      ->condition('flag_id', $this->flag->id())
      ->condition('entity_type', 'node')
      ->condition('entity_id', $this->node->id())
      ->execute();

    $this->assertEqual(count($ids_after), 0, "The flag has no flaggings after being reset.");
  }

  /**
   * Create further flags and change the weights using the draggable list.
   */
  public function doFlagChangeWeights() {
    $flag_weights_to_set = [];

    // We have one flag already.
    $flag_weights_to_set[$this->flagId] = 0;

    foreach (range(1, 10) as $i) {
      $flag = $this->createFlag();

      $flag_weights_to_set[$flag->id()] = -$i;
    }

    $edit = array();
    foreach ($flag_weights_to_set as $id => $weight) {
      $edit['flags[' . $id . '][weight]'] = $weight;
    }
    // Saving the new weights via the interface.
    $this->drupalPostForm('admin/structure/flags', $edit, $this->t('Save'));

    // Load the all the flags.
    $flag_storage = $this->container->get('entity.manager')->getStorage('flag');
    $updated_flags = $flag_storage->loadMultiple();

    // Check that the weights are saved in the database correctly.
    foreach ($updated_flags as $id => $flag) {
      $this->assertEqual($updated_flags[$id]->get('weight'), $flag_weights_to_set[$id], 'The flag weight was changed.');
    }
  }

  /**
   * Delete the flag.
   */
  public function doFlagDelete() {
    // Flag node.
    $this->drupalGet('node/' . $this->nodeId);
    $this->assertLink($this->flagShortText);
    // Go to the delete form for the flag.
    $this->drupalGet('admin/structure/flags/manage/' . $this->flag->id() . '/delete');

    $this->assertText($this->t('Are you sure you want to delete the flag @this_label?', ['@this_label' => $this->label]));

    $this->drupalPostForm(NULL, [], $this->t('Delete'));

    // Check the flag has been deleted.
    $result = $this->flagService->getFlagById($this->flagId);

    $this->assertNull($result, 'The flag was deleted.');
    $this->drupalGet('node/' . $this->nodeId);
    $this->assertText($this->node->label());
    $this->assertNoLink($this->flagShortText);
  }

}
