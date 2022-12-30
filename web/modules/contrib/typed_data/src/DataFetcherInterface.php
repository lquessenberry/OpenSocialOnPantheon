<?php

namespace Drupal\typed_data;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Interface for the DataFetcher service.
 */
interface DataFetcherInterface {

  /**
   * Fetches data based upon the given property path.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $typed_data
   *   The data from which to select a value.
   * @param string $property_path
   *   The property path string, e.g. "uid.entity.mail.value".
   * @param \Drupal\Core\Render\BubbleableMetadata|null $bubbleable_metadata
   *   (optional) An object to which required bubbleable metadata will be added.
   * @param string $langcode
   *   (optional) The language code used to get the argument value if the
   *   argument value should be translated. Defaults to NULL.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The variable wrapped as typed data.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *   Thrown if the data cannot be fetched due to missing data; e.g., unset
   *   properties or list items.
   * @throws \Drupal\typed_data\Exception\InvalidArgumentException
   *   Thrown if the given path is not valid for the given data; e.g., a not
   *   existing property is referenced.
   */
  public function fetchDataByPropertyPath(TypedDataInterface $typed_data, $property_path, BubbleableMetadata $bubbleable_metadata = NULL, $langcode = NULL);

  /**
   * Fetches data based upon the given sub-paths.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $typed_data
   *   The data from which to select a value.
   * @param string[] $sub_paths
   *   A list of sub paths; i.e., a property path separated into its parts.
   * @param \Drupal\Core\Render\BubbleableMetadata|null $bubbleable_metadata
   *   (optional) An object to which required bubbleable metadata will be added.
   * @param string $langcode
   *   (optional) The language code used to get the argument value if the
   *   argument value should be translated. Defaults to NULL.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The variable wrapped as typed data.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *   Thrown if the data cannot be fetched due to missing data; e.g., unset
   *   properties or list items.
   * @throws \Drupal\typed_data\Exception\InvalidArgumentException
   *   Thrown if the given path is not valid for the given data; e.g., a not
   *   existing property is referenced.
   */
  public function fetchDataBySubPaths(TypedDataInterface $typed_data, array $sub_paths, BubbleableMetadata $bubbleable_metadata = NULL, $langcode = NULL);

  /**
   * Fetches a data definition based upon the given property path.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $data_definition
   *   The data definition from which to retrieve a nested definition.
   * @param string $property_path
   *   The property path string, e.g. "uid.entity.mail.value".
   * @param string $langcode
   *   (optional) The language code used to get the argument value if the
   *   argument value should be translated. Defaults to NULL.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface
   *   The data definition of the target.
   *
   * @throws \Drupal\typed_data\Exception\InvalidArgumentException
   *   Thrown if the given path is not valid for the given data; e.g., a not
   *   existing property is referenced.
   */
  public function fetchDefinitionByPropertyPath(DataDefinitionInterface $data_definition, $property_path, $langcode = NULL);

  /**
   * Fetches a data definition based upon the given sub-paths.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $data_definition
   *   The data definition from which to retrieve a nested definition.
   * @param string[] $sub_paths
   *   A list of sub paths; i.e., a property path separated into its parts.
   * @param string $langcode
   *   (optional) The language code used to get the argument value if the
   *   argument value should be translated. Defaults to NULL.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface
   *   The data definition of the target.
   *
   * @throws \Drupal\typed_data\Exception\InvalidArgumentException
   *   Thrown if the given path is not valid for the given data; e.g., a not
   *   existing property is referenced.
   */
  public function fetchDefinitionBySubPaths(DataDefinitionInterface $data_definition, array $sub_paths, $langcode = NULL);

  /**
   * Provides autocomplete suggestions for an incomplete property path.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $data_definitions
   *   A map of available data definitions that should be seareched. The array
   *   keys are the first part of the property path.
   * @param string $partial_property_path
   *   The partial property path, example: "node.uid.ent".
   *
   * @return array[]
   *   A list of autocomplete suggestions - valid property paths for one of the
   *   provided data definitions. Each entry is an array with the following
   *   keys:
   *   - value: the data selector property path.
   *   - label: the human readable label suggestion.
   */
  public function autocompletePropertyPath(array $data_definitions, $partial_property_path);

}
