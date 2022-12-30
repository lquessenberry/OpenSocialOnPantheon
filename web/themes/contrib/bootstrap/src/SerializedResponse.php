<?php

namespace Drupal\bootstrap;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SerializedResponse.
 */
class SerializedResponse extends Response {

  /**
   * The decoded data array.
   *
   * @var array
   */
  protected $data = [];

  /**
   * The serialization format.
   *
   * @var string
   */
  protected $format;

  /**
   * The request made that gave this response.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * A format specific Serialization service.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected static $serializer;

  /**
   * A map of extensions and acceptable MIME types.
   *
   * @var array
   */
  protected static $mimeExtensionMap = [
    'css' => [
      'text/css',
    ],
    'js' => [
      'application/javascript',
      'application/x-javascript',
      'text/javascript',
    ],
    'json' => [
      'application/hal+json',
      'application/json',
      'application/vnd.api+json',
      'application/x-json',
      'text/json',
    ],
    'yaml' => [
      'application/x-yaml',
      'application/yaml',
      'text/yaml',
      'text/yml',
    ],
    'yml' => [
      'application/x-yaml',
      'application/yaml',
      'text/yaml',
      'text/yml',
    ],
  ];

  /**
   * A map of formats, keyed by MIME type.
   *
   * @var array
   */
  protected static $mimeFormatMap = [
    'application/hal+json' => 'json',
    'application/json' => 'json',
    'application/vnd.api+json' => 'json',
    'application/x-json' => 'json',
    'application/x-yaml' => 'yaml',
    'application/yaml' => 'yaml',
    'text/json' => 'json',
    'text/yaml' => 'yaml',
    'text/yml' => 'yaml',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct($content = '', $status = 200, array $headers = [], Request $request = NULL) {
    parent::__construct($content, $status, $headers);
    $this->request = $request;

    // Attempt to determine the format, based on the response content type.
    $contentType = $this->getMimeType();
    if (isset(static::$mimeFormatMap[$contentType])) {
      $this->format = static::$mimeFormatMap[$contentType];
    }
    elseif (($extension = $this->getExtension()) && isset(static::$mimeFormatMap["text/$extension"])) {
      $this->format = static::$mimeFormatMap["text/$extension"];
    }

    if (($serializer = static::getSerializer()) && ($data = $serializer->decode($content))) {
      $this->data = $data;
      $this->content = NULL;
    }
  }

  /**
   * Creates a new SerializedResponse object from a Guzzle Response object.
   *
   * @param \GuzzleHttp\Psr7\Response $response
   *   A Guzzle Response object.
   * @param \GuzzleHttp\Psr7\Request $request
   *   Optional. The Guzzle Request object associated with the response.
   *
   * @return static
   */
  public static function createFromGuzzleResponse(GuzzleResponse $response, GuzzleRequest $request = NULL) {
    // In order to actually cache any request or response body contents, they
    // must be extracted from the stream before it's stored in the database.
    return new static($response->getBody(TRUE)->getContents(), $response->getStatusCode(), $response->getHeaders(), static::createRequestFromGuzzleRequest($request));
  }

  /**
   * Creates a new SerializedResponse object from an Exception object.
   *
   * @param \Exception $exception
   *   The exception thrown.
   * @param \GuzzleHttp\Psr7\Request $request
   *   Optional. The Guzzle Request object associated with the response.
   *
   * @return static
   */
  public static function createFromException(\Exception $exception, GuzzleRequest $request = NULL) {
    return new static($exception->getMessage(), $exception->getCode() ?: 500, [], static::createRequestFromGuzzleRequest($request));
  }

  /**
   * Creates a Symfony Request object from a Guzzle Request object.
   *
   * @param \GuzzleHttp\Psr7\Request $request
   *   The Guzzle Request object.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   A Symfony Request object.
   */
  protected static function createRequestFromGuzzleRequest(GuzzleRequest $request) {
    return Request::create($request->getUri(), $request->getMethod(), ['headers' => $request->getHeaders()], [], [], [], $request->getBody(TRUE)->getContents());
  }

  /**
   * {@inheritdoc}
   */
  public function getContent() {
    if (!isset($this->content) && ($serializer = $this->getSerializer()) && ($data = $this->getData())) {
      return $serializer->encode($data);
    }
    return $this->content;
  }

  /**
   * Retrieves the file extension from the request URI, if any.
   *
   * @return string
   *   The extension.
   */
  public function getExtension() {
    return $this->request ? pathinfo($this->request->getPathInfo(), PATHINFO_EXTENSION) : '';
  }

  /**
   * Retrieves the MIME type from the response Content-Type header.
   *
   * @return string
   *   The MIME type.
   */
  public function getMimeType() {
    $types = explode(';', $this->headers->get('Content-Type', ''));
    return reset($types) ?: NULL;
  }

  /**
   * Retrieves a format specific Serialization service.
   *
   * @return \Drupal\Component\Serialization\SerializationInterface|false
   *   A format specific Serialization service.
   */
  protected function getSerializer() {
    if (!isset(static::$serializer)) {
      static::$serializer = $this->format && \Drupal::hasService("serialization.{$this->format}") ? \Drupal::service("serialization.{$this->format}") : FALSE;
    }
    return static::$serializer;
  }

  /**
   * Retrieves the data array.
   *
   * @return array
   *   The data array.
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Ensures the MIME type matches the request file extension.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function validMimeExtension() {
    $extension = $this->getExtension();
    $mimeType = $this->getMimeType();
    return isset(static::$mimeExtensionMap[$extension]) && in_array($mimeType, static::$mimeExtensionMap[$extension]);
  }

}
