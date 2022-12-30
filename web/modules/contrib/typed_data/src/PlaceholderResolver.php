<?php

namespace Drupal\typed_data;

use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\typed_data\Exception\InvalidArgumentException;

/**
 * Resolver for placeholder tokens based upon typed data.
 *
 * @see \Drupal\Core\Utility\Token
 */
class PlaceholderResolver implements PlaceholderResolverInterface {

  /**
   * The typed data manager.
   *
   * @var \Drupal\typed_data\DataFetcherInterface
   */
  protected $dataFetcher;

  /**
   * The data filter manager.
   *
   * @var \Drupal\typed_data\DataFilterManagerInterface
   */
  protected $dataFilterManager;

  /**
   * Constructs the object.
   *
   * @param \Drupal\typed_data\DataFetcherInterface $data_fetcher
   *   The typed data manager.
   * @param \Drupal\typed_data\DataFilterManagerInterface $data_filter_manager
   *   The data filter manager.
   */
  public function __construct(DataFetcherInterface $data_fetcher, DataFilterManagerInterface $data_filter_manager) {
    $this->dataFetcher = $data_fetcher;
    $this->dataFilterManager = $data_filter_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function resolvePlaceholders($text, array $data = [], BubbleableMetadata $bubbleable_metadata = NULL, array $options = []) {
    $options += [
      'langcode' => NULL,
      'clear' => FALSE,
    ];
    $placeholder_by_data = $this->scan($text);
    if (empty($placeholder_by_data)) {
      return [];
    }

    $replacements = [];
    foreach ($placeholder_by_data as $data_name => $placeholders) {
      foreach ($placeholders as $placeholder_main_part => $placeholder) {
        try {
          if (!isset($data[$data_name])) {
            throw new MissingDataException("There is no data with the name '$data_name' available.");
          }
          list ($property_sub_paths, $filters) = $this->parseMainPlaceholderPart($placeholder_main_part, $placeholder);
          $fetched_data = $this->dataFetcher->fetchDataBySubPaths($data[$data_name], $property_sub_paths, $bubbleable_metadata, $options['langcode']);

          // Apply filters.
          if ($filters) {
            $value = $fetched_data->getValue();
            $definition = $fetched_data->getDataDefinition();
            foreach ($filters as $filter_data) {
              list($filter_id, $arguments) = $filter_data;
              $filter = $this->dataFilterManager->createInstance($filter_id);
              if (!$filter->allowsNullValues() && !isset($value)) {
                throw new MissingDataException("There is no data value for filter '$filter_id' to work on.");
              }
              $value = $filter->filter($definition, $value, $arguments, $bubbleable_metadata);
              $definition = $filter->filtersTo($definition, $arguments);
            }
          }
          else {
            $value = $fetched_data->getString();
          }

          // Escape the tokens, unless they are explicitly markup.
          $replacements[$placeholder] = $value instanceof MarkupInterface ? $value : new HtmlEscapedText($value);
        }
        catch (InvalidArgumentException $e) {
          // Should we log warnings if there are problems other than missing
          // data, like syntactically invalid placeholders?
          if (!empty($options['clear'])) {
            $replacements[$placeholder] = '';
          }
        }
        catch (MissingDataException $e) {
          if (!empty($options['clear'])) {
            $replacements[$placeholder] = '';
          }
        }
      }
    }
    return $replacements;
  }

  /**
   * Parses the main placeholder part.
   *
   * Main placeholder parts look like 'property.property|filter(arg)|filter'.
   *
   * @param string $main_part
   *   The main placeholder part.
   * @param string $placeholder
   *   The full placeholder string.
   *
   * @return array[]
   *   An numerically indexed arrays containing:
   *   - The numerically indexed array of property sub-paths.
   *   - The numerically indexed array of parsed filter expressions, where each
   *     entry is another numerically indexed array containing two items: the
   *     the filter id and the array of filter arguments.
   *
   * @throws \Drupal\typed_data\Exception\InvalidArgumentException
   *   Thrown if in invalid placeholders are to be parsed.
   */
  protected function parseMainPlaceholderPart($main_part, $placeholder) {
    if (!$main_part) {
      return [[], []];
    }
    $properties = explode('.', $main_part);
    $last_part = array_pop($properties);
    $filter_expressions = array_filter(explode('|', $last_part));
    // If there is a property, the first part, before the first |, is it.
    // Also be sure to remove potential whitespace after the last property.
    if ($main_part[0] != '|') {
      $properties[] = rtrim(array_shift($filter_expressions));
    }
    $filters = [];

    foreach ($filter_expressions as $expression) {
      // Look for filter arguments.
      $matches = [];
      preg_match_all('/
      ([^\(]+)
      \(             # ( - pattern start
       (.+)
      \)             # ) - pattern end
      /x', $expression, $matches);

      $filter_id = isset($matches[1][0]) ? $matches[1][0] : $expression;
      // Be sure to remove all whitespaces.
      $filter_id = str_replace(' ', '', $filter_id);
      $args = array_map(function ($arg) {
        // Remove surrounding whitespaces and then quotes.
        return trim(trim($arg), "'");
      }, explode(',', isset($matches[2][0]) ? $matches[2][0] : ''));

      $filters[] = [$filter_id, $args];
    }
    return [$properties, $filters];
  }

  /**
   * {@inheritdoc}
   */
  public function replacePlaceHolders($text, array $data = [], BubbleableMetadata $bubbleable_metadata = NULL, array $options = []) {
    $replacements = $this->resolvePlaceholders($text, $data, $bubbleable_metadata, $options);

    $placeholders = array_keys($replacements);
    $values = array_values($replacements);

    return str_replace($placeholders, $values, $text);
  }

  /**
   * {@inheritdoc}
   */
  public function scan($text) {
    // Matches tokens with the following pattern: {{ $name.$property_path }}
    // $name and $property_path may not contain { or } characters.
    // $name may not contain . or whitespace characters, but $property_path may.
    // $name may optionally contain a prefix of the form "@service_id:" which
    // indicates it's a global context variable. In this case, the prefix
    // starts with @, ends with :, and doesn't contain any whitespace.
    $number_of_tokens = preg_match_all('/
      \{\{\s*                   # {{ - pattern start
      ((?:@\S+:)?[^\s\{\}.|]*)  # $match[1] $name not containing whitespace . | { or }, with optional prefix
      (                         # $match[2] begins
        (
          (\.|\s*\|\s*)         # . with no spaces on either side, or | as separator
          [^\s\{\}.|]           # after separator we need at least one character
        )
        ([^\{\}]*)              # but then almost anything goes up until pattern end
      )?                        # $match[2] is optional
      \s*\}\}                   # }} - pattern end
      /x', $text, $matches);

    $names = $matches[1];
    $tokens = $matches[2];

    // Iterate through the matches, building an associative array containing
    // $tokens grouped by $name, pointing to the version of the token found in
    // the source text. For example,
    // $results['node']['title'] = '{{node.title}}';
    // where '{{node.title}}' is found in the source text.
    $results = [];
    for ($i = 0; $i < $number_of_tokens; $i++) {
      // Remove leading whitespaces and ".", but not the | denoting a filter.
      $main_part = trim($tokens[$i], ". \t\n\r\0\x0B");
      $results[$names[$i]][$main_part] = $matches[0][$i];
    }

    return $results;
  }

}
