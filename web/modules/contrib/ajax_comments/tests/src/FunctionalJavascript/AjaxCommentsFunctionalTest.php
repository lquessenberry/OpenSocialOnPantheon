<?php

namespace Drupal\Tests\ajax_comments\FunctionalJavascript;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\user\Entity\Role;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

/**
 * Javascript functional tests for ajax comments.
 *
 * @group ajax_comments
 */
class AjaxCommentsFunctionalTest extends WebDriverTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'ajax_comments',
    'node',
    'comment',
    'editor',
    'ckeditor',
    'filter',
  ];

   /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    // Ensure an `article` node type exists.
    $this->drupalCreateContentType(['type' => 'article']);
    $this->addDefaultCommentField('node', 'article');

    $comment_field = $this->entityTypeManager->getStorage('field_config')->load('node.article.comment');
    $comment_field->setSetting('per_page', 10);
    $comment_field->save();

    // Enable ajax comments on the comment field.
    $entity_view_display = EntityViewDisplay::load('node.article.default');
    $renderer = $entity_view_display->getRenderer('comment');
    $renderer->setThirdPartySetting('ajax_comments', 'enable_ajax_comments', '1');
    $entity_view_display->save();
  }

  /**
   * Tests that comments can be posted and edited over ajax without errors.
   */
  public function testCommentPosting() {
    // Enable CKEditor.
    $format = $this->randomMachineName();
    FilterFormat::create([
      'format' => $format,
      'name' => $this->randomString(),
      'weight' => 1,
      'filters' => [],
    ])->save();
    $settings['toolbar']['rows'] = [
      [
        [
          'name' => 'Links',
          'items' => [
            'DrupalLink',
            'DrupalUnlink',
          ],
        ],
      ],
    ];
    $editor = Editor::create([
      'format' => $format,
      'editor' => 'ckeditor',
    ]);
    $editor->setSettings($settings);
    $editor->save();

    $admin_user = $this->drupalCreateUser([
      'access content',
      'access comments',
      // Usernames aren't shown in comment edit form autocomplete unless this
      // permission is granted.
      'access user profiles',
      'administer comments',
      'edit own comments',
      'post comments',
      'skip comment approval',
      'use text format ' . $format,
    ]);
    $this->drupalLogin($admin_user);

    $node = $this->drupalCreateNode([
      'type' => 'article',
      'comment' => CommentItemInterface::OPEN,
    ]);
    $this->drupalGet($node->toUrl());

    // Set up JavaScript to track errors.
    $onerror_javascript = <<<JS
    (function (){
      window.jsErrors = [];
      window.onerror = function (message, source, lineno, colno, error) {
        window.jsErrors.push(error);
      }
    }());
JS;
    $this->getSession()->executeScript($onerror_javascript);

    $page = $this->getSession()->getPage();

    // Post comments through ajax.
    for ($i = 0; $i < 11; $i++) {
      $comment_body_id = $page
        ->findField('comment_body[0][value]')
        ->getAttribute('id');
      $count = $i + 1;
      $ckeditor_javascript = <<<JS
    (function (){
      CKEDITOR.instances['$comment_body_id'].setData('New comment $count');
    }());
JS;
      $this->getSession()->executeScript($ckeditor_javascript);
      $page->pressButton('Save');
      $this->assertSession()->assertWaitOnAjaxRequest(20000);
    }

    // Export the updated content of the page.
    if ($this->htmlOutputEnabled) {
      $out = $page->getContent();
      $this->htmlOutput($out);
    }
    $this->assertSession()->pageTextContains('Your comment has been posted.');
    $this->assertSession()->pageTextContains('New comment 1');
    $this->assertSession()->pageTextContains('New comment 2');

    $current_url = $this->getSession()->getCurrentUrl();
    $parts = parse_url($current_url);
    $path = empty($parts['path']) ? '/' : $parts['path'];
    $current_path = preg_replace('/^\\/[^\\.\\/]+\\.php\\//', '/', $path);

    $this->assertSession()->linkByHrefExists($current_path . '?page=1');

    $javascript_assertion = <<<JS
    (function () {
      return window.jsErrors.length === 0;
    }());
JS;
    $this->assertJsCondition($javascript_assertion);

    // Using prepareRequest() followed by refreshVariables() seems to help
    // refresh the route permissions for the ajax_comments.update route.
    $this->prepareRequest();
    $this->refreshVariables();

    // Test updating a comment through ajax.
    $this->clickLink('Edit');
    $this->assertSession()->assertWaitOnAjaxRequest(20000);
    $comment_body_id = $page->find('css', 'form.ajax-comments-form-edit textarea')->getAttribute('id');
    $ckeditor_javascript = <<<JS
    (function (){
      CKEDITOR.instances['$comment_body_id'].setData('Updated comment');
    }());
JS;
    $this->getSession()->executeScript($ckeditor_javascript);
    if ($this->htmlOutputEnabled) {
      $out = $page->getContent();
      $html_output = $out . '<hr />' . $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }

    // Save the edits to the comment.
    $this->refreshVariables();
    $save_button = $page->find('css', 'form.ajax-comments-form-edit input[value=Save]');
    $this->assertTrue(!empty($save_button));
    $save_button->press();
    $this->assertSession()->assertWaitOnAjaxRequest(20000);

    if ($this->htmlOutputEnabled) {
      $out = $page->getContent();
      $html_output = $out . '<hr />' . $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }
    $this->assertSession()->pageTextContains('Updated comment');
    $this->assertJsCondition($javascript_assertion);

    // Test the cancel button.
    $this->clickLink('Edit');
    $this->assertSession()->assertWaitOnAjaxRequest(20000);
    $this->assertSession()->elementExists('css', 'form.ajax-comments-form-edit');
    if ($this->htmlOutputEnabled) {
      $out = $page->getContent();
      $html_output = $out . '<hr />' . $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }
    $cancel_button = $page->find('css', 'form.ajax-comments-form-edit input[value=Cancel]');
    $this->assertTrue(!empty($cancel_button));
    $cancel_button->press();
    $this->assertSession()->assertWaitOnAjaxRequest(20000);
    if ($this->htmlOutputEnabled) {
      $out = $page->getContent();
      $html_output = $out . '<hr />' . $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }

    // Test replying to a comment.
    $this->clickLink('Reply');
    $this->assertSession()->assertWaitOnAjaxRequest(20000);
    $comment_body_id = $page->find('css', 'form.ajax-comments-form-reply textarea')->getAttribute('id');
    $ckeditor_javascript = <<<JS
    (function (){
      CKEDITOR.instances['$comment_body_id'].setData('Comment reply');
    }());
JS;
    $this->getSession()->executeScript($ckeditor_javascript);
    if ($this->htmlOutputEnabled) {
      $out = $page->getContent();
      $html_output = $out . '<hr />' . $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }
    $save_button = $page->find('css', 'form.ajax-comments-form-reply input[value=Save]');
    $this->assertTrue(!empty($save_button));
    $save_button->press();
    $this->assertSession()->assertWaitOnAjaxRequest(20000);
    if ($this->htmlOutputEnabled) {
      $out = $page->getContent();
      $html_output = $out . '<hr />' . $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }
    $this->assertSession()->pageTextContains('Comment reply');
    $this->assertJsCondition($javascript_assertion);

    // Test deleting a comment.
    $delete_link = $page->findLink('Delete');
    $this->assertTrue(!empty($delete_link));
    $delete_url = $delete_link->getAttribute('href');
    $this->assertTrue(!empty($delete_url));

    // Get the comment ID (in $matches[1]).
    preg_match('/comment\/(.+)\/delete/i', $delete_url, $matches);
    $this->assertTrue(isset($matches[1]));
    $comment_to_delete = Comment::load($matches[1]);
    $comment_to_delete_body = $comment_to_delete->get('comment_body')->value;

    $delete_form = $this->container
      ->get('entity_type.manager')
      ->getFormObject(
        $comment_to_delete->getEntityTypeId(), 'delete'
      );
    $delete_form->setEntity($comment_to_delete);
    // The delete confirmation question has tags stripped and is truncated
    // in the modal dialog box.
    $confirm_question = substr(strip_tags($delete_form->getQuestion()), 0, 50);

    $delete_link->click();
    $this->assertSession()->assertWaitOnAjaxRequest(20000);

    if ($this->htmlOutputEnabled) {
      $out = $page->getContent();
      $html_output = $out . '<hr />' . $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }
    $this->assertSession()->pageTextContains($confirm_question);

    $delete_button = $page->find('css', '.ui-dialog button.button--primary.js-form-submit');
    $this->assertTrue(!empty($delete_button));
    $delete_button->click();
    $this->assertSession()->assertWaitOnAjaxRequest(20000);
    if ($this->htmlOutputEnabled) {
      $out = $page->getContent();
      $html_output = $out . '<hr />' . $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }
    $this->assertSession()->pageTextNotContains($comment_to_delete_body);
    $this->assertJsCondition($javascript_assertion);

    // Test removing the role's permission to post comments.
    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = Role::loadMultiple($admin_user->getRoles());
    foreach ($roles as $role) {
      $role->revokePermission('post comments');
      $role->trustData()->save();
    }

    // Now try to submit a new comment. We haven't reloaded the page after
    // changing permissions, so the comment field should still be visible.
    $comment_body_id = $page
      ->findField('comment_body[0][value]')
      ->getAttribute('id');
    $ckeditor_javascript = <<<JS
    (function (){
      CKEDITOR.instances['$comment_body_id'].setData('This should fail.');
    }());
JS;
    $this->getSession()->executeScript($ckeditor_javascript);
    $page->pressButton('Save');
    $this->assertSession()->assertWaitOnAjaxRequest(20000);

    // Confirm that the new comment does not appear.
    $this->assertSession()->pageTextNotContains('This should fail.');
    // Confirm that the error message DOES appear.
    $this->assertSession()->pageTextContains('You do not have permission to post a comment.');

    if ($this->htmlOutputEnabled) {
      $out = $page->getContent();
      $html_output = $out . '<hr />' . $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }

    // Restore the user's permission to post comments, and reload the page
    // so that the reply links are visible.
    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = Role::loadMultiple($admin_user->getRoles());
    foreach ($roles as $role) {
      $role->grantPermission('post comments');
      $role->trustData()->save();
    }

    // Reload the page.
    $this->drupalGet($node->toUrl());

    // Revoke the user's permission to post comments, again.
    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = Role::loadMultiple($admin_user->getRoles());
    foreach ($roles as $role) {
      $role->revokePermission('post comments');
      $role->trustData()->save();
    }

    // Click the link to reply to a comment. The link should still be present,
    // because we haven't reloaded the page since revoking the user's
    // permission.
    $reply_link = $page->findLink('Reply');
    $reply_link->click();
    $this->assertSession()->assertWaitOnAjaxRequest(20000);

    if ($this->htmlOutputEnabled) {
      $out = $page->getContent();
      $html_output = $out . '<hr />' . $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }
    // Confirm that the error message appears.
    $this->assertSession()->pageTextContains('You do not have permission to post a comment.');

    // Again, restore the user's permission to post comments, and
    // reload the page so that the reply links are visible.
    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = Role::loadMultiple($admin_user->getRoles());
    foreach ($roles as $role) {
      $role->grantPermission('post comments');
      $role->trustData()->save();
    }

    // Reload the page.
    $this->drupalGet($node->toUrl());

    // Click the link to reply to a comment.
    $reply_link = $page->findLink('Reply');
    $reply_link->click();
    $this->assertSession()->assertWaitOnAjaxRequest(20000);
    // The reply form should load. Enter a comment in the reply field.
    $comment_body_id = $page->find('css', 'form.ajax-comments-form-reply textarea')->getAttribute('id');
    $ckeditor_javascript = <<<JS
    (function (){
      CKEDITOR.instances['$comment_body_id'].setData('This reply should fail.');
    }());
JS;
    $this->getSession()->executeScript($ckeditor_javascript);
    if ($this->htmlOutputEnabled) {
      $out = $page->getContent();
      $html_output = $out . '<hr />' . $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }

    // Revoke the user's permission to post comments without reloading the page.
    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = Role::loadMultiple($admin_user->getRoles());
    foreach ($roles as $role) {
      $role->revokePermission('post comments');
      $role->trustData()->save();
    }

    // Now try to click the 'Save' button on the reply form.
    $save_button = $page->find('css', 'form.ajax-comments-form-reply input[value=Save]');
    $this->assertTrue(!empty($save_button));
    $save_button->press();
    $this->assertSession()->assertWaitOnAjaxRequest(20000);

    if ($this->htmlOutputEnabled) {
      $out = $page->getContent();
      $html_output = $out . '<hr />' . $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }
    // Confirm that the new comment does not appear.
    $this->assertSession()->pageTextNotContains('This reply should fail.');
    // Confirm that the error message DOES appear.
    $this->assertSession()->pageTextContains('You do not have permission to post a comment.');
  }

}
