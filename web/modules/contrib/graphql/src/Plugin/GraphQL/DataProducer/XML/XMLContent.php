<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\XML;

use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * XML child nodes content data producer.
 *
 * @DataProducer(
 *   id = "xml_content",
 *   name = @Translation("XML Content"),
 *   description = @Translation("The content of a DOM element."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("Content"),
 *   ),
 *   consumes = {
 *     "dom" = @ContextDefinition("any",
 *       label = @Translation("The DOM element")
 *     )
 *   }
 * )
 */
class XMLContent extends DataProducerPluginBase {

  /**
   * Returns the XML content as string.
   *
   * @param \DOMElement $dom
   *   The source (root) DOM element.
   *
   * @return string
   */
  public function resolve(\DOMElement $dom) {
    return implode('', array_map(function ($child) {
      if ($child instanceof \DOMText) {
        return $child->nodeValue;
      }
      elseif ($child instanceof \DOMElement) {
        return $child->ownerDocument->saveXML($child);
      }
    }, iterator_to_array($dom->childNodes)));
  }

}
