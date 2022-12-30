<?php

namespace Drupal\Tests\select2\Unit\Element;

use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\select2\Element\Select2;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\select2\Element\Select2
 * @group select2
 */
class Select2Test extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $language = $this->createMock('Drupal\Core\Language\LanguageInterface');
    $language->expects($this->any())
      ->method('getDirection')
      ->will($this->returnValue('rtl'));
    $language->method('getId')
      ->will($this->returnValue('en'));

    $language_manager = $this->createMock('Drupal\Core\Language\LanguageManagerInterface');
    $language_manager->expects($this->any())
      ->method('getCurrentLanguage')
      ->will($this->returnValue($language));

    $theme = $this->createMock('Drupal\Core\Theme\ActiveTheme');
    $theme->expects($this->any())
      ->method('getName')
      ->will($this->returnValue('seven'));

    $theme_manager = $this->createMock('Drupal\Core\Theme\ThemeManagerInterface');
    $theme_manager->expects($this->any())
      ->method('getActiveTheme')
      ->will($this->returnValue($theme));

    $library_discovery = $this->createMock('Drupal\Core\Asset\LibraryDiscoveryInterface');
    $library_discovery->expects($this->any())
      ->method('getLibraryByName')
      ->will($this->returnValue(TRUE));

    $string_translation = $this->createMock('Drupal\Core\StringTranslation\TranslationManager');

    $container = new ContainerBuilder();
    $container->set('language_manager', $language_manager);
    $container->set('theme.manager', $theme_manager);
    $container->set('library.discovery', $library_discovery);
    $container->set('string_translation', $string_translation);

    \Drupal::setContainer($container);
  }

  /**
   * @covers ::preRenderSelect
   *
   * @dataProvider providerTestPreRenderSelect
   */
  public function testPreRenderSelect($multiple, $required, $settings, $expected) {
    $element = [
      '#name' => 'field_foo',
      '#options' => [],
      '#multiple' => $multiple,
      '#required' => $required,
      '#attributes' => ['data-drupal-selector' => 'field-foo'],
      '#autocreate' => [],
      '#autocomplete' => FALSE,
      '#cardinality' => 0,
      '#empty_value' => '',
      '#select2' => $settings,
    ];

    $element = Select2::preRenderSelect($element);
    $element = Select2::preRenderAutocomplete($element);
    $element = Select2::preRenderOverwrites($element);
    $this->assertEquals($expected, array_intersect_key($element['#attributes'], $expected));
  }

  /**
   * Data provider for testPreRenderSelect().
   */
  public function providerTestPreRenderSelect() {
    $data = [];
    $data[] = [TRUE, TRUE, [],
      [
        'multiple' => 'multiple',
        'name' => 'field_foo[]',
        'data-select2-config' => Json::encode([
          'multiple' => TRUE,
          'placeholder' => ['id' => '', 'text' => ''],
          'allowClear' => FALSE,
          'dir' => 'rtl',
          'language' => 'en',
          'tags' => FALSE,
          'theme' => 'seven',
          'maximumSelectionLength' => 0,
          'tokenSeparators' => [],
          'selectOnClose' => FALSE,
          'width' => '100%',
        ]),
      ],
    ];
    $data[] = [FALSE, TRUE, [],
      [
        'name' => 'field_foo',
        'data-select2-config' => Json::encode([
          'multiple' => FALSE,
          'placeholder' => ['id' => '', 'text' => ''],
          'allowClear' => FALSE,
          'dir' => 'rtl',
          'language' => 'en',
          'tags' => FALSE,
          'theme' => 'seven',
          'maximumSelectionLength' => 0,
          'tokenSeparators' => [],
          'selectOnClose' => FALSE,
          'width' => '100%',
        ]),
      ],
    ];
    $data[] = [TRUE, FALSE, [],
      [
        'multiple' => 'multiple',
        'name' => 'field_foo[]',
        'data-select2-config' => Json::encode([
          'multiple' => TRUE,
          'placeholder' => ['id' => '', 'text' => ''],
          'allowClear' => FALSE,
          'dir' => 'rtl',
          'language' => 'en',
          'tags' => FALSE,
          'theme' => 'seven',
          'maximumSelectionLength' => 0,
          'tokenSeparators' => [],
          'selectOnClose' => FALSE,
          'width' => '100%',
        ]),
      ],
    ];
    $data[] = [FALSE, FALSE, [],
      [
        'name' => 'field_foo',
        'data-select2-config' => Json::encode([
          'multiple' => FALSE,
          'placeholder' => ['id' => '', 'text' => ''],
          'allowClear' => TRUE,
          'dir' => 'rtl',
          'language' => 'en',
          'tags' => FALSE,
          'theme' => 'seven',
          'maximumSelectionLength' => 0,
          'tokenSeparators' => [],
          'selectOnClose' => FALSE,
          'width' => '100%',
        ]),
      ],
    ];
    // Test overwriting of the default setting.
    $data[] = [FALSE, FALSE, ['allowClear' => FALSE, 'multiple' => TRUE],
      [
        'name' => 'field_foo',
        'data-select2-config' => Json::encode([
          'multiple' => TRUE,
          'placeholder' => ['id' => '', 'text' => ''],
          'allowClear' => FALSE,
          'dir' => 'rtl',
          'language' => 'en',
          'tags' => FALSE,
          'theme' => 'seven',
          'maximumSelectionLength' => 0,
          'tokenSeparators' => [],
          'selectOnClose' => FALSE,
          'width' => '100%',
        ]),
      ],
    ];

    return $data;
  }

  /**
   * Checks #placeholder property.
   *
   * @dataProvider providerTestPlaceholderPropertyRendering
   */
  public function testPlaceholderPropertyRendering($required, $empty_option, $empty_value, $placeholder, $expected) {
    $element = [
      '#name' => 'field_foo',
      '#options' => [],
      '#autocreate' => [],
      '#multiple' => FALSE,
      '#required' => $required,
      '#autocomplete' => FALSE,
      '#empty_value' => $empty_value,
      '#empty_option' => $empty_option,
      '#attributes' => ['data-drupal-selector' => 'field-foo'],
      '#placeholder' => $placeholder,
      '#select2' => [],
    ];

    $element = Select2::preRenderSelect($element);
    $element = Select2::preRenderAutocomplete($element);

    $placeholder = $element['#attributes']['data-select2-config']['placeholder'];

    $this->assertSame($expected['id'], $placeholder['id']);
    $this->assertEquals($expected['text'], $placeholder['text']->getUntranslatedString());
  }

  /**
   * Data provider for testPlaceholderPropertyRendering().
   */
  public function providerTestPlaceholderPropertyRendering() {
    $data = [];
    $data[] = [TRUE, '', '', '',
      ['id' => '', 'text' => '- Select -'],
    ];
    $data[] = [FALSE, '', '', '',
      ['id' => '', 'text' => '- None -'],
    ];
    $data[] = [FALSE, NULL, NULL, NULL,
      ['id' => '', 'text' => '- None -'],
    ];
    $data[] = [FALSE, new TranslatableMarkup('empty_option'), NULL, NULL,
      ['id' => '', 'text' => 'empty_option'],
    ];
    $data[] = [FALSE, new TranslatableMarkup('empty_option'), NULL, new TranslatableMarkup('placeholder'),
      ['id' => '', 'text' => 'placeholder'],
    ];
    $data[] = [FALSE, NULL, NULL, new TranslatableMarkup('placeholder'),
      ['id' => '', 'text' => 'placeholder'],
    ];
    $data[] = [FALSE, NULL, 'foo', new TranslatableMarkup('placeholder'),
      ['id' => 'foo', 'text' => 'placeholder'],
    ];
    $data[] = [TRUE, NULL, 'foo', new TranslatableMarkup('placeholder'),
      ['id' => 'foo', 'text' => 'placeholder'],
    ];
    return $data;
  }

}
