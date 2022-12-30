<?php

namespace Drupal\Tests\typed_data\Kernel;

use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the placeholder resolver.
 *
 * @group typed_data
 *
 * @coversDefaultClass \Drupal\typed_data\PlaceholderResolver
 */
class PlaceholderResolverTest extends KernelTestBase {

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * A node used for testing.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * An entity type manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The placeholder resolver instanced tested.
   *
   * @var \Drupal\typed_data\PlaceholderResolver
   */
  protected $placeholderResolver;

  /**
   * A simple global context for testing.
   *
   * @var \Drupal\typed_data_global_context_test\ContextProvider\SimpleTestContext
   */
  protected $simpleTestContext;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'typed_data',
    'system',
    'node',
    'field',
    'text',
    'user',
    'typed_data_global_context_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);

    // Make sure default date formats are there for testing the format_date
    // filter.
    $this->installConfig(['system']);

    $this->typedDataManager = $this->container->get('typed_data_manager');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->placeholderResolver = $this->container->get('typed_data.placeholder_resolver');
    $this->simpleTestContext = $this->container->get('typed_data_global_context_test.simple_test_context');

    $this->entityTypeManager->getStorage('node_type')
      ->create(['type' => 'page'])
      ->save();

    // Create a multi-value integer field for testing.
    FieldStorageConfig::create([
      'field_name' => 'field_integer',
      'type' => 'integer',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_integer',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();

    $this->node = $this->entityTypeManager->getStorage('node')
      ->create([
        'title' => 'test',
        'type' => 'page',
      ]);
  }

  /**
   * @covers ::scan
   */
  public function testScanningForPlaceholders() {
    $text = 'token {{example.foo}} and {{example.foo.bar}} just as {{example.foo|default(bar)}} and {{ example.whitespace }}';
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([
      'example' => [
        'foo' => '{{example.foo}}',
        'foo.bar' => '{{example.foo.bar}}',
        'foo|default(bar)' => '{{example.foo|default(bar)}}',
        'whitespace' => '{{ example.whitespace }}',
      ],
    ], $placeholders);
    // Test a simple placeholder with filters only.
    $text = "text {{ date | filter }} text";
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([
      'date' => [
        '| filter' => '{{ date | filter }}',
      ],
    ], $placeholders);
    // Test a simple placeholder with and without a filter.
    $text = "text {{ date | filter }} text {{ date }}";
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([
      'date' => [
        '| filter' => '{{ date | filter }}',
        '' => '{{ date }}',
      ],
    ], $placeholders);
    // Test a compound placeholder with and without a filter.
    $text = "text {{ node.title.value | lower }} text {{ node.title.value }}";
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([
      'node' => [
        'title.value | lower' => '{{ node.title.value | lower }}',
        'title.value' => '{{ node.title.value }}',
      ],
    ], $placeholders);
    // Test a global context variable placeholder.
    $text = "global context variable token {{ @service_id:context.property }}";
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([
      '@service_id:context' => [
        'property' => '{{ @service_id:context.property }}',
      ],
    ], $placeholders);
    // Test a global context variable placeholder with a period in the
    // service id.
    $text = "global context variable token {{ @service.id:context.property }}";
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([
      '@service.id:context' => [
        'property' => '{{ @service.id:context.property }}',
      ],
    ], $placeholders);
  }

  /**
   * @covers ::scan
   */
  public function testEmptyPlaceholders() {
    $text = 'text {{ }} text';
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([
      '' => [
        '' => '{{ }}',
      ],
    ], $placeholders);
  }

  /**
   * @covers ::scan
   */
  public function testNoPlaceholders() {
    $text = 'test text does not have any placeholders';
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([], $placeholders);
  }

  /**
   * @covers ::scan
   */
  public function testMalformedPlaceholders() {
    $text = "text {{ node. title }} text";
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([], $placeholders);

    $text = "text {{ node .title }} text";
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([], $placeholders);

    $text = "text {{node.}} text";
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([], $placeholders);

    $text = "text {{ node| }} text";
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([], $placeholders);

    $text = "text {{ no de }} text";
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([], $placeholders);
  }

  /**
   * @covers ::scan
   */
  public function testFilterOnly() {
    $text = "text {{ |filter }} text";
    $placeholders = $this->placeholderResolver->scan($text);
    $this->assertEquals([
      '' => [
        '|filter' => '{{ |filter }}',
      ],
    ], $placeholders);
  }

  /**
   * @covers ::resolvePlaceholders
   */
  public function testResolvingPlaceholders() {
    // Test resolving multiple tokens.
    $text = 'test {{node.title}} and {{node.title.value}}';
    $result = $this->placeholderResolver->resolvePlaceholders($text, ['node' => $this->node->getTypedData()]);
    $expected = [
      '{{node.title}}' => 'test',
      '{{node.title.value}}' => 'test',
    ];
    $this->assertEquals($expected, $result);

    // Test resolving multiple tokens, one with a filter.
    $this->node->title->value = 'tEsT';
    $result = $this->placeholderResolver->resolvePlaceHolders("test {{ node.title.value | lower }} and {{ node.title.value }}", ['node' => $this->node->getTypedData()]);
    $expected = [
      '{{ node.title.value | lower }}' => 'test',
      '{{ node.title.value }}' => 'tEsT',
    ];
    $this->assertEquals($expected, $result);

    // Test a placeholder without accessing a property.
    $text = 'test {{string}}';
    $result = $this->placeholderResolver->resolvePlaceholders($text, [
      'string' => $this->typedDataManager->create(
      DataDefinition::create('string'), 'replacement'
      ),
    ]);
    $expected = [
      '{{string}}' => 'replacement',
    ];
    $this->assertEquals($expected, $result);

    // Test a global context variable placeholder.
    $text = 'test {{ @typed_data_global_context_test.simple_test_context:dragons }}';
    $context = $this->simpleTestContext->getRuntimeContexts(['dragons']);
    $result = $this->placeholderResolver->resolvePlaceholders($text, [
      '@typed_data_global_context_test.simple_test_context:dragons' => $context['dragons']->getContextData(),
    ]);
    $expected = [
      '{{ @typed_data_global_context_test.simple_test_context:dragons }}' => 'Dragons are better than unicorns!',
    ];
    $this->assertEquals($expected, $result);
  }

  /**
   * @covers ::replacePlaceHolders
   */
  public function testReplacePlaceholders() {
    $text = 'test {{node.title}} and {{node.title.value}}';
    $result = $this->placeholderResolver->replacePlaceHolders($text, ['node' => $this->node->getTypedData()]);
    $this->assertEquals('test test and test', $result);
  }

  /**
   * @covers ::replacePlaceHolders
   */
  public function testPlaceholdersAcrossReferences() {
    $user = $this->entityTypeManager->getStorage('user')
      ->create([
        'name' => 'test',
        'type' => 'user',
      ]);
    $this->node->uid->entity = $user;
    $text = 'test {{node.title}} and {{node.uid.entity.name}}';
    $result = $this->placeholderResolver->replacePlaceHolders($text, ['node' => $this->node->getTypedData()]);
    $this->assertEquals('test test and test', $result);
  }

  /**
   * @covers ::replacePlaceHolders
   */
  public function testPlaceholdersWithMissingData() {
    $text = 'test {{node.title.1.value}}';
    $result = $this->placeholderResolver->replacePlaceHolders($text, ['node' => $this->node->getTypedData()], NULL, []);
    $this->assertEquals('test {{node.title.1.value}}', $result);
    $result = $this->placeholderResolver->replacePlaceHolders($text, ['node' => $this->node->getTypedData()], NULL, ['clear' => FALSE]);
    $this->assertEquals('test {{node.title.1.value}}', $result);
    $result = $this->placeholderResolver->replacePlaceHolders($text, ['node' => $this->node->getTypedData()], NULL, ['clear' => TRUE]);
    $this->assertEquals('test ', $result);
  }

  /**
   * @covers ::replacePlaceHolders
   */
  public function testStringEncoding() {
    $this->node->title->value = '<b>XSS</b>';
    $text = 'test {{node.title}}';
    $result = $this->placeholderResolver->replacePlaceHolders($text, ['node' => $this->node->getTypedData()]);
    $this->assertEquals('test ' . new HtmlEscapedText('<b>XSS</b>'), $result);
  }

  /**
   * @covers ::replacePlaceHolders
   */
  public function testIntegerPlaceholder() {
    $this->node->field_integer->value = 3;
    $text = 'test {{node.field_integer.0.value}}';
    $result = $this->placeholderResolver->replacePlaceHolders($text, ['node' => $this->node->getTypedData()]);
    $this->assertEquals('test 3', $result);
  }

  /**
   * @covers ::replacePlaceHolders
   */
  public function testListPlaceholder() {
    $this->node->field_integer = [1, 2];
    $text = 'test {{node.field_integer}}';
    $result = $this->placeholderResolver->replacePlaceHolders($text, ['node' => $this->node->getTypedData()]);
    $this->assertEquals('test 1, 2', $result);
  }

  /**
   * @covers ::replacePlaceHolders
   */
  public function testApplyingFilters() {
    // Test filter expression.
    $this->node->field_integer = [1, 2, NULL];
    $this->node->title->value = NULL;
    $result = $this->placeholderResolver->replacePlaceHolders("test {{node.field_integer.2.value|default('0')}}", ['node' => $this->node->getTypedData()]);
    $this->assertEquals('test 0', $result);

    // Test piping filter expressions.
    $result = $this->placeholderResolver->replacePlaceHolders("test {{node.title.value|default('tEsT')|lower}}", ['node' => $this->node->getTypedData()]);
    $this->assertEquals('test test', $result);

    // Test piping filter expressions with whitespaces.
    $result = $this->placeholderResolver->replacePlaceHolders("test {{ node.title.value | default('tEsT') | lower }}", ['node' => $this->node->getTypedData()]);
    $this->assertEquals('test test', $result);

    // Test multiple tokens with filters.
    $this->node->title->value = 'tEsT';
    $result = $this->placeholderResolver->replacePlaceHolders("test {{ node.title.value | lower }} and {{ node.title.value }}", ['node' => $this->node->getTypedData()]);
    $this->assertEquals('test test and tEsT', $result);

    // Test a filter expression on data without accessing a property.
    $text = 'test {{string | lower}}';
    $result = $this->placeholderResolver->replacePlaceHolders($text, [
      'string' => $this->typedDataManager->create(DataDefinition::create('string'), 'Replacement'),
    ]);
    $this->assertEquals('test replacement', $result);

    $text = "The year is {{ date | format_date('custom', 'Y') }}.";
    $result = $this->placeholderResolver->replacePlaceHolders($text, [
      'date' => $this->typedDataManager->create(DataDefinition::create('timestamp'), '3600'),
    ]);
    $this->assertEquals('The year is 1970.', $result);
  }

  /**
   * @covers ::replacePlaceHolders
   */
  public function testBubbleableMetadata() {
    // Make sure the bubbleable metadata added by the fetcher is properly passed
    // though.
    $bubbleable_metadata = new BubbleableMetadata();
    // Save the node, so it gets a cache tag.
    $this->node->save();
    $this->placeholderResolver->replacePlaceHolders('test {{node.field_integer}}', ['node' => $this->node->getTypedData()], $bubbleable_metadata);
    $expected = ['node:' . $this->node->id()];
    $this->assertEquals($expected, $bubbleable_metadata->getCacheTags());

    // Ensure cache tags of filters are added in.
    $bubbleable_metadata = new BubbleableMetadata();
    $this->placeholderResolver->replacePlaceHolders("test {{ node.created.value | format_date('medium') }}", ['node' => $this->node->getTypedData()], $bubbleable_metadata);
    $expected = Cache::mergeTags(['node:' . $this->node->id()], DateFormat::load('medium')->getCacheTags());
    $this->assertEquals($expected, $bubbleable_metadata->getCacheTags());
  }

  /**
   * @covers ::replacePlaceHolders
   */
  public function testGlobalContextVariable() {
    $text = 'test {{ @typed_data_global_context_test.simple_test_context:dragons }}';
    $context = $this->simpleTestContext->getRuntimeContexts(['dragons']);
    $result = $this->placeholderResolver->replacePlaceHolders($text, [
      '@typed_data_global_context_test.simple_test_context:dragons' => $context['dragons']->getContextData(),
    ]);
    $this->assertEquals('test Dragons are better than unicorns!', $result);
  }

}
