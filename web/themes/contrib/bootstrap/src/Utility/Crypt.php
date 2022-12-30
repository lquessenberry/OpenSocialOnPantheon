<?php

namespace Drupal\bootstrap\Utility;

use Drupal\bootstrap\Bootstrap;
use Drupal\Component\Utility\Crypt as CoreCrypt;

/**
 * Extends \Drupal\Component\Utility\Crypt.
 *
 * @ingroup utility crypt functions.
 */
class Crypt extends CoreCrypt {

  /**
   * The regular expression used to match an SRI integrity value.
   *
   * @var string
   */
  const SRI_INTEGRITY_REGEXP = '/^(sha(?:256|384|512))-(.*)$/';

  /**
   * The length of each algorithm's digest, keyed by algorithm name.
   *
   * @var int[]
   *
   * @todo Move to a constant once PHP 5.5 is no longer supported.
   */
  protected static $algorithmDigestLengths = [
    'md5' => 32,
    'sha1' => 40,
    'sha224' => 56,
    'sha256' => 64,
    'sha384' => 96,
    'sha512' => 128,
  ];

  /**
   * The valid SRI Integrity algorithms supported by current browsers.
   *
   * @var string[]
   *
   * @todo Move to a constant once PHP 5.5 is no longer supported.
   */
  protected static $validSriIntegrityAlgorithms = [
    'sha256',
    'sha384',
    'sha512',
  ];

  /**
   * Ensures the base64 encoded hash matches the algorithm's digest length.
   *
   * @param string $algorithm
   *   The algorithm output length to check.
   * @param string $hash
   *   The base64 encoded hash to check.
   * @param bool $sriIntegrity
   *   Flag indicating whether this is a hash intended for use as an SRI
   *   integrity value.
   *
   * @return bool
   *   TRUE if the digest length from decoding the base64 hash matches what
   *   the algorithm length is supposed to be; FALSE otherwise.
   */
  public static function checkBase64HashAlgorithm($algorithm, $hash, $sriIntegrity = FALSE) {
    // Immediately return if values aren't provided or an unsupported algorithm.
    if (!$algorithm || !$hash || !isset(static::$algorithmDigestLengths[$algorithm])) {
      return FALSE;
    }

    // Check if this is an SRI algorithm supported by a browser.
    if ($sriIntegrity && !in_array($algorithm, static::$validSriIntegrityAlgorithms)) {
      return FALSE;
    }

    // Ensure the provided hash matches the length of the algorithm provided.
    return !!preg_match('/^([a-f0-9]{' . static::$algorithmDigestLengths[$algorithm] . '})$/', static::decodeHashBase64($hash));
  }

  /**
   * Decodes a base64 encoded hash back into its raw digest value.
   *
   * Note: this will also decode binary digests into a proper hexadecimal value.
   *
   * @param string $hash
   *   The base64 encoded hash to decode.
   *
   * @return string|false
   *   The decoded digest value or FALSE if unable to decode it.
   */
  public static function decodeHashBase64($hash) {
    $digest = base64_decode($hash);

    // Check if digest is binary and convert to hex, if needed.
    if ($digest && preg_match('/[^a-f0-9]*/', $digest) && (!extension_loaded('ctype') || !ctype_print($digest))) {
      $digest = bin2hex($digest);
    }

    return $digest;
  }

  /**
   * Determines the algorithm used for a base64 encoded hash.
   *
   * @param string $hash
   *   The base64 encoded hash to check.
   *
   * @return string|false
   *   The algorithm used or FALSE if unable to determine it.
   */
  public static function determineHashBase64Algorithm($hash) {
    $digest = static::decodeHashBase64($hash);
    $length = strlen($digest);
    return array_search($length, static::$algorithmDigestLengths, TRUE);
  }

  /**
   * Generates a unique identifier by serializing and hashing an array of data.
   *
   * @param array $data
   *   The data to serialize and hash.
   * @param string|string[] $prefix
   *   The value(s) to use to prefix the identifier, separated by colons (:).
   * @param string $delimiter
   *   The delimiter to use when joining the prefix and hash.
   *
   * @return string
   *   The uniquely generated identifier.
   */
  public static function generateBase64HashIdentifier(array $data, $prefix = NULL, $delimiter = ':') {
    $prefix = Unicode::castToString($prefix, $delimiter);
    $hash = self::hashBase64(serialize(array_merge([$prefix], $data)));
    return $prefix ? $prefix . $delimiter . $hash : $hash;
  }

  /**
   * Parses a SRI integrity value to separate the algorithm from the hash.
   *
   * @param string $integrity
   *   An integrity value beginning with a prefix indicating a particular hash
   *   algorithm (currently the allowed prefixes are sha256, sha384, and
   *   sha512), followed by a dash, and ending with the actual base64 encoded
   *   hash.
   *
   * @return array
   *   An indexed array containing intended for use with list():
   *   - algorithm - (string) The provided or matched algorithm.
   *   - hash - (string) The base64 encoded hash.
   *
   * @see https://developer.mozilla.org/en-US/docs/Web/Security/Subresource_Integrity
   */
  public static function parseSriIntegrity($integrity) {
    // Extract the algorithm and base64 encoded hash.
    preg_match(Crypt::SRI_INTEGRITY_REGEXP, $integrity, $matches);
    $algorithm = !empty($matches[1]) ? $matches[1] : FALSE;
    $hash = !empty($matches[2]) ? $matches[2] : $integrity;

    // Attempt to determine the algorithm used if one wasn't prepended.
    if (!$algorithm) {
      $algorithm = static::determineHashBase64Algorithm($hash);
    }

    return [$algorithm, $hash];
  }

  /****************************************************************************
   * Deprecated methods.
   * ***************************************************************************.
   */

  /**
   * Generates a unique hash name.
   *
   * @param ...
   *   All arguments passed will be serialized and used to generate the hash.
   *
   * @return string
   *   The generated hash identifier.
   *
   * @deprecated in 8.x-3.18 and is removed from project:5.0.0. Use
   *   \Drupal\bootstrap\Utility\Crypt::generateBase64HashIdentifier() instead.
   * @see
   */
  public static function generateHash() {
    Bootstrap::deprecated();
    $args = func_get_args();
    return static::generateBase64HashIdentifier($args, $args[0]);
  }

}
