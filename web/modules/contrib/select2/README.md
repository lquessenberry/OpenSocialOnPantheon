# Drupal 8 - Select2
This is a [select2](https://select2.org/) integration for Drupal 8. It provides a render element (for usage in forms) and two field widgets.
One for simple select fields and another for entity reference fields.

The render element supports several select2 features:

* Single and multiple selection
* Internationalization
* Integrates nicely with the seven theme

When the field widget is used in the entity reference context this module provides several features:

* Autocomplete: The select options will not be rendered in the page and instead fetched by API during typing.
* Autocreate: Like core's entity reference field this widget can create new entities on the fly.

## Dependencies
* [Select2 library](https://select2.org/) (>=4.0.x)

## Installation
Install this module like every other Drupal module. Also it's needed to get the select2 library.

### Composer (recommended)
If you would like to install the select2 library with composer, you probably used the [drupal composer template](https://github.com/drupal-composer/drupal-project) to setup your project. It's recommended to use [asset-packagist]() to install JavaScript libraries. So you will need to add the following to your composer.json file into the repositories section:

```json
    {
        "type": "composer",
        "url": "https://asset-packagist.org"
    }
```

It's also needed to extend the 'installer-path' section:

```json
    "web/libraries/{$name}": [
        "type:drupal-library",
        "type:bower-asset",
        "type:npm-asset"
    ],
```
And add a new 'installer-types' section next to the 'installer-path' in the 'extra' section:

```json
    "installer-types": ["bower-asset", "npm-asset"],
```

After this you can install the library with "composer require oomphinc/composer-installers-extender npm-asset/select2" and the library will be downloaded into the libraries folder.

### Manual
Download it from the [release page](https://github.com/select2/select2/releases) and place it in Drupal's library folder.

## Usage
### Render element
You can use the select2 element in your forms like every other form element (see [Form and render elements](https://api.drupal.org/api/drupal/elements)).

```php
    $form['select2'] = [
      '#type' => 'select2',
      '#title' => t('My select2 form element'),
      '#options' => ['foo', 'bar'],
    ];
```

The select2 element sets useful defaults for the [select2 configuration options](https://select2.org/configuration/options-api).
Nevertheless you are able to override them:

```php
    $form['select2'] = [
      '#type' => 'select2',
      '#title' => t('My select2 form element'),
      '#options' => ['foo', 'bar'],
      '#select2' => [
        'allowClear' => FALSE,
      ],
    ];
```

### Themes
The select2 library provides the possibility to use your own [theme](https://select2.org/appearance#themes) for the select2 widgets.  If you have your own theme, you can customize the appearance of the select2 widget:

Add a 'select2.theme' entry to the libraries.yml of your theme. This module will use your defined theme automatically.

```php
    select2.theme:
      css:
        component:
          css/my-select2-theme.css: {}
```

To start with your own select2 theme, it's recommended to copy all style definitions with a '--default' from the select2/dist/css/select2.css
file into your my-select2-theme.css file. Then replace '--default' with '--my_theme' (machine name of your theme) and start customizing.

Note: This module already provides a theme for Drupal's seven theme.
