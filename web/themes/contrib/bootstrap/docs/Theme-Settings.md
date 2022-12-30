<!-- @file Overview of theme settings for Drupal Bootstrap based themes. -->
<!-- @defgroup -->
<!-- @ingroup -->
# Theme Settings

Drupal 8 introduced the [config system](https://www.drupal.org/documentation/administer/config).

Theme settings have now become quite more complex due to how and where they are
stored and at what point in the process they are accessed.

There are essentially four places where theme settings do or could reside:

1. **Install Config** - `./themes/THEMENAME/config/install/THEMENAME.settings.yml`  
   This is the install config only. They will only be set upon the initial
   installation of a theme. This is **not** like previous Drupal implementations
   where changes made here are reflected after a cache rebuild. The only way
   to make changes made to this file be used after a theme has been installed
   is to completely uninstall and reinstall the theme. To supply default values
   when a theme is installed, create the file named above and add the following:
   ```yaml
   # Install settings (these are only set once). 
   
   SETTING_NAME: SETTING_VALUE
   ```
2. **Exported Config** - `./CONFIG_DIR/THEMENAME.settings.yml`  
   This is where theme settings are exported. The `CONFIG_DIR` is usually a
   directory located either just inside or outside the `DOCROOT` of the site.
   You can read more about this in the link above. This file is automatically
   generated; **DO NOT EDIT MANUALLY**.
3. **Active Config** - `(Database)`  
   Located in both the `config` and `cache_config` tables there will be an entry
   named `THEMENAME.settings`. This is where the "active" config is stored.
   These database entries are automatically generated; **DO NOT EDIT MANUALLY**.
4. **Overridden Config** - `./DOCROOT/sites/default/settings[.local].php`  
   This is your site's `settings[.local].php` file. Despite its path/filename,
   anything stored in the `$config` variable does not supply default values.
   These values actually override any exported or active config. While it is
   technically possible to specify your config based theme settings here, it is
   important to remember that this file's main purpose is to supply
   environmental specific `$database` and `$settings` values (e.g. local,
   stage, prod, etc.); not config. Its use to store config based theme settings
   of any kind here is highly discouraged and not supported by this project.
   While not an exception, it is important to note that this base-theme does
   support various theme specific `$settings` values, which are not the same as
   or to be confused with the config based theme settings (read more below).
   
If you are migrating from older versions of Drupal and need help wrapping your
head around the config paradigm shift, think of "Active Config" as the new
".info" file, but specifically for your theme's settings. Because this is config
though, you don't edit it manually. Instead, you should navigate to your theme's
settings UI in the browser, make and save the desired changes, and then export
your config. Your new theme settings will appear in the exported config
directory.

If you need to programmatically access or modify a theme's settings, it's best
to use this base-theme's APIs. To retrieve a theme setting, use:
\Drupal\bootstrap\Theme::getSetting(). To set a theme setting, use:
\Drupal\bootstrap\Theme::setSetting():

```php
<?php
use Drupal\bootstrap\Bootstrap;
$theme = Bootstrap::getTheme('THEMENAME');

// Retrieve a theme setting.
$theme->getSetting('my_setting', 'a default value');

// Set a theme setting (saved to config automatically).
$theme->setSetting('my_setting', 'a new value');
```
<!-- THEME SETTINGS GENERATION START -->

---

### General > Buttons

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="button-colorize" data-anchor="true">button_colorize</span>
      </td>
      <td>
        <div class="help-block">Adds classes to buttons based on their text value.</div>
        <pre class="language-yaml"><code>button_colorize: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="button-iconize" data-anchor="true">button_iconize</span>
      </td>
      <td>
        <div class="help-block">Adds icons to buttons based on the text value</div>
        <pre class="language-yaml"><code>button_iconize: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="button-size" data-anchor="true">button_size</span>
      </td>
      <td>
        <div class="help-block">Defines the Bootstrap Buttons specific size</div>
        <pre class="language-yaml"><code>button_size: ''</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### General > Container

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="fluid-container" data-anchor="true">fluid_container</span>
      </td>
      <td>
        <div class="help-block">Uses the <code>.container-fluid</code> class instead of <code>.container</code>.</div>
        <pre class="language-yaml"><code>fluid_container: 0</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### General > Forms

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="forms-has-error-value-toggle" data-anchor="true">forms_has_error_value_toggle</span>
      </td>
      <td>
        <div class="help-block">If an element has a <code>.has-error</code> class attached to it, enabling this will automatically remove that class when a value is entered.</div>
        <pre class="language-yaml"><code>forms_has_error_value_toggle: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="forms-required-has-error" data-anchor="true">forms_required_has_error</span>
      </td>
      <td>
        <div class="help-block">If an element in a form is required, enabling this will always display the element with a <code>.has-error</code> class. This turns the element red and helps in usability for determining which form elements are required to submit the form.</div>
        <pre class="language-yaml"><code>forms_required_has_error: 0</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="forms-smart-descriptions" data-anchor="true">forms_smart_descriptions</span>
      </td>
      <td>
        <div class="help-block">Convert descriptions into tooltips (must be enabled) automatically based on certain criteria. This helps reduce the, sometimes unnecessary, amount of noise on a page full of form elements.</div>
        <pre class="language-yaml"><code>forms_smart_descriptions: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="forms-smart-descriptions-allowed-tags" data-anchor="true">forms_smart_descriptions_allowed_tags</span>
      </td>
      <td>
        <div class="help-block">Prevents descriptions from becoming tooltips by checking for HTML not in the list above (i.e. links). Separate by commas. To disable this filtering criteria, leave an empty value.</div>
        <pre class="language-yaml"><code>forms_smart_descriptions_allowed_tags: 'b, code, em, i, kbd, span, strong'</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="forms-smart-descriptions-limit" data-anchor="true">forms_smart_descriptions_limit</span>
      </td>
      <td>
        <div class="help-block">Prevents descriptions from becoming tooltips by checking the character length of the description (HTML is not counted towards this limit). To disable this filtering criteria, leave an empty value.</div>
        <pre class="language-yaml"><code>forms_smart_descriptions_limit: '250'</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### General > Images

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="image-responsive" data-anchor="true">image_responsive</span>
      </td>
      <td>
        <div class="help-block">Images in Bootstrap 3 can be made responsive-friendly via the addition of the <code>.img-responsive</code> class. This applies <code>max-width: 100%;</code> and <code>height: auto;</code> to the image so that it scales nicely to the parent element.</div>
        <pre class="language-yaml"><code>image_responsive: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="image-shape" data-anchor="true">image_shape</span>
      </td>
      <td>
        <div class="help-block">Add classes to an <code>&lt;img&gt;</code> element to easily style images in any project.</div>
        <pre class="language-yaml"><code>image_shape: ''</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### General > Tables

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="table-bordered" data-anchor="true">table_bordered</span>
      </td>
      <td>
        <div class="help-block">Add borders on all sides of the table and cells.</div>
        <pre class="language-yaml"><code>table_bordered: 0</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="table-condensed" data-anchor="true">table_condensed</span>
      </td>
      <td>
        <div class="help-block">Make tables more compact by cutting cell padding in half.</div>
        <pre class="language-yaml"><code>table_condensed: 0</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="table-hover" data-anchor="true">table_hover</span>
      </td>
      <td>
        <div class="help-block">Enable a hover state on table rows.</div>
        <pre class="language-yaml"><code>table_hover: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="table-striped" data-anchor="true">table_striped</span>
      </td>
      <td>
        <div class="help-block">Add zebra-striping to any table row within the <code>&lt;tbody&gt;</code>.</div>
        <pre class="language-yaml"><code>table_striped: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="table-responsive" data-anchor="true">table_responsive</span>
      </td>
      <td>
        <div class="help-block">Wraps tables with <code>.table-responsive</code> to make them horizontally scroll when viewing them on devices under 768px. When viewing on devices larger than 768px, you will not see a difference in the presentational aspect of these tables. The <code>Automatic</code> option will only apply this setting for front-end facing tables, not the tables in administrative areas.</div>
        <pre class="language-yaml"><code>table_responsive: -1</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### Components > Breadcrumbs

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="breadcrumb" data-anchor="true">breadcrumb</span>
      </td>
      <td>
        <div class="help-block">Show or hide the Breadcrumbs</div>
        <pre class="language-yaml"><code>breadcrumb: '1'</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="breadcrumb-home" data-anchor="true">breadcrumb_home</span>
      </td>
      <td>
        <div class="help-block">If your site has a module dedicated to handling breadcrumbs already, ensure this setting is enabled.</div>
        <pre class="language-yaml"><code>breadcrumb_home: 0</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="breadcrumb-title" data-anchor="true">breadcrumb_title</span>
      </td>
      <td>
        <div class="help-block">If your site has a module dedicated to handling breadcrumbs already, ensure this setting is disabled.</div>
        <pre class="language-yaml"><code>breadcrumb_title: 1</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### Components > Navbar

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="navbar-inverse" data-anchor="true">navbar_inverse</span>
      </td>
      <td>
        <div class="help-block">Select if you want the inverse navbar style.</div>
        <pre class="language-yaml"><code>navbar_inverse: 0</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="navbar-position" data-anchor="true">navbar_position</span>
      </td>
      <td>
        <div class="help-block">Determines where the navbar is positioned on the page.</div>
        <pre class="language-yaml"><code>navbar_position: ''</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### Components > Region Wells

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="region-wells" data-anchor="true">region_wells</span>
      </td>
      <td>
        <div class="help-block">Enable the <code>.well</code>, <code>.well-sm</code> or <code>.well-lg</code> classes for specified regions.</div>
        <pre class="language-yaml"><code>region_wells:
  navigation: ''
  navigation_collapsible: ''
  header: ''
  highlighted: ''
  help: ''
  content: ''
  sidebar_first: ''
  sidebar_second: well
  footer: ''</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### JavaScript > Modals

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="modal-enabled" data-anchor="true">modal_enabled</span>
      </td>
      <td>
        <div class="help-block"></div>
        <pre class="language-yaml"><code>modal_enabled: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="modal-jquery-ui-bridge" data-anchor="true">modal_jquery_ui_bridge</span>
      </td>
      <td>
        <div class="help-block">Enabling this replaces the core/jquery.ui.dialog dependency in the core/drupal.dialog library with a jQuery UI Dialog widget bridge. This bridge adds support to Bootstrap Modals so that it may interpret jQuery UI Dialog functionality.</div>
        <pre class="language-yaml"><code>modal_jquery_ui_bridge: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="modal-animation" data-anchor="true">modal_animation</span>
      </td>
      <td>
        <div class="help-block">Apply a CSS fade transition to modals.</div>
        <pre class="language-yaml"><code>modal_animation: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="modal-backdrop" data-anchor="true">modal_backdrop</span>
      </td>
      <td>
        <div class="help-block">Includes a modal-backdrop element. Alternatively, specify <code>static</code> for a backdrop which doesn't close the modal on click.</div>
        <pre class="language-yaml"><code>modal_backdrop: 'true'</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="modal-focus-input" data-anchor="true">modal_focus_input</span>
      </td>
      <td>
        <div class="help-block">Enabling this focuses on the first available and visible input found in the modal after it's opened. If no element is found, the close button (if visible) is focused instead.</div>
        <pre class="language-yaml"><code>modal_focus_input: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="modal-keyboard" data-anchor="true">modal_keyboard</span>
      </td>
      <td>
        <div class="help-block">Closes the modal when escape key is pressed.</div>
        <pre class="language-yaml"><code>modal_keyboard: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="modal-select-text" data-anchor="true">modal_select_text</span>
      </td>
      <td>
        <div class="help-block">Enabling this selects the text of the first available and visible input found after it has been focused.</div>
        <pre class="language-yaml"><code>modal_select_text: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="modal-show" data-anchor="true">modal_show</span>
      </td>
      <td>
        <div class="help-block">Shows the modal when initialized.</div>
        <pre class="language-yaml"><code>modal_show: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="modal-size" data-anchor="true">modal_size</span>
      </td>
      <td>
        <div class="help-block">Defines the modal size between the default, <code>modal-sm</code> and <code>modal-lg</code>.</div>
        <pre class="language-yaml"><code>modal_size: ''</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### JavaScript > Popovers

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="popover-enabled" data-anchor="true">popover_enabled</span>
      </td>
      <td>
        <div class="help-block">Elements that have the <code>data-toggle="popover"</code> attribute set will automatically initialize the popover upon page load. <div class='alert alert-warning alert-sm'><strong>WARNING:</strong> This feature can sometimes impact performance. Disable if pages appear to hang after load.</div></div>
        <pre class="language-yaml"><code>popover_enabled: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="popover-animation" data-anchor="true">popover_animation</span>
      </td>
      <td>
        <div class="help-block">Apply a CSS fade transition to the popover.</div>
        <pre class="language-yaml"><code>popover_animation: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="popover-auto-close" data-anchor="true">popover_auto_close</span>
      </td>
      <td>
        <div class="help-block">If enabled, the active popover will automatically close when it loses focus, when a click occurs anywhere in the DOM (outside the popover), the escape key (ESC) is pressed or when another popover is opened.</div>
        <pre class="language-yaml"><code>popover_auto_close: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="popover-container" data-anchor="true">popover_container</span>
      </td>
      <td>
        <div class="help-block">Appends the popover to a specific element. Example: <code>body</code>. This option is particularly useful in that it allows you to position the popover in the flow of the document near the triggering element - which will prevent the popover from floating away from the triggering element during a window resize.</div>
        <pre class="language-yaml"><code>popover_container: body</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="popover-content" data-anchor="true">popover_content</span>
      </td>
      <td>
        <div class="help-block">Default content value if <code>data-content</code> or <code>data-target</code> attributes are not present.</div>
        <pre class="language-yaml"><code>popover_content: ''</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="popover-delay" data-anchor="true">popover_delay</span>
      </td>
      <td>
        <div class="help-block">The amount of time to delay showing and hiding the popover (in milliseconds). Does not apply to manual trigger type.</div>
        <pre class="language-yaml"><code>popover_delay: '0'</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="popover-html" data-anchor="true">popover_html</span>
      </td>
      <td>
        <div class="help-block">Insert HTML into the popover. If false, jQuery's text method will be used to insert content into the DOM. Use text if you're worried about XSS attacks.</div>
        <pre class="language-yaml"><code>popover_html: 0</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="popover-placement" data-anchor="true">popover_placement</span>
      </td>
      <td>
        <div class="help-block">Where to position the popover. When <code>auto</code> is specified, it will dynamically reorient the popover. For example, if placement is <code>auto left</code>, the popover will display to the left when possible, otherwise it will display right.</div>
        <pre class="language-yaml"><code>popover_placement: right</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="popover-selector" data-anchor="true">popover_selector</span>
      </td>
      <td>
        <div class="help-block">If a selector is provided, tooltip objects will be delegated to the specified targets. In practice, this is used to enable dynamic HTML content to have popovers added.</div>
        <pre class="language-yaml"><code>popover_selector: ''</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="popover-title" data-anchor="true">popover_title</span>
      </td>
      <td>
        <div class="help-block">Default title value if <code>title</code> attribute isn't present.</div>
        <pre class="language-yaml"><code>popover_title: ''</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="popover-trigger" data-anchor="true">popover_trigger</span>
      </td>
      <td>
        <div class="help-block">How a popover is triggered.</div>
        <pre class="language-yaml"><code>popover_trigger: click</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### JavaScript > Tooltips

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="tooltip-enabled" data-anchor="true">tooltip_enabled</span>
      </td>
      <td>
        <div class="help-block">Elements that have the <code>data-toggle="tooltip"</code> attribute set will automatically initialize the tooltip upon page load. <div class='alert alert-warning alert-sm'><strong>WARNING:</strong> This feature can sometimes impact performance. Disable if pages appear to "hang" after load.</div></div>
        <pre class="language-yaml"><code>tooltip_enabled: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="tooltip-animation" data-anchor="true">tooltip_animation</span>
      </td>
      <td>
        <div class="help-block">Apply a CSS fade transition to the tooltip.</div>
        <pre class="language-yaml"><code>tooltip_animation: 1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="tooltip-container" data-anchor="true">tooltip_container</span>
      </td>
      <td>
        <div class="help-block">Appends the tooltip to a specific element. Example: <code>body</code>.</div>
        <pre class="language-yaml"><code>tooltip_container: body</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="tooltip-delay" data-anchor="true">tooltip_delay</span>
      </td>
      <td>
        <div class="help-block">The amount of time to delay showing and hiding the tooltip (in milliseconds). Does not apply to manual trigger type.</div>
        <pre class="language-yaml"><code>tooltip_delay: '0'</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="tooltip-html" data-anchor="true">tooltip_html</span>
      </td>
      <td>
        <div class="help-block">Insert HTML into the tooltip. If false, jQuery's text method will be used to insert content into the DOM. Use text if you're worried about XSS attacks.</div>
        <pre class="language-yaml"><code>tooltip_html: 0</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="tooltip-placement" data-anchor="true">tooltip_placement</span>
      </td>
      <td>
        <div class="help-block">Where to position the tooltip. When <code>auto</code> is specified, it will dynamically reorient the tooltip. For example, if placement is <code>auto left</code>, the tooltip will display to the left when possible, otherwise it will display right.</div>
        <pre class="language-yaml"><code>tooltip_placement: 'auto left'</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="tooltip-selector" data-anchor="true">tooltip_selector</span>
      </td>
      <td>
        <div class="help-block">If a selector is provided, tooltip objects will be delegated to the specified targets.</div>
        <pre class="language-yaml"><code>tooltip_selector: ''</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="tooltip-trigger" data-anchor="true">tooltip_trigger</span>
      </td>
      <td>
        <div class="help-block">How a tooltip is triggered.</div>
        <pre class="language-yaml"><code>tooltip_trigger: hover</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### CDN (Content Delivery Network)

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="cdn-provider" data-anchor="true">cdn_provider</span>
      </td>
      <td>
        <div class="help-block">Choose the CDN Provider used to load Bootstrap resources.</div>
        <pre class="language-yaml"><code>cdn_provider: jsdelivr</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="cdn-version" data-anchor="true">cdn_version</span>
      </td>
      <td>
        <div class="help-block">Choose a version provided by the CDN Provider.</div>
        <pre class="language-yaml"><code>cdn_version: 3.4.1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="cdn-theme" data-anchor="true">cdn_theme</span>
      </td>
      <td>
        <div class="help-block">Choose a theme provided by the CDN Provider.</div>
        <pre class="language-yaml"><code></code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### CDN (Content Delivery Network) > Advanced Cache

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="cdn-cache-ttl-versions" data-anchor="true">cdn_cache_ttl_versions</span>
      </td>
      <td>
        <div class="help-block">The length of time to cache the CDN verions before requesting them from the API again.</div>
        <pre class="language-yaml"><code>cdn_cache_ttl_versions: 604800</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="cdn-cache-ttl-themes" data-anchor="true">cdn_cache_ttl_themes</span>
      </td>
      <td>
        <div class="help-block">The length of time to cache the CDN themes (if applicable) before requesting them from the API again.</div>
        <pre class="language-yaml"><code>cdn_cache_ttl_themes: 604800</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="cdn-cache-ttl-assets" data-anchor="true">cdn_cache_ttl_assets</span>
      </td>
      <td>
        <div class="help-block">The length of time to cache the parsing and processing of CDN assets before rebuilding them again. Note: any change to CDN values automatically triggers a new build.</div>
        <pre class="language-yaml"><code>cdn_cache_ttl_assets: -1</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="cdn-cache-ttl-library" data-anchor="true">cdn_cache_ttl_library</span>
      </td>
      <td>
        <div class="help-block">The length of time to cache the theme's library alterations before rebuilding them again. Note: any change to CDN values automatically triggers a new build.</div>
        <pre class="language-yaml"><code>cdn_cache_ttl_library: -1</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### CDN (Content Delivery Network) > Custom URLs

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="cdn-custom" data-anchor="true">cdn_custom</span>
      </td>
      <td>
        <div class="help-block">One complete URL per line. All URLs are validated and parsed to determine available version(s) and/or theme(s). A URL can be any file ending in <code>.css</code> or <code>.js</code> (with matching response MIME type). Minified URLs can also be supplied and the will be used automatically.</div>
        <pre class="language-yaml"><code>cdn_custom: "https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.css
https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css
https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.js
https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js"</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### Advanced

<table class="table table-striped table-responsive">
  <thead>
    <tr>
      <th class="col-xs-3">Setting name</th>
      <th>Description and default value</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="col-xs-3">
        <span id="include-deprecated" data-anchor="true">include_deprecated</span>
      </td>
      <td>
        <div class="help-block">Enabling this setting will include any <code>deprecated.php</code> file found in your theme or base themes.</div>
        <pre class="language-yaml"><code>include_deprecated: 0</code></pre>
      </td>
    </tr>
    <tr>
      <td class="col-xs-3">
        <span id="suppress-deprecated-warnings" data-anchor="true">suppress_deprecated_warnings</span>
      </td>
      <td>
        <div class="help-block">Enable this setting if you wish to suppress deprecated warning messages.</div>
        <pre class="language-yaml"><code>suppress_deprecated_warnings: 0</code></pre>
      </td>
    </tr>
      </tbody>
</table>

---

### Deprecated

<table class="table table-responsive">
  <thead>
  <tr>
    <th class="col-xs-3">Setting name</th>
    <th>Description and default value</th>
  </tr>
  </thead>
  <tbody>
    <tr class="bg-warning">
      <td class="col-xs-3">
        <span id="popover_trigger_autoclose" data-anchor="true">popover_trigger_autoclose</span>
      </td>
      <td>
        <div class="help-block">Will automatically close the current popover if a click occurs anywhere else other than the popover element.</div>
        <pre class="language-yaml"><code>popover_trigger_autoclose: 1</code></pre>
        <div class="alert alert-danger alert-sm">
          <strong>Deprecated since 8.x-3.14</strong> - Replaced with new setting. Will be removed in a future release. (see: <a href="#popover-auto-close">popover_auto_close</a>)
        </div>
      </td>
    </tr>
  <tr class="bg-warning">
      <td class="col-xs-3">
        <span id="cdn_jsdelivr_version" data-anchor="true">cdn_jsdelivr_version</span>
      </td>
      <td>
        <div class="help-block">Choose the Bootstrap version from jsdelivr</div>
        <pre class="language-yaml"><code>cdn_jsdelivr_version: 3.4.1</code></pre>
        <div class="alert alert-danger alert-sm">
          <strong>Deprecated since 8.x-3.18</strong> - Replaced with new setting. Will be removed in a future release. (see: <a href="#cdn-version">cdn_version</a>)
        </div>
      </td>
    </tr>
  <tr class="bg-warning">
      <td class="col-xs-3">
        <span id="cdn_jsdelivr_theme" data-anchor="true">cdn_jsdelivr_theme</span>
      </td>
      <td>
        <div class="help-block">Choose the Example Theme provided by Bootstrap or one of the Bootswatch themes.</div>
        <pre class="language-yaml"><code>cdn_jsdelivr_theme: bootstrap</code></pre>
        <div class="alert alert-danger alert-sm">
          <strong>Deprecated since 8.x-3.18</strong> - Replaced with new setting. Will be removed in a future release. (see: <a href="#cdn-theme">cdn_theme</a>)
        </div>
      </td>
    </tr>
  <tr class="bg-warning">
      <td class="col-xs-3">
        <span id="cdn_custom_css" data-anchor="true">cdn_custom_css</span>
      </td>
      <td>
        <div class="help-block">It is best to use <code>https</code> protocols here as it will allow more flexibility if the need ever arises.</div>
        <pre class="language-yaml"><code>cdn_custom_css: 'https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.css'</code></pre>
        <div class="alert alert-danger alert-sm">
          <strong>Deprecated since 8.x-3.18</strong> - Replaced with new setting. Will be removed in a future release. (see: <a href="#cdn-custom">cdn_custom</a>)
        </div>
      </td>
    </tr>
  <tr class="bg-warning">
      <td class="col-xs-3">
        <span id="cdn_custom_css_min" data-anchor="true">cdn_custom_css_min</span>
      </td>
      <td>
        <div class="help-block">Additionally, you can provide the minimized version of the file. It will be used instead if site aggregation is enabled.</div>
        <pre class="language-yaml"><code>cdn_custom_css_min: 'https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css'</code></pre>
        <div class="alert alert-danger alert-sm">
          <strong>Deprecated since 8.x-3.18</strong> - Replaced with new setting. Will be removed in a future release. (see: <a href="#cdn-custom">cdn_custom</a>)
        </div>
      </td>
    </tr>
  <tr class="bg-warning">
      <td class="col-xs-3">
        <span id="cdn_custom_js" data-anchor="true">cdn_custom_js</span>
      </td>
      <td>
        <div class="help-block">It is best to use <code>https</code> protocols here as it will allow more flexibility if the need ever arises.</div>
        <pre class="language-yaml"><code>cdn_custom_js: 'https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.js'</code></pre>
        <div class="alert alert-danger alert-sm">
          <strong>Deprecated since 8.x-3.18</strong> - Replaced with new setting. Will be removed in a future release. (see: <a href="#cdn-custom">cdn_custom</a>)
        </div>
      </td>
    </tr>
  <tr class="bg-warning">
      <td class="col-xs-3">
        <span id="cdn_custom_js_min" data-anchor="true">cdn_custom_js_min</span>
      </td>
      <td>
        <div class="help-block">Additionally, you can provide the minimized version of the file. It will be used instead if site aggregation is enabled.</div>
        <pre class="language-yaml"><code>cdn_custom_js_min: 'https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js'</code></pre>
        <div class="alert alert-danger alert-sm">
          <strong>Deprecated since 8.x-3.18</strong> - Replaced with new setting. Will be removed in a future release. (see: <a href="#cdn-custom">cdn_custom</a>)
        </div>
      </td>
    </tr>
  </tbody>
</table>
<!-- THEME SETTINGS GENERATION END -->

### Environmental Theme Settings

These settings are not config based and cannot be set via the UI. They can
only be set in your site's `settings[.local].php` file. They are intended to be
used only for local development purposes:


#### Development Mode

This indicates that theme is in "development" mode:

```php
<?php
$settings['theme.dev'] = TRUE;
```

While this setting doesn't really do much on its own, its primary function is
intended to help with sub-theming. This adds variables that can be accessed
elsewhere in your code:

**PHP**
```php
<?php
use Drupal\bootstrap\Bootstrap; 

/**
 * Implements hook_preprocess_HOOK().
 */
function THEMENAME_preprocess_page(&$variables) {
  // Preprocess hooks already have this in the "theme" array.
  // This is also passed to the Twig template (see below).
  if ($variables['theme']['dev']) {
    // Do something here.
  }
}

/**
 * Implements hook_js_settings_alter().
 */
function THEMENAME_js_settings_alter(array &$settings, AttachedAssetsInterface $assets) {
  // In other procedural functions, use the Bootstrap helper method to retrieve
  // the theme and then access the method there.
  $theme = Bootstrap::getTheme(); 
  if ($theme->isDev()) {
    // Do something here.
  }
}
```

In Drupal Bootstrap based plugins, there is a `theme` property already in the
plugin instance that can be accessed (e.g. `$this->theme->isDev()`).

**Twig**
```twig
{% if theme.dev %}
  {# Do something here. #}
{% endif %}
```

**JavaScript**
```js
var theme = drupalSettings['THEMENAME'] || {};
if (theme.dev) {
  // Do something here.
}
```


#### Livereload

This automatically adds livereload to the page. Supply one of the following:

```php
<?php
// Enable default value: //127.0.0.1:35729/livereload.js.
$settings['theme.livereload'] = TRUE;

// Or, set just the port number: //127.0.0.1:12345/livereload.js.
$settings['theme.livereload'] = 12345;

// Or, Set an explicit URL.
$settings['theme.livereload'] = '//127.0.0.1:35729/livereload.js';
```


[Drupal Bootstrap]: https://www.drupal.org/project/bootstrap
[Bootstrap Framework]: https://getbootstrap.com/docs/3.4/
