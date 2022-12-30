<?php

namespace Drupal\bootstrap\BcSupport;

if (!interface_exists('\Drupal\Core\Security\TrustedCallbackInterface')) {
  /* @noinspection PhpIgnoredClassAliasDeclaration */
  class_alias('\Drupal\bootstrap\BcSupport\BcAliasedInterface', '\Drupal\Core\Security\TrustedCallbackInterface');
}

use Drupal\Core\Security\TrustedCallbackInterface as CoreTrustedCallbackInterface;

/**
 * Interface to declare trusted callbacks.
 *
 * @deprecated in bootstrap:8.x-3.22 and is removed from bootstrap:5.0.0.
 *   Use \Drupal\Core\Security\TrustedCallbackInterface instead.
 * @see https://www.drupal.org/project/bootstrap/issues/3096963
 * @see \Drupal\Core\Security\TrustedCallbackInterface
 */
interface TrustedCallbackInterface extends CoreTrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks();

}
