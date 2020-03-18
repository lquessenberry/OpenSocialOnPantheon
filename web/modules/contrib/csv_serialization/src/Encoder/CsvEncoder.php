<?php

namespace Drupal\csv_serialization\Encoder;

use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use League\Csv\Writer;
use League\Csv\Reader;
use SplTempFileObject;
use Drupal\Component\Utility\Html;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;

/**
 * Adds CSV encoder support for the Serialization API.
 */
class CsvEncoder implements EncoderInterface, DecoderInterface {


  /**
   * Indicates the character used to delimit fields. Defaults to ",".
   *
   * @var string
   */
  protected $delimiter;

  /**
   * Indicates the character used for field enclosure. Defaults to '"'.
   *
   * @var string
   */
  protected $enclosure;

  /**
   * Indicates the character used for escaping. Defaults to "\".
   *
   * @var string
   */
  protected $escapeChar;

  /**
   * Whether to strip tags from values or not. Defaults to TRUE.
   *
   * @var bool
   */
  protected $stripTags;

  /**
   * Whether to trim values or not. Defaults to TRUE.
   *
   * @var bool
   */
  protected $trimValues;

  /**
   * The format that this encoder supports.
   *
   * @var string
   */
  protected static $format = 'csv';

  /**
   * Indicates usage of UTF-8 signature in generated CSV file.
   *
   * @var bool
   */
  protected $useUtf8Bom = FALSE;

