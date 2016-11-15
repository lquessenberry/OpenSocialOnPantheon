<?php
/**
 * @file
 * Contains \Drupal\bootstrap\Plugin\Preprocess\Table.
 */

namespace Drupal\bootstrap\Plugin\Preprocess;

use Drupal\bootstrap\Annotation\BootstrapPreprocess;
use Drupal\bootstrap\Utility\Variables;

/**
 * Pre-processes variables for the "table" theme hook.
 *
 * @ingroup plugins_preprocess
 *
 * @BootstrapPreprocess("table")
 */
class Table extends PreprocessBase {

  /**
   * {@inheritdoc}
   */
  public function preprocessVariables(Variables $variables) {
    $responsive = (string) $this->theme->getSetting('table_responsive');
    switch ($responsive) {
      case '-1':
        $variables['responsive'] = !\Drupal::service('router.admin_context')->isAdminRoute();
        break;
      case '0':
        $variables['responsive'] = FALSE;
        break;
      case '1':
        $variables['responsive'] = TRUE;
        break;
    }
  }

}
