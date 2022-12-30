<?php

namespace Drupal\Tests\typed_data\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;

/**
 * Tests using typed data filters.
 *
 * @group typed_data
 *
 * @coversDefaultClass \Drupal\typed_data\DataFilterManager
 */
class DataFilterTest extends KernelTestBase {

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * The data filter manager.
   *
   * @var \Drupal\typed_data\DataFilterManagerInterface
   */
  protected $dataFilterManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'filter',
    'node',
    'system',
    'typed_data',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->typedDataManager = $this->container->get('typed_data_manager');
    $this->dataFilterManager = $this->container->get('plugin.manager.typed_data_filter');

    // Make sure default date formats are available
    // for testing the format_date filter.
    $this->installConfig(['system', 'filter']);

    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    // Set up the filter formats used by this test.
    $basic_html_format = FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'Basic HTML',
      'filters' => [
        'filter_html' => [
          'status' => 1,
          'settings' => [
            'allowed_html' => '<p> <br> <strong> <a> <em> <code>',
          ],
        ],
      ],
    ]);
    $basic_html_format->save();

    $full_html_format = FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 1,
      'filters' => [],
    ]);
    $full_html_format->save();
  }

  /**
   * Tests the operation of the 'count' data filter.
   *
   * @covers \Drupal\typed_data\Plugin\TypedDataFilter\CountFilter
   */
  public function testCountFilter() {
    $filter = $this->dataFilterManager->createInstance('count');
    $data = $this->typedDataManager->create(DataDefinition::create('string'), 'No one shall speak to the Man at the Helm.');

    $this->assertTrue($filter->canFilter($data->getDataDefinition()));
    $this->assertFalse($filter->canFilter(DataDefinition::create('any')));

    $this->assertEquals('integer', $filter->filtersTo($data->getDataDefinition(), [])->getDataType());

    $this->assertEquals(42, $filter->filter($data->getDataDefinition(), $data->getValue(), []));
  }

  /**
   * @covers \Drupal\typed_data\Plugin\TypedDataFilter\LowerFilter
   */
  public function testLowerFilter() {
    $filter = $this->dataFilterManager->createInstance('lower');
    $data = $this->typedDataManager->create(DataDefinition::create('string'), 'tEsT');

    $this->assertTrue($filter->canFilter($data->getDataDefinition()));
    $this->assertFalse($filter->canFilter(DataDefinition::create('any')));

    $this->assertEquals('string', $filter->filtersTo($data->getDataDefinition(), [])->getDataType());

    $this->assertEquals('test', $filter->filter($data->getDataDefinition(), $data->getValue(), []));
  }

  /**
   * Tests the operation of the 'replace' data filter.
   *
   * @covers \Drupal\typed_data\Plugin\TypedDataFilter\ReplaceFilter
   */
  public function testReplaceFilter() {
    $filter = $this->dataFilterManager->createInstance('replace');
    $data = $this->typedDataManager->create(DataDefinition::create('string'), 'Text with mispeling to correct');

    $this->assertTrue($filter->canFilter($data->getDataDefinition()));
    $this->assertFalse($filter->canFilter(DataDefinition::create('any')));

    $this->assertEquals('string', $filter->filtersTo($data->getDataDefinition(), [])->getDataType());

    // Verify that two arguments are required.
    $fails = $filter->validateArguments($data->getDataDefinition(), [/* No arguments given */]);
    $this->assertCount(1, $fails);
    $this->assertStringContainsString('Missing arguments for filter', (string) $fails[0]);

    $fails = $filter->validateArguments($data->getDataDefinition(), [new \stdClass(), new \stdClass()]);
    $this->assertCount(2, $fails);
    $this->assertEquals('This value should be of the correct primitive type.', $fails[0]);
    $this->assertEquals('This value should be of the correct primitive type.', $fails[1]);

    $this->assertEquals('Text with misspelling to correct', $filter->filter($data->getDataDefinition(), $data->getValue(), ['mispeling', 'misspelling']));
  }

  /**
   * Tests the operation of the 'trim' data filter.
   *
   * @covers \Drupal\typed_data\Plugin\TypedDataFilter\TrimFilter
   */
  public function testTrimFilter() {
    $filter = $this->dataFilterManager->createInstance('trim');
    $data = $this->typedDataManager->create(DataDefinition::create('string'), ' Text with whitespace ');

    $this->assertTrue($filter->canFilter($data->getDataDefinition()));
    $this->assertFalse($filter->canFilter(DataDefinition::create('any')));

    $this->assertEquals('string', $filter->filtersTo($data->getDataDefinition(), [])->getDataType());

    $this->assertEquals('Text with whitespace', $filter->filter($data->getDataDefinition(), $data->getValue(), []));
  }

  /**
   * Tests the operation of the 'upper' data filter.
   *
   * @covers \Drupal\typed_data\Plugin\TypedDataFilter\UpperFilter
   */
  public function testUpperFilter() {
    $filter = $this->dataFilterManager->createInstance('upper');
    $data = $this->typedDataManager->create(DataDefinition::create('string'), 'tEsT');

    $this->assertTrue($filter->canFilter($data->getDataDefinition()));
    $this->assertFalse($filter->canFilter(DataDefinition::create('any')));

    $this->assertEquals('string', $filter->filtersTo($data->getDataDefinition(), [])->getDataType());

    $this->assertEquals('TEST', $filter->filter($data->getDataDefinition(), $data->getValue(), []));
  }

  /**
   * @covers \Drupal\typed_data\Plugin\TypedDataFilter\DefaultFilter
   */
  public function testDefaultFilter() {
    $filter = $this->dataFilterManager->createInstance('default');
    $data = $this->typedDataManager->create(DataDefinition::create('string'));

    $this->assertTrue($filter->canFilter($data->getDataDefinition()));
    $this->assertFalse($filter->canFilter(DataDefinition::create('any')));

    $this->assertSame($data->getDataDefinition(), $filter->filtersTo($data->getDataDefinition(), ['default']));

    $fails = $filter->validateArguments($data->getDataDefinition(), []);
    $this->assertCount(1, $fails);
    $this->assertStringContainsString('Missing arguments', (string) $fails[0]);
    $fails = $filter->validateArguments($data->getDataDefinition(), [new \stdClass()]);
    $this->assertCount(1, $fails);
    $this->assertEquals('This value should be of the correct primitive type.', $fails[0]);

    $this->assertEquals('default', $filter->filter($data->getDataDefinition(), $data->getValue(), ['default']));
    $data->setValue('non-default');
    $this->assertEquals('non-default', $filter->filter($data->getDataDefinition(), $data->getValue(), ['default']));
  }

  /**
   * @covers \Drupal\typed_data\Plugin\TypedDataFilter\FormatDateFilter
   */
  public function testFormatDateFilter() {
    $filter = $this->dataFilterManager->createInstance('format_date');
    $data = $this->typedDataManager->create(DataDefinition::create('timestamp'), 3700);

    $this->assertTrue($filter->canFilter($data->getDataDefinition()));
    $this->assertFalse($filter->canFilter(DataDefinition::create('any')));

    $this->assertEquals('string', $filter->filtersTo($data->getDataDefinition(), [])->getDataType());

    $fails = $filter->validateArguments($data->getDataDefinition(), []);
    $this->assertCount(0, $fails);
    $fails = $filter->validateArguments($data->getDataDefinition(), ['medium']);
    $this->assertCount(0, $fails);
    $fails = $filter->validateArguments($data->getDataDefinition(), ['invalid-format']);
    $this->assertCount(1, $fails);
    $fails = $filter->validateArguments($data->getDataDefinition(), ['custom']);
    $this->assertCount(1, $fails);
    $fails = $filter->validateArguments($data->getDataDefinition(), ['custom', 'Y']);
    $this->assertCount(0, $fails);

    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = $this->container->get('date.formatter');
    $this->assertEquals($date_formatter->format(3700), $filter->filter($data->getDataDefinition(), $data->getValue(), []));
    $this->assertEquals($date_formatter->format(3700, 'short'), $filter->filter($data->getDataDefinition(), $data->getValue(), ['short']));
    $this->assertEquals('1970', $filter->filter($data->getDataDefinition(), $data->getValue(), ['custom', 'Y']));

    // Verify the filter works with non-timestamp data as well.
    $data = $this->typedDataManager->create(DataDefinition::create('datetime_iso8601'), "1970-01-01T10:10:10+00:00");
    $this->assertTrue($filter->canFilter($data->getDataDefinition()));
    $this->assertEquals('string', $filter->filtersTo($data->getDataDefinition(), [])->getDataType());
    $this->assertEquals('1970', $filter->filter($data->getDataDefinition(), $data->getValue(), ['custom', 'Y']));

    // Test cache dependencies of date format config entities are added in.
    $metadata = new BubbleableMetadata();
    $filter->filter($data->getDataDefinition(), $data->getValue(), ['short'], $metadata);
    $this->assertEquals(DateFormat::load('short')->getCacheTags(), $metadata->getCacheTags());
    $metadata = new BubbleableMetadata();
    $filter->filter($data->getDataDefinition(), $data->getValue(), ['custom', 'Y'], $metadata);
    $this->assertEquals([], $metadata->getCacheTags());
  }

  /**
   * @covers \Drupal\typed_data\Plugin\TypedDataFilter\FormatTextFilter
   */
  public function testFormatTextFilter() {
    $filter = $this->dataFilterManager->createInstance('format_text');
    $data = $this->typedDataManager->create(DataDefinition::create('string'), '<b>Test <em>format_text</em> filter with <code>full_html</code> plugin</b>');

    $this->assertTrue($filter->canFilter($data->getDataDefinition()));
    $this->assertFalse($filter->canFilter(DataDefinition::create('any')));

    $this->assertEquals('string', $filter->filtersTo($data->getDataDefinition(), ['full_html'])->getDataType());

    $fails = $filter->validateArguments($data->getDataDefinition(), []);
    $this->assertCount(1, $fails);
    $this->assertStringContainsString('Missing arguments', (string) $fails[0]);
    $fails = $filter->validateArguments($data->getDataDefinition(), [new \stdClass()]);
    $this->assertCount(1, $fails);
    $this->assertEquals('This value should be of the correct primitive type.', $fails[0]);

    $this->assertEquals(
      '<b>Test <em>format_text</em> filter with <code>full_html</code> plugin</b>',
      $filter->filter($data->getDataDefinition(), $data->getValue(), ['full_html'])
    );

    $data->setValue('<b>Test <em>format_text</em> filter with <code>basic_html</code> plugin</b>');
    $this->assertEquals(
      'Test <em>format_text</em> filter with <code>basic_html</code> plugin',
      $filter->filter($data->getDataDefinition(), $data->getValue(), ['basic_html'])
    );

    // Test the fallback filter.
    $data->setValue('<b>Test <em>format_text</em> filter with <code>plain_text</code> plugin</b>');
    $this->assertEquals(
      "<p>&lt;b&gt;Test &lt;em&gt;format_text&lt;/em&gt; filter with &lt;code&gt;plain_text&lt;/code&gt; plugin&lt;/b&gt;</p>\n",
      $filter->filter($data->getDataDefinition(), $data->getValue(), ['plain_text'])
    );
  }

  /**
   * @covers \Drupal\typed_data\Plugin\TypedDataFilter\StripTagsFilter
   */
  public function testStripTagsFilter() {
    $filter = $this->dataFilterManager->createInstance('striptags');
    $data = $this->typedDataManager->create(DataDefinition::create('string'), '<b>Test <em>striptags</em> filter</b>');

    $this->assertTrue($filter->canFilter($data->getDataDefinition()));
    $this->assertFalse($filter->canFilter(DataDefinition::create('any')));

    $this->assertEquals('string', $filter->filtersTo($data->getDataDefinition(), [])->getDataType());

    $this->assertEquals('Test striptags filter', $filter->filter($data->getDataDefinition(), $data->getValue(), []));
  }

  /**
   * @covers \Drupal\typed_data\Plugin\TypedDataFilter\EntityUrlFilter
   */
  public function testEntityUrlFilter() {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'title' => 'Test node',
      'type' => 'page',
    ]);
    $node->save();

    $filter = $this->dataFilterManager->createInstance('entity_url');
    $data = $this->typedDataManager->create(EntityDataDefinition::create('node'));
    $data->setValue($node);

    $this->assertTrue($filter->canFilter($data->getDataDefinition()));
    $this->assertFalse($filter->canFilter(DataDefinition::create('any')));

    $this->assertEquals('uri', $filter->filtersTo($data->getDataDefinition(), [])->getDataType());

    // Test the output of the filter.
    $output = $filter->filter($data->getDataDefinition(), $data->getValue(), []);
    $this->assertEquals($node->toUrl('canonical', ['absolute' => TRUE])->toString(), $output);
  }

  /**
   * @covers \Drupal\typed_data\Plugin\TypedDataFilter\EntityUrlFilter
   */
  public function testFileEntityUrlFilter() {
    file_put_contents('public://example.txt', $this->randomMachineName());
    /** @var \Drupal\file\FileInterface $file */
    $file = File::create([
      'uri' => 'public://example.txt',
    ]);
    $file->save();

    $filter = $this->dataFilterManager->createInstance('entity_url');
    $data = $this->typedDataManager->create(EntityDataDefinition::create('file'));
    $data->setValue($file);

    $this->assertTrue($filter->canFilter($data->getDataDefinition()));
    $this->assertFalse($filter->canFilter(DataDefinition::create('any')));

    $this->assertEquals('uri', $filter->filtersTo($data->getDataDefinition(), [])->getDataType());

    // Test the output of the filter.
    $output = $filter->filter($data->getDataDefinition(), $data->getValue(), []);
    $this->assertEquals($file->createFileUrl(FALSE), $output);
  }

}
