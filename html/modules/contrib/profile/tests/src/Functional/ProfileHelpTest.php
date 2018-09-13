<?php

namespace Drupal\Tests\profile\Functional;

use Drupal\Core\Url;

/**
 * Tests that the help page for the module is available.
 *
 * @group profile
 */
class ProfileHelpTest extends ProfileTestBase {
  public static $modules = ['help'];

  /**
   * Tests that the help page loads.
   */
  public function testHelpPage() {
    $user = $this->createUser(['access administration pages']);
    $this->drupalLogin($user);
    $this->drupalGet(Url::fromRoute('help.page', ['name' => 'profile'])->toString());
    $this->assertSession()->pageTextContains('Types of profiles');
    $this->assertSession()->pageTextContains('Creating profiles');
  }

}
