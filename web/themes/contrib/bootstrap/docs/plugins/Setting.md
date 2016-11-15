<!-- @file Documentation for the @BootstrapSetting annotated discovery plugin. -->
<!-- @defgroup -->
<!-- @ingroup -->
# @BootstrapSetting

- [Create a plugin](#create)
- [Rebuild the cache](#rebuild)
- [Public Methods](#methods)

## Create a plugin {#create}

We will use `SkipLink` as our first `@BootstrapSetting` plugin to create. In
this example we want our sub-theme to specify a different skip link anchor id
to change in the Theme Settings interface altering the default of
`#main-content`.

Replace all of the following instances of `THEMENAME` with the actual machine
name of your sub-theme.

Create a file at `./THEMENAME/src/Plugin/Setting/THEMENAME/Accessibility/SkipLink.php`
with the following contents:

```php
namespace Drupal\THEMENAME\Plugin\Setting\THEMENAME\Accessibility\SkipLink;

use Drupal\bootstrap\Annotation\BootstrapSetting;
use Drupal\bootstrap\Plugin\Setting\SettingBase;
use Drupal\Core\Annotation\Translation;

/**
 * The "THEMENAME_skip_link_id" theme setting.
 *
 * @ingroup plugins_setting
 *
 * @BootstrapSetting(
 *   id = "THEMENAME_skip_link_id",
 *   type = "textfield",
 *   title = @Translation("Anchor ID for the ""skip link"""),
 *   defaultValue = "main-content",
 *   description = @Translation("Specify the HTML ID of the element that the accessible-but-hidden ""skip link"" should link to. (<a href="":link"" target=""_blank"">Read more about skip links</a>.)",
     arguments = { ":link"  = "http://drupal.org/node/467976" }),
 *   groups = {
 *     "THEMENAME" = "THEMETITLE",
 *     "accessibility" = @Translation("Accessibility"),
 *   },
 * )
 */
class SkipLink extends SettingBase {}
```

Helpfully Bootstrap adds a global `theme` variable added to every template
in `Bootstrap::preprocess()`.

This variable can now simply be called in the `html.html.twig` file with the
following contents:

```twig
<a href="#{{ theme.settings.THEMENAME_skip_link_id }}" class="visually-hidden focusable skip-link">
  {{ 'Skip to main content'|t }}
</a>
```

In addition, the `page.html.twig` file will also need to be adjusted for this to
work properly with the new anchor id.

```twig
<a id="{{ theme.settings.THEMENAME_skip_link_id }}"></a>
```

## Rebuild the cache {#rebuild}

Once you have saved, you must rebuild your cache for this new plugin to be
discovered. This must happen anytime you make a change to the actual file name
or the information inside the `@BootstrapSetting` annotation.

To rebuild your cache, navigate to `admin/config/development/performance` and
click the `Clear all caches` button. Or if you prefer, run `drush cr` from the
command line.

Voilà! After this, you should have a fully functional `@BootstrapSetting` plugin!

## Public Methods {#methods}

Now that we covered how to create a basic `@BootstrapSetting` plugin, we can
discuss how to customize a setting to fulfill a range of requirements.

The `@BootstrapSetting` is implemented through the base class `SettingBase`
which provides a variety of public methods to assist in the customization of
a plugin.

#### SettingBase::alterForm

This method provides a way for you to alter the form render array as well as the
$formState object of the `@BootstrapSetting`.

For example, the CDNProvider::alterForm() provides functionality to
automatically create groupings for the different CDN providers as well as
providing helpful introductory text.

Another more in-depth example is RegionWells::alterForm() which helps to
provide configuration for specifying a custom "well" class to apply to a Region.
Interestingly this plugin creates dynamic well settings for every defined region
to assist in fine grained customization.

```php
public function alterForm(array &$form, FormStateInterface $form_state, $form_id = NULL) {
  parent::alterForm($form, $form_state, $form_id);

  $setting = $this->getElement($form, $form_state);

  // Retrieve the current default values.
  $default_values = $setting->getProperty('default_value', $this->getDefaultValue());

  $wells = [
    '' => t('None'),
    'well' => t('.well (normal)'),
    'well well-sm' => t('.well-sm (small)'),
    'well well-lg' => t('.well-lg (large)'),
  ];
  // Create dynamic well settings for each region.
  $regions = system_region_list($this->theme->getName());
  foreach ($regions as $name => $title) {
    if (in_array($name, ['page_top', 'page_bottom'])) {
      continue;
    }
    $setting->{'region_well-' . $name} = [
      '#title' => $title,
      '#type' => 'select',
      '#attributes' => [
        'class' => ['input-sm'],
      ],
      '#options' => $wells,
      '#default_value' => isset($default_values[$name]) ? $default_values[$name] : '',
    ];
  }
}
```

#### SettingBase::drupalSettings

This method provides a way for you to determine whether a theme setting should
be added to the `drupalSettings` javascript variable. Please note that by
default this is set to false to prevent leaked information from being exposed.

```php
public function drupalSettings() {
  return FALSE;
}
```

#### SettingBase::getCacheTags

This method provides a way for you to add cache tags that when the instantiated
class is modified the associated cache tags will be invalidated. This is
incredibly useful for example with CDNCustomCss::getCacheTags() which returns an
array of `library_info`. So when a CdnProvider::getCacheTags() instantiated
plugin changes the `library_info` cache tag will be invalidated automatically.

It is important to note that the invalidation occurs because the base theme
loads external resources using libraries by altering the libraries it defines
based on settings in LibraryInfo::alter().

```php
public function getCacheTags() {
  return ['library_info'];
}
```

#### SettingBase::getElement

This method provides a way for you to retrieve the form element that was
automatically generated by the base theme; based on the plugin definition.

#### SettingBase::getGroup

This method provides a way for you to retrieve the last group (fieldset /
details form element), as defined by the groups plugin definition the setting
lives in. You can also perform other operations such as setting properties based
on the returned group such as which group should be open by default.

#### SettingBase::getGroups

This method retrieves the associative array of groups as defined in the plugin
definition. It's keyed by the group machine name and it's value is the
translatable label.

```php
public function getGroups() {
  return !empty($this->pluginDefinition['groups']) ? $this->pluginDefinition['groups'] : [];
}
```

#### SettingBase::submitForm

This method provides a way for you alter the submitted values stored in the
$formState before the setting's value is stored in configuration. This is
performed automatically for you by the base theme.

Additionally this method can also provide functionality such that after a user
has clicked the "Save configuration" button an additional message to the user
would be displayed based on a value.

An example is illustrated below where the RegionWells::submitForm method will
extract all regions with individual dynamic settings by checking if
`/^region_well-/` exists in any of the values.

```php
public static function submitForm(array &$form, FormStateInterface $form_state, $form_id = NULL) {
  $values = $form_state->getValues();

  // Extract the regions from individual dynamic settings.
  $regex = '/^region_well-/';
  $region_wells = [];
  foreach ($values as $key => $value) {
    if (!preg_match($regex, $key)) {
      continue;
    }
    $region_wells[preg_replace($regex, '', $key)] = $value;
    unset($values[$key]);
  }

  // Store the new values.
  $values['region_wells'] = $region_wells;
  $form_state->setValues($values);
}
```

#### SettingBase::validateForm

This method provides a way for you to validate a form. This can be based on the
values submitted or a variety of other conditions. This method could, for
instance, be useful when a custom CDN provider were to be added. The
`validateForm` could check that the new CDN provider is the correct version and
the proper location has been given.
