<?php

namespace Drupal\graphql\GraphQL\Buffers;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Collects entity IDs per entity type and loads them all at once in the end.
 */
class EntityBuffer extends BufferBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EntityBuffer constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Add an item to the buffer.
   *
   * @param string $type
   *   The entity type of the given entity ids.
   * @param array|int|string $id
   *   The entity id(s) to load.
   *
   * @return \Closure
   *   The callback to invoke to load the result for this buffer item.
   */
  public function add($type, $id) {
    $item = new \ArrayObject([
      'type' => $type,
      'id' => $id,
    ]);

    return $this->createBufferResolver($item);
  }

  /**
   * {@inheritdoc}
   */
  protected function getBufferId($item) {
    return $item['type'];
  }

  /**
   * {@inheritdoc}
   */
  public function resolveBufferArray(array $buffer) {
    $type = reset($buffer)['type'];
    $ids = array_map(function (\ArrayObject $item) {
      return (array) $item['id'];
    }, $buffer);

    $ids = call_user_func_array('array_merge', $ids);
    $ids = array_values(array_unique($ids));

    // Load the buffered entities.
    $entities = $this->entityTypeManager
      ->getStorage($type)
      ->loadMultiple($ids);

    return array_map(function ($item) use ($entities) {
      if (is_array($item['id'])) {
        return array_reduce($item['id'], function ($carry, $current) use ($entities) {
          if (!empty($entities[$current])) {
            array_push($carry, $entities[$current]);
            return $carry;
          }

          return $carry;
        }, []);
      }

      return $entities[$item['id']] ?? NULL;
    }, $buffer);
  }

}
