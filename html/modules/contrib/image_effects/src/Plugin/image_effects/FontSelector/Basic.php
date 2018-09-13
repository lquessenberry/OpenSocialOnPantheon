<?php

namespace Drupal\image_effects\Plugin\image_effects\FontSelector;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image_effects\Plugin\ImageEffectsFontSelectorPluginInterface;
use Drupal\image_effects\Plugin\ImageEffectsPluginBase;

/**
 * Basic font selector plugin.
 *
 * Allows typing in the font file URI/path.
 *
 * @Plugin(
 *   id = "basic",
 *   title = @Translation("Basic font selector"),
 *   short_title = @Translation("Basic"),
 *   help = @Translation("Allows typing in the font file URI/path.")
 * )
 */
class Basic extends ImageEffectsPluginBase implements ImageEffectsFontSelectorPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = []) {
    // Element.
    return array_merge([
      '#type' => 'textfield',
      '#title' => $this->t('Font URI/path'),
      '#description' => $this->t('An URI, an absolute path, or a relative path. Relative paths will be resolved relative to the Drupal installation directory.'),
      '#element_validate' => [[$this, 'validateSelectorUri']],
    ], $options);
  }

  /**
   * Validation handler for the selection element.
   */
  public function validateSelectorUri($element, FormStateInterface $form_state, $form) {
    if (!empty($element['#value'])) {
      if (!file_exists($element['#value'])) {
        $form_state->setErrorByName(implode('][', $element['#parents']), $this->t('The file does not exist.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription($uri) {
    return $this->getData($uri)['name'];
  }

  /**
   * Return the font information.
   *
   * Scans the font file to return tags information.
   *
   * @param string $uri
   *   the URI of the font file.
   *
   * @return array
   *   an associative array with the following keys:
   *   'copyright' => Copyright information
   *   'family' => Font family
   *   'subfamily' => Font subfamily
   *   'name' => Font name
   *   'file' => Font file URI
   */
  protected function getData($uri) {
    $realpath = drupal_realpath($uri);
    $fd = fopen($realpath, "r");
    $text = fread($fd, filesize($realpath));
    fclose($fd);

    $number_of_tabs = $this->dec2hex(ord($text[4])) . $this->dec2hex(ord($text[5]));
    for ($i = 0; $i < hexdec($number_of_tabs); $i++) {
      $tag = $text[12 + $i * 16] . $text[12 + $i * 16 + 1] . $text[12 + $i * 16 + 2] . $text[12 + $i * 16 + 3];
      if ($tag == "name") {
        $offset_name_table_hex = $this->dec2hex(ord($text[12 + $i * 16 + 8])) . $this->dec2hex(ord($text[12 + $i * 16 + 8 + 1])) . $this->dec2hex(ord($text[12 + $i * 16 + 8 + 2])) . $this->dec2hex(ord($text[12 + $i * 16 + 8 + 3]));
        $offset_name_table_dec = hexdec($offset_name_table_hex);
        $offset_storage_hex = $this->dec2hex(ord($text[$offset_name_table_dec + 4])) . $this->dec2hex(ord($text[$offset_name_table_dec + 5]));
        $offset_storage_dec = hexdec($offset_storage_hex);
        $number_name_records_hex = $this->dec2hex(ord($text[$offset_name_table_dec + 2])) . $this->dec2hex(ord($text[$offset_name_table_dec + 3]));
        $number_name_records_dec = hexdec($number_name_records_hex);
        break;
      }
    }

    $storage_dec = $offset_storage_dec + $offset_name_table_dec;
    $font = [
      'copyright' => '',
      'family' => '',
      'subfamily' => '',
      'name' => '',
      'file' => $uri,
    ];

    for ($j = 0; $j < $number_name_records_dec; $j++) {
      $name_id_hex = $this->dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 6])) . $this->dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 7]));
      $name_id_dec = hexdec($name_id_hex);
      $string_length_hex = $this->dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 8])) . $this->dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 9]));
      $string_length_dec = hexdec($string_length_hex);
      $string_offset_hex = $this->dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 10])) . $this->dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 11]));
      $string_offset_dec = hexdec($string_offset_hex);

      if ($name_id_dec == 0 && empty($font['copyright'])) {
        for ($l = 0; $l < $string_length_dec; $l++) {
          if (ord($text[$storage_dec + $string_offset_dec + $l]) >= 32) {
            $font['copyright'] .= $text[$storage_dec + $string_offset_dec + $l];
          }
        }
      }

      if ($name_id_dec == 1 && empty($font['family'])) {
        for ($l = 0; $l < $string_length_dec; $l++) {
          if (ord($text[$storage_dec + $string_offset_dec + $l]) >= 32) {
            $font['family'] .= $text[$storage_dec + $string_offset_dec + $l];
          }
        }
      }

      if ($name_id_dec == 2 && empty($font['subfamily'])) {
        for ($l = 0; $l < $string_length_dec; $l++) {
          if (ord($text[$storage_dec + $string_offset_dec + $l]) >= 32) {
            $font['subfamily'] .= $text[$storage_dec + $string_offset_dec + $l];
          }
        }
      }

      if ($name_id_dec == 4 && empty($font['name'])) {
        for ($l = 0; $l < $string_length_dec; $l++) {
          if (ord($text[$storage_dec + $string_offset_dec + $l]) >= 32) {
            $font['name'] .= $text[$storage_dec + $string_offset_dec + $l];
          }
        }
      }

      if ($font['copyright'] != "" && $font['family'] != "" && $font['subfamily'] != "" && $font['name'] != "") {
        break;
      }
    }

    return $font;
  }

  /**
   * Convert a dec to a hex.
   *
   * @param int $dec
   *   An integer number.
   *
   * @return string
   *   the number represented as hex
   */
  protected function dec2hex($dec) {
    $hex = dechex($dec);
    return str_repeat("0", 2 - Unicode::strlen($hex)) . Unicode::strtoupper($hex);
  }

}
