<?php

namespace Drupal\Tests\r4032login\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test update of settings from admin/config/system/site-information.
 *
 * @group r4032login
 */
class SettingsUpdateTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['r4032login'];

  /**
   * Test update of settings.
   *
   * @param array $permissions
   *   The permissions for the user to test against.
   * @param bool $admin
   *   Either or not the user to test against is an admin.
   *
   * @dataProvider settingsUpdateDataProvider
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSettingsUpdate(array $permissions, $admin) {
    $webUser = $this->drupalCreateUser($permissions, NULL, $admin);

    $this->drupalLogin($webUser);

    // Test global settings form submission.
    $this->drupalGet('/admin/config/system/r4032login/settings');
    $settings = [
      'default_redirect_code' => 302,
      'add_noindex_header' => TRUE,
      'match_noredirect_negate' => 1,
      'match_noredirect_pages' => '/admin/config',
    ];
    $this->submitForm($settings, 'Save configuration');
    foreach ($settings as $key => $value) {
      $this->assertEquals($value, $this->config('r4032login.settings')->get($key));
    }

    // Test anonymous settings form submission.
    $this->drupalGet('/admin/config/system/r4032login/settings/anonymous');
    $anonymousSettings = [
      'redirect_to_destination' => FALSE,
      'destination_parameter_override' => 'test',
      'display_denied_message' => FALSE,
      'access_denied_message' => 'Access denied',
      'access_denied_message_type' => 'status',
    ];

    // The submission should fail because the /abcd path is invalid.
    $anonymousSettings['user_login_path'] = '/abcd';
    $this->submitForm($anonymousSettings, 'Save configuration');
    $this->assertSession()
      ->pageTextContains("The user login form path '/abcd' is either invalid or a logged out user does not have access to it.");

    // This submission should fail because
    // the /admin/config/system/site-information path is not accessible
    // to anonymous.
    $anonymousSettings['user_login_path'] = '/admin/config/system/site-information';
    $this->submitForm($anonymousSettings, 'Save configuration');
    $this->assertSession()
      ->pageTextContains("The user login form path '/admin/config/system/site-information' is either invalid or a logged out user does not have access to it.");

    // This submission should success
    // because the <front> path is valid for anonymous.
    $anonymousSettings['user_login_path'] = '<front>';
    $this->submitForm($anonymousSettings, 'Save configuration');
    $this->assertSession()
      ->pageTextContains("The user login form path '<front>' is either invalid or a logged out user does not have access to it.");

    // This submission should success
    // because the external https://www.drupal.org/user/login path is valid
    // for anonymous.
    $anonymousSettings['user_login_path'] = 'https://www.drupal.org/user/login';
    $this->submitForm($anonymousSettings, 'Save configuration');
    $this->assertSession()
      ->pageTextContains('The configuration options have been saved.');

    // This submission should success
    // because the internal /user/login path is valid for anonymous.
    $anonymousSettings['user_login_path'] = '/user/login';
    $this->submitForm($anonymousSettings, 'Save configuration');
    $this->assertSession()
      ->pageTextContains('The configuration options have been saved.');

    // Test that anonymous settings were correctly updated.
    foreach ($settings as $key => $value) {
      $this->assertEquals($value, $this->config('r4032login.settings')->get($key));
    }

    // Test authenticated settings form submission.
    $this->drupalGet('/admin/config/system/r4032login/settings/authenticated');
    $settings = [
      'redirect_authenticated_users_to' => 'https://www.drupal.org',
      'throw_authenticated_404' => TRUE,
      'display_auth_denied_message' => FALSE,
      'access_denied_auth_message' => 'Authenticated access denied',
      'access_denied_auth_message_type' => 'status',
    ];
    $this->submitForm($settings, 'Save configuration');
    foreach ($settings as $key => $value) {
      $this->assertEquals($value, $this->config('r4032login.settings')->get($key));
    }
  }

  /**
   * Data provider for testSettingsUpdate.
   */
  public function settingsUpdateDataProvider() {
    return [
      [
        [],
        TRUE,
      ],
      [
        ['administer r4032login'],
        FALSE,
      ],
    ];
  }

}
