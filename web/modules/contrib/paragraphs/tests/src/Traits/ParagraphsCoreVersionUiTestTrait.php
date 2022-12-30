<?php

namespace Drupal\Tests\paragraphs\Traits;

/**
 * Provides helper methods for Drupal 8.3.x and 8.4.x versions.
 */
trait ParagraphsCoreVersionUiTestTrait {

  /**
   * An adapter for 8.3 > 8.4 Save (and (un)publish) node button change.
   *
   * Arguments are the same as WebTestBase::drupalPostForm.
   *
   * @see \Drupal\simpletest\WebTestBase::drupalPostForm
   * @see https://www.drupal.org/node/2847274
   *
   * @param \Drupal\Core\Url|string $path
   *   Location of the post form.
   * @param array $edit
   *   Field data in an associative array.
   * @param mixed $submit
   *   Value of the submit button whose click is to be emulated. For example,
   * @param array $options
   *   (optional) Options to be forwarded to the url generator.
   * @param array $headers
   *   (optional) An array containing additional HTTP request headers.
   * @param string $form_html_id
   *   (optional) HTML ID of the form to be submitted.
   * @param string $extra_post
   *   (optional) A string of additional data to append to the POST submission.
   */
  protected function paragraphsPostNodeForm($path, $edit, $submit, array $options = [], array $headers = [], $form_html_id = NULL, $extra_post = NULL) {
    $drupal_version = (float) substr(\Drupal::VERSION, 0, 3);
    if ($drupal_version > 8.3) {
      switch ($submit) {
        case  t('Save and unpublish'):
          $submit = 'Save';
          $edit['status[value]'] = FALSE;
          break;

        case 'Save and publish':
          $submit = 'Save';
          $edit['status[value]'] = TRUE;
          break;

        case 'Save and keep published (this translation)':
          $submit = 'Save (this translation)';
          break;

        default:
          $submit = 'Save';
      }
    }
    parent::drupalPostForm($path, $edit, $submit, $options, $headers, $form_html_id, $extra_post);
  }

  /**
   * Places commonly used blocks in a consistent order.
   */
  protected function placeDefaultBlocks() {
    // Place the system main block explicitly and first to have a consistent
    // block order before and after Drupal 9.4
    $this->drupalPlaceBlock('system_main_block', ['weight' => -1, 'region' => 'content']);
    // Place the breadcrumb, tested in fieldUIAddNewField().
    $this->drupalPlaceBlock('system_breadcrumb_block', ['region' => 'content']);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content']);
    $this->drupalPlaceBlock('local_actions_block', ['region' => 'content']);
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content']);
  }

}