  /**
   * Constructs the class.
   *
   * @param string $delimiter
   *   Indicates the character used to delimit fields. Defaults to ",".
   * @param string $enclosure
   *   Indicates the character used for field enclosure. Defaults to '"'.
   * @param string $escape_char
   *   Indicates the character used for escaping. Defaults to "\".
   * @param bool $strip_tags
   *   Whether to strip tags from values or not. Defaults to TRUE.
   * @param bool $trim_values
   *   Whether to trim values or not. Defaults to TRUE.
   */
  public function __construct($delimiter = ",", $enclosure = '"', $escape_char = "\\", $strip_tags = TRUE, $trim_values = TRUE) {
    $this->delimiter = $delimiter;
    $this->enclosure = $enclosure;
    $this->escapeChar = $escape_char;
    $this->stripTags = $strip_tags;
    $this->trimValues = $trim_values;

    if (!ini_get("auto_detect_line_endings")) {
      ini_set("auto_detect_line_endings", '1');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format) {
    return $format == static::$format;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding($format) {
    return $format == static::$format;
  }

  /**
   * {@inheritdoc}
   *
   * Uses HTML-safe strings, with several characters escaped.
   */
  public function encode($data, $format, array $context = array()) {
    switch (gettype($data)) {
      case "array":
        break;

      case 'object':
        $data = (array) $data;
        break;

      // May be bool, integer, double, string, resource, NULL, or unknown.
      default:
        $data = array($data);
        break;
    }

    if (!empty($context['views_style_plugin']->options['csv_settings'])) {
      $this->setSettings($context['views_style_plugin']->options['csv_settings']);
    }

    try {
      // Instantiate CSV writer with options.
      $csv = Writer::createFromFileObject(new SplTempFileObject());
      $csv->setDelimiter($this->delimiter);
      $csv->setEnclosure($this->enclosure);
      $csv->setEscape($this->escapeChar);

      // Set data.
      if ($this->useUtf8Bom) {
        $csv->setOutputBOM(Writer::BOM_UTF8);
      }
      $headers = $this->extractHeaders($data, $context);
      $csv->insertOne($headers);
      $csv->addFormatter(array($this, 'formatRow'));
      foreach ($data as $row) {
        $csv->insertOne($row);
      }
      $output = $csv->__toString();

      return trim($output);
    }
    catch (\Exception $e) {
      throw new InvalidDataTypeException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * Extracts the headers using the first row of values.
   *
   * @param array $data
   *   The array of data to be converted to a CSV.
   * @param array $context
   *   Options that normalizers/encoders have access to. For views encoders
   *   this means that we'll have the view available here.
   *
   * We must make the assumption that each row shares the same set of headers
   * will all other rows. This is inherent in the structure of a CSV.
   *
   * @return array
   *   An array of CSV headesr.
   */
  protected function extractHeaders($data, array $context = array()) {
    $headers = [];
    if (!empty($data)) {
      $first_row = $data[0];
      $allowed_headers = array_keys($first_row);

      if (!empty($context['views_style_plugin'])) {
        $fields = $context['views_style_plugin']
          ->view
          ->getDisplay('rest_export_attachment_1')
          ->getOption('fields');
      }

      foreach ($allowed_headers as $allowed_header) {
        $headers[] = !empty($fields[$allowed_header]['label']) ? $fields[$allowed_header]['label'] : $allowed_header;
      }
    }

    return $headers;
  }

  /**
   * Formats all cells in a given CSV row.
   *
   * This flattens complex data structures into a string, and formats
   * the string.
   *
   * @param $row
   * @return array
   */
  public function formatRow($row) {
    $formatted_row = array();

    foreach ($row as $column_name => $cell_data) {
      if (is_array($cell_data)) {
        $cell_value = $this->flattenCell($cell_data);
      }
      else {
        $cell_value = $cell_data;
      }

      $formatted_row[] = $this->formatValue($cell_value);
    }

    return $formatted_row;
  }

  /**
   * Flattens a multi-dimensional array into a single level.
   *
   * @param array $data
   *   An array of data for be flattened into a cell string value.
   *
   * @return string
   *   The string value of the CSV cell, un-sanitized.
   */
  protected function flattenCell($data) {
    $depth = $this->arrayDepth($data);

    if ($depth == 1) {
      // @todo Allow customization of this in-cell separator.
      return implode('|', $data);
    }
    else {
      $cell_value = "";
      foreach ($data as $item) {
        $cell_value .= '|' . $this->flattenCell($item);
      }
      return trim($cell_value, '|');
    }
  }

  /**
   * Formats a single value for a given CSV cell.
   *
   * @param string $value
   *   The raw value to be formatted.
   *
   * @return string
   *   The formatted value.
   *
   */
  protected function formatValue($value) {
    if ($this->stripTags) {
      $value = Html::decodeEntities($value);
      $value = strip_tags($value);
    }
    if ($this->trimValues) {
      $value = trim($value);
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function decode($data, $format, array $context = array()) {
    $csv = Reader::createFromString($data);
    $csv->setDelimiter($this->delimiter);
    $csv->setEnclosure($this->enclosure);
    $csv->setEscape($this->escapeChar);

    $results = [];
    foreach ($csv->fetchAssoc() as $row) {
      $results[] = $this->expandRow($row);
    }

    return $results;
  }

  /**
   * Explodes multiple, concatenated values for all cells in a row.
   *
   * @param array $row
   *   The row of CSV cells.
   *
   * @return array
   *   The same row of CSV cells, with each cell's contents exploded.
   */
  public function expandRow($row) {
    foreach ($row as $column_name => $cell_data) {
      // @todo Allow customization of this in-cell separator.
      if (strpos($cell_data, '|') !== FALSE) {
        $row[$column_name] = explode('|', $cell_data);
      }
    }

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public static function getFileExtension() {
    return static::$format;
  }

  /**
   * Determine the depth of an array.
   *
   * This method determines array depth by analyzing the indentation of the
   * dumped array. This avoid potential issues with recursion.
   *
   * @param $array
   * @return float
   *
   * @see http://stackoverflow.com/a/263621
   */
  protected function arrayDepth($array) {
    $max_indentation = 1;

    $array_str = print_r($array, true);
    $lines = explode("\n", $array_str);

    foreach ($lines as $line) {
      $indentation = (strlen($line) - strlen(ltrim($line))) / 4;

      if ($indentation > $max_indentation) {
        $max_indentation = $indentation;
      }
    }

    return ceil(($max_indentation - 1) / 2) + 1;
  }

  /**
   * Set CSV settings from the Views settings array.
   *
   * If a tab character ('\t') is used for the delimiter, it will be properly
   * converted to "\t".
   */
  protected function setSettings(array $settings) {
    // Replace tab character with one that will be properly interpreted.
    $this->delimiter = str_replace('\t', "\t", $settings['delimiter']);
    $this->enclosure = $settings['enclosure'];
    $this->escapeChar = $settings['escape_char'];
    $this->useUtf8Bom = ($settings['encoding'] === 'utf8' && !empty($settings['utf8_bom']));
    $this->stripTags = $settings['strip_tags'];
    $this->trimValues = $settings['trim'];
  }

}
