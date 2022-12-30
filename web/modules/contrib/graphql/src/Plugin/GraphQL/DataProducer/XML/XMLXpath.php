<?php

namespace Drupal\graphql\Plugin\GraphQL\DataProducer\XML;

use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;

/**
 * XPath query data producer.
 *
 * @DataProducer(
 *   id = "xml_xpath",
 *   name = @Translation("XML Path"),
 *   description = @Translation("A DOM element located at a specific path."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("DOM element"),
 *     multiple = TRUE
 *   ),
 *   consumes = {
 *     "dom" = @ContextDefinition("any",
 *       label = @Translation("The source DOM element")
 *     ),
 *    "query" = @ContextDefinition("string",
 *       label = @Translation("The xpath query")
 *     ),
 *   }
 * )
 */
class XMLXpath extends DataProducerPluginBase {

  /**
   * Execute the XPath query and return matching DOMElements.
   *
   * @param \DOMElement $dom
   *   The source (root) DOM element.
   * @param string $query
   *   The xpath query.
   *
   * @return \DOMElement[]
   */
  public function resolve(\DOMElement $dom, $query) {
    $xpath = new \DOMXPath($dom->ownerDocument);
    return iterator_to_array($xpath->query($query, $dom));
  }

}
