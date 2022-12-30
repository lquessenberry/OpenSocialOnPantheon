<?php

namespace Drupal\metatag\TypedData;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\TypedData;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * A computed property for each meta tag.
 *
 * Required settings (below the definition's 'settings' key) are:
 *  - tag_name: The tag to be processed.
 *    Examples: "title", "description".
 */
class IndividualTag extends TypedData {

  use DependencySerializationTrait;

  /**
   * Cached processed value.
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);

    if ($definition->getSetting('tag_name') === NULL) {
      throw new \InvalidArgumentException("The definition's 'tag_name' key has to specify the name of the meta tag to be processed.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if (isset($this->value)) {
      return $this->value;
    }

    // The Metatag plugin ID.
    $property_name = $this->definition->getSetting('tag_name');

    // The item is the parent.
    $item = $this->getParent();
    $entity = $item->getEntity();

    // Rendered values.
    $metatagManager = \Drupal::service('metatag.manager');
    $defaultTags = $metatagManager->tagsFromEntityWithDefaults($entity);
    if (!isset($defaultTags[$property_name])) {
      \Drupal::service('logger.factory')
        ->get('metatag')
        ->notice('No default for "%tag_name" - entity_type: %type, entity_bundle: %bundle, id: %id. See src/TypedData/Metatags.php.', [
          '%tag_name' => $property_name,
          '%type' => $entity->getEntityTypeId(),
          '%bundle' => $entity->bundle(),
          '%id' => $entity->id(),
        ]);
      return FALSE;
    }
    $tags = [
      $property_name => $defaultTags[$property_name],
    ];
    $values = $metatagManager->generateRawElements($tags, $entity);

    $all_tags = [];
    foreach (\Drupal::service('plugin.manager.metatag.tag')->getDefinitions() as $tag_name => $tag_spec) {
      $all_tags[$tag_name] = new $tag_spec['class']([], $tag_name, $tag_spec);
    }

    // If this tag has a value set the property value.
    if (isset($values[$property_name])) {
      $attribute_name = $all_tags[$property_name]->getHtmlValueAttribute();

      // It should be possible to extract the HTML attribute that stores the
      // value, but in some cases it might not be possible.
      if (isset($values[$property_name]['#attributes'][$attribute_name])) {
        $this->value = $values[$property_name]['#attributes'][$attribute_name];
      }
      else {
        \Drupal::service('logger.factory')
          ->get('metatag')
          ->notice('Attribute value not mapped for "%property_name" - entity_type: %type, entity_bundle: %bundle, id: %id. See src/TypedData/Metatags.php.', [
            '%property_name' => $property_name,
            '%type' => $entity->getEntityTypeId(),
            '%bundle' => $entity->bundle(),
            '%id' => $entity->id(),
          ]);
        return FALSE;
      }
    }

    return $this->value;
  }

}
