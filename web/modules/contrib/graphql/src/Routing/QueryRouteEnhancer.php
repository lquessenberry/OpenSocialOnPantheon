<?php

namespace Drupal\graphql\Routing;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Routing\EnhancerInterface;
use Drupal\graphql\GraphQL\Utility\JsonHelper;
use GraphQL\Server\Helper;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Adds GraphQL operation information to the Symfony route being resolved.
 */
class QueryRouteEnhancer implements EnhancerInterface {

  /**
   * Returns whether the enhancer runs on the current route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The current route.
   *
   * @return bool
   */
  public function applies(Route $route) {
    return $route->hasDefault('_graphql');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \GraphQL\Server\RequestError
   */
  public function enhance(array $defaults, Request $request) {
    $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];
    if (!$this->applies($route)) {
      return $defaults;
    }

    $helper = new Helper();
    $method = $request->getMethod();
    $body = $this->extractBody($request);
    $query = $this->extractQuery($request);
    $operations = $helper->parseRequestParams($method, $body, $query);

    return $defaults + ['operations' => $operations];
  }

  /**
   * Extracts the query parameters from a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The http request object.
   *
   * @return array
   *   The normalized query parameters.
   */
  protected function extractQuery(Request $request) {
    return JsonHelper::decodeParams($request->query->all());
  }

  /**
   * Extracts the body parameters from a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The http request object.
   *
   * @return array
   *   The normalized body parameters.
   */
  protected function extractBody(Request $request) {
    $values = [];

    // Extract the request content.
    if ($content = json_decode($request->getContent(), TRUE)) {
      $values = array_merge($values, JsonHelper::decodeParams($content));
    }

    if (stripos($request->headers->get('content-type'), 'multipart/form-data') !== FALSE) {
      return $this->extractMultipart($request, $values);
    }

    return $values;
  }

  /**
   * Handles file uploads from multipart/form-data requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param array $values
   *   The request body values.
   *
   * @return array
   *   The query parameters with added file uploads.
   */
  protected function extractMultipart(Request $request, array $values) {
    // The request body parameters might contain file upload mutations. We treat
    // them according to the graphql multipart request specification.
    //
    // @see https://github.com/jaydenseric/graphql-multipart-request-spec#server
    if ($body = JsonHelper::decodeParams($request->request->all())) {
      // Flatten the operations array if it exists.
      $operations = isset($body['operations']) && is_array($body['operations']) ? $body['operations'] : [];
      $values = array_merge($values, $body, $operations);
    }

    // According to the graphql multipart request specification, uploaded files
    // are referenced to variable placeholders in a map. Here, we resolve this
    // map by assigning the uploaded files to the corresponding variables.
    if (!empty($values['map']) && is_array($values['map']) && $files = $request->files->all()) {
      foreach ($files as $key => $file) {
        if (!isset($values['map'][$key])) {
          continue;
        }

        $paths = (array) $values['map'][$key];
        foreach ($paths as $path) {
          $path = explode('.', $path);

          if (NestedArray::keyExists($values, $path)) {
            NestedArray::setValue($values, $path, $file);
          }
        }
      }
    }

    return $values;
  }

}
