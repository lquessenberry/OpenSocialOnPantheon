# GraphQL OAuth

This module introduces GraphQL directives; which gives the possibility to restrict/allow access on field and 
type definitions for users or applications by OAuth scopes.

## Dependencies
* [GraphQL](https://www.drupal.org/project/graphql) (>=4.1)
* [Simple OAuth](https://www.drupal.org/project/simple_oauth) (>=6.0)

## Setup

The directives are made available as a schema extension see:
`Drupal\graphql_oauth\Plugin\GraphQL\SchemaExtension\OauthSchemaExtension`.
The OAuth schema extension requires to be programmatically referenced to your schema, you can do this by
overriding the `getExtensions` method in your schema plugin:

```
/**
 * @Schema(
 *   id = "example",
 *   name = "Example Schema"
 * )
 */
class ExampleSchema extends SdlSchemaPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function getExtensions(): array {
    $extensions = parent::getExtensions();
    // Enable OAuth related directives in the schema.
    $oauth_extension_plugin_id = 'graphql_oauth_schema_extension';
    if (!isset($extensions[$oauth_extension_plugin_id])) {
      /** @var \Drupal\graphql\Plugin\SchemaExtensionPluginInterface $plugin */
      $plugin = $this->extensionManager->createInstance($oauth_extension_plugin_id);
      $extensions[$oauth_extension_plugin_id] = $plugin;
    }
    return $extensions;
  }
```