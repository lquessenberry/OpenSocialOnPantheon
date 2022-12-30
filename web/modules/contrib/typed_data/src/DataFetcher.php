<?php

namespace Drupal\typed_data;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\typed_data\Exception\InvalidArgumentException;

/**
 * Implementation of the data fetcher service.
 */
class DataFetcher implements DataFetcherInterface {

  /**
   * {@inheritdoc}
   */
  public function fetchDataByPropertyPath(TypedDataInterface $typed_data, $property_path, BubbleableMetadata $bubbleable_metadata = NULL, $langcode = NULL) {
    $sub_paths = explode('.', $property_path);
    return $this->fetchDataBySubPaths($typed_data, $sub_paths, $bubbleable_metadata, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchDataBySubPaths(TypedDataInterface $typed_data, array $sub_paths, BubbleableMetadata $bubbleable_metadata = NULL, $langcode = NULL) {
    $current_selector = [];
    $bubbleable_metadata = $bubbleable_metadata ?: new BubbleableMetadata();

    try {
      foreach ($sub_paths as $name) {
        $current_selector[] = $name;

        // If the current data is just a reference then directly dereference the
        // target.
        if ($typed_data instanceof DataReferenceInterface) {
          $this->addBubbleableMetadata($typed_data, $bubbleable_metadata);
          $typed_data = $typed_data->getTarget();
          if ($typed_data === NULL) {
            throw new MissingDataException("The specified reference is NULL.");
          }
        }

        // Make sure we are using the right language.
        if (isset($langcode) && $typed_data instanceof TranslatableInterface) {
          if ($typed_data->hasTranslation($langcode)) {
            $typed_data = $typed_data->getTranslation($langcode);
          }
          // @todo What if the requested translation does not exist? Currently
          // we just ignore that and continue with the current object.
        }

        // If this is a list but the selector is not an integer, we forward the
        // selection to the first element in the list.
        if ($typed_data instanceof ListInterface && !ctype_digit($name)) {
          $this->addBubbleableMetadata($typed_data, $bubbleable_metadata);
          $typed_data = $typed_data->get(0);
        }

        // Drill down to the next step in the data selector.
        if ($typed_data instanceof ListInterface || $typed_data instanceof ComplexDataInterface) {
          $this->addBubbleableMetadata($typed_data, $bubbleable_metadata);
          $typed_data = $typed_data->get($name);
        }
        else {
          $current_selector_string = implode('.', $current_selector);
          throw new InvalidArgumentException("The parent property is not a list or a complex structure at '$current_selector_string'.");
        }

        // If an accessed list item does not exist, $typed_data will be NULL.
        if (!isset($typed_data)) {
          $selector_string = implode('.', $sub_paths);
          $current_selector_string = implode('.', $current_selector);
          throw new MissingDataException("Unable to apply data selector '$selector_string' at '$current_selector_string'");
        }
      }
      $this->addBubbleableMetadata($typed_data, $bubbleable_metadata);
      return $typed_data;
    }
    catch (MissingDataException $e) {
      $selector = implode('.', $sub_paths);
      $current_selector = implode('.', $current_selector);
      throw new MissingDataException("Unable to apply data selector '$selector' at '$current_selector': " . $e->getMessage());
    }
    catch (\InvalidArgumentException $e) {
      $selector = implode('.', $sub_paths);
      $current_selector = implode('.', $current_selector);
      throw new InvalidArgumentException("Unable to apply data selector '$selector' at '$current_selector': " . $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fetchDefinitionByPropertyPath(DataDefinitionInterface $data_definition, $property_path, $langcode = NULL) {
    $sub_paths = explode('.', $property_path);
    return $this->fetchDefinitionBySubPaths($data_definition, $sub_paths, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchDefinitionBySubPaths(DataDefinitionInterface $data_definition, array $sub_paths, $langcode = NULL) {
    $current_selector = [];

    foreach ($sub_paths as $name) {
      $current_selector[] = $name;

      // If the current data is just a reference then directly dereference the
      // target.
      if ($data_definition instanceof DataReferenceDefinitionInterface) {
        $data_definition = $data_definition->getTargetDefinition();
      }

      // If this is a list but the selector is not an integer, we forward the
      // selection to the first element in the list.
      if ($data_definition instanceof ListDataDefinitionInterface && !ctype_digit($name)) {
        $data_definition = $data_definition->getItemDefinition();
      }

      // Drill down to the next step in the data selector.
      if ($data_definition instanceof ComplexDataDefinitionInterface) {
        $data_definition = $data_definition->getPropertyDefinition($name);
      }
      elseif ($data_definition instanceof ListDataDefinitionInterface) {
        $data_definition = $data_definition->getItemDefinition();
      }
      else {
        $current_selector_string = implode('.', $current_selector);
        if (count($current_selector) > 1) {
          $parent_property = $current_selector[count($current_selector) - 2];
          throw new InvalidArgumentException("The data selector '$current_selector_string' cannot be applied because the parent property '$parent_property' is not a list or a complex structure");
        }
        else {
          $type = $data_definition->getDataType();
          throw new InvalidArgumentException("The data selector '$current_selector_string' cannot be applied because the definition of type '$type' is not a list or a complex structure");
        }
      }

      // If an accessed property is not existing, $data_definition will be
      // NULL.
      if (!isset($data_definition)) {
        $selector_string = implode('.', $sub_paths);
        $current_selector_string = implode('.', $current_selector);
        throw new InvalidArgumentException("Unable to apply data selector '$selector_string' at '$current_selector_string'");
      }
    }
    return $data_definition;
  }

  /**
   * {@inheritdoc}
   */
  public function autocompletePropertyPath(array $data_definitions, $partial_property_path) {
    // For the empty string we suggest the names of the data definitions.
    if ($partial_property_path == '') {
      return array_keys($data_definitions);
    }

    $results = [];
    // Suggest top level variables where the partial path matches the start of
    // the top level variable name.
    foreach ($data_definitions as $variable_name => $data_definition) {
      if (stripos($variable_name, $partial_property_path) === 0) {
        $results = array_merge($results, $this->getAutocompleteSuggestion($data_definition, $variable_name));
      }
    }
    if (!empty($results)) {
      return $results;
    }

    // Partial path now contains more than just a top level variable. In order
    // to separate the variable parts we have to account for the syntax of
    // global context variables versus local context variables.
    // Global context variables begin with '@' and have a colon separating the
    // global context from the variable.
    $colon = strpos($partial_property_path, ':');
    if ($colon === FALSE) {
      // This is NOT a global context variable, so we only have to worry about
      // the '.' separators.
      $parts = explode('.', $partial_property_path);
      $first_part = array_shift($parts);
    }
    else {
      // This IS a global context variable, so the entire string up to and
      // including the ':' needs to be removed before we split the remainder
      // at the '.' separators.
      $parts = explode('.', substr($partial_property_path, $colon + 1));
      $first_part = substr($partial_property_path, 0, $colon + 1) . array_shift($parts);
    }

    if (!isset($data_definitions[$first_part])) {
      return [];
    }

    $last_part = array_pop($parts);
    $middle_path = implode('.', $parts);

    if ($middle_path === '') {
      $variable_definition = $data_definitions[$first_part];
    }
    else {
      try {
        $variable_definition = $this->fetchDefinitionByPropertyPath($data_definitions[$first_part], $middle_path);
      }
      catch (InvalidArgumentException $e) {
        // Invalid property path, so no suggestions available.
        return [];
      }
    }

    // If the current data is just a reference then directly dereference the
    // target.
    if ($variable_definition instanceof DataReferenceDefinitionInterface) {
      $variable_definition = $variable_definition->getTargetDefinition();
    }

    if ($variable_definition instanceof ListDataDefinitionInterface) {
      // Suggest a couple of example indices of a list if there is nothing
      // selected on it yet. Special case for fields: only make the suggestion
      // if this is a multi-valued field.
      if ($last_part === '' && !($variable_definition instanceof FieldDefinitionInterface
        && $variable_definition->getFieldStorageDefinition()->getCardinality() === 1)
      ) {
        if ($middle_path === '') {
          $property_path = $first_part;
        }
        else {
          $property_path = "$first_part.$middle_path";
        }
        $item_definition = $variable_definition->getItemDefinition();
        for ($i = 0; $i < 3; $i++) {
          $results = array_merge($results, $this->getAutocompleteSuggestion($item_definition, "$property_path.$i"));
        }
      }

      // If this is a list but the selector is not an integer, we forward the
      // selection to the first element in the list.
      if (!ctype_digit($last_part)) {
        $variable_definition = $variable_definition->getItemDefinition();
      }
    }

    if ($variable_definition instanceof ComplexDataDefinitionInterface) {
      foreach ($variable_definition->getPropertyDefinitions() as $property_name => $property_definition) {
        // If the property starts with the part then we have a suggestion. If
        // the part after the dot is the empty string we include all properties.
        if (stripos($property_name, $last_part) === 0 || $last_part === '') {

          if ($middle_path === '') {
            $property_path = "$first_part.$property_name";
          }
          else {
            $property_path = "$first_part.$middle_path.$property_name";
          }
          $results = array_merge($results, $this->getAutocompleteSuggestion($property_definition, $property_path));
        }
      }
    }

    usort($results, function ($a, $b) {
      return strnatcasecmp($a['value'], $b['value']);
    });
    return $results;
  }

  /**
   * Generates autocomplete suggestions for a matched data definition.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $data_definition
   *   The data definition to inspect.
   * @param string $variable_name
   *   The variable name or property path.
   *
   * @return array[]
   *   A list of autocomplete suggestions - valid property paths for the
   *   provided data definition. Each entry is an array with the following keys:
   *   - value: the data selector property path.
   *   - label: the human readable label suggestion.
   */
  protected function getAutocompleteSuggestion(DataDefinitionInterface $data_definition, $variable_name) {
    $label = $variable_name;
    if ($data_label = $data_definition->getLabel()) {
      $label .= " ($data_label)";
    }
    $results[] = ['value' => $variable_name, 'label' => $label];

    // If the data definition is just a reference then directly dereference the
    // target.
    if ($data_definition instanceof DataReferenceDefinitionInterface) {
      $data_definition = $data_definition->getTargetDefinition();
    }

    if ($data_definition instanceof ListDataDefinitionInterface
      || $data_definition instanceof ComplexDataDefinitionInterface
    ) {
      $label = "$variable_name...";
      if ($data_label) {
        $label .= " ($data_label)";
      }
      $results[] = ['value' => "$variable_name.", 'label' => $label];
    }

    return $results;
  }

  /**
   * Adds the bubbleable metadata of the given data.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $data
   *   The data of which to add the metadata.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   The bubbleable metadata to which to add the data.
   */
  protected function addBubbleableMetadata(TypedDataInterface $data, BubbleableMetadata $bubbleable_metadata) {
    if ($data instanceof PrimitiveInterface) {
      // Primitives do not have any metadata attached.
      return;
    }
    $value = $data->getValue();
    if ($value instanceof CacheableDependencyInterface || $value instanceof AttachmentsInterface) {
      $bubbleable_metadata->addCacheableDependency($value);
    }
  }

}
