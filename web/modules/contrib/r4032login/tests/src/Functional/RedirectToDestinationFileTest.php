<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\file\Entity\File;
use Drupal\Tests\BrowserTestBase;

/**
 * Test redirection when accessing a private file with spaces in its name.
 *
 * @group r4032login
 */
class RedirectToDestinationFileTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file', 'r4032login'];

  /**
   * A user to log on.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->user = $this->drupalCreateUser([], NULL, TRUE);

    $filename = 'sample with spaces.pdf';
    /** @var \Drupal\file\FileInterface $file */
    $file = File::create([
      'uri' => 'private://' . $filename,
      'uid' => $this->user->id(),
    ]);
    file_put_contents($file->getFileUri(), 'r4032login test PDF content');
    $file->save();
  }

  /**
   * Test redirection when accessing a private file with spaces in its name.
   */
  public function testRedirectToDestination() {
    $this->drupalGet('system/files/sample with spaces.pdf');

    $edit = [
      'name' => $this->user->getAccountName(),
      'pass' => $this->user->passRaw,
    ];
    $this->submitForm($edit, 'Log in');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'application/pdf');
    $this->assertSession()->pageTextContains('r4032login test PDF content');
  }

}
