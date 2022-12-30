<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\Images;

use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * Returns the URL of an image derivative.
 *
 * @DataProducer(
 *   id = "image_style_url",
 *   name = @Translation("Image Style URL"),
 *   description = @Translation("Returns the URL of an image derivative."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("URL")
 *   ),
 *   consumes = {
 *     "derivative" = @ContextDefinition("any",
 *       label = @Translation("Derivative")
 *     )
 *   }
 * )
 */
class ImageResourceUrl extends DataProducerPluginBase {

  /**
   * Simply checks the url property in a given derivative result.
   *
   * @param array $derivative
   *
   * @return mixed
   */
  public function resolve(array $derivative) {
    return $derivative['url'] ?? '';
  }

}
