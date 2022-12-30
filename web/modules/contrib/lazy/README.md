# Lazy-load

This is a simple Drupal module which lets you enable lazy-loading images and
iframes.

This module depends on the [lazysizes](https://github.com/aFarkas/lazysizes) library.
> High performance and SEO friendly lazy loader for images (responsive and
> normal), iframes and more, that detects any visibility changes triggered
> through user interaction, CSS or JavaScript without configuration.


## Features

* Now uses [lazysizes](https://github.com/aFarkas/lazysizes) library for rich features
* Supports **inline-images** and **inline-iframes** (Enabled per text-format)
* Supports **image fields** with following field formatters:
    - Colorbox `colorbox`
    - Image `image`
    - Responsive image `responsive_image`
    - Media Thumbnail `media_thumbnail`
* Provides a `hook_lazy_field_formatters_alter(&$formatters)` hook for your
  theme or module for adding field formatters programmatically.
* Supports native lazy-loading via `loading="auto"` attribute for Chrome
  browsers. This option can be disabled in the settings, so that the lazysizes
  library can be used for all browsers instead.
* Added 2 new (optional) image formatters: **Image (Lazy-load)** and
  **Responsive image (Lazy-load)**. These can be very useful with the Views.
* Lazy-loading is automatically disabled for [AMP](https://www.drupal.org/project/amp) pages.
* Additional paths can be defined to disable lazy-loading.
* Supports upgrading from 8.x-2.0 release.


## Installation

Download and install like any other module, and library;
into `modules/contrib/lazy`, and `libraries/lazysizes` folders respectively.

For installing via Composer make sure to read [How to use Composer to install Lazy-load module and its dependency](https://www.drupal.org/docs/8/modules/lazy-load/how-to-use-composer-to-install-lazy-load-module-and-its-dependency)


## How to use

Once installed, you can enable Lazy-loading in 3 different ways:

### via text formats
This method enables lazy-loading for **inline-images** and **inline-iframes** in
*Body* (formatted, text) fields.

 1. Open [Text formats and editors](https://example.com/admin/config/content/formats)
 2. Configure your choice of text formats. i.e. _Full HTML_.
 3. Enable the lazy filter labeled as **Lazy-load images and iframes**

### via field formatters
In entity display settings page, edit the image formatters. Supporting image
fields display a checkbox in the field formatting settings. Check the box to
enable lazy-loading for that image field, for that view mode.

### via Form API
See `LazyForm.php` for a quick example.


## Breaking changes since 2.x

> If you're looking for 8.x-2.x documentation go to [8.x-2.x/README.md](https://git.drupalcode.org/project/lazy/blob/8.x-2.x/README.md)

**[bLazy](http://dinbror.dk/blazy/) library replaced with [lazysizes](https://github.com/aFarkas/lazysizes).**
If you customized the bLazy configuration in 8.x-2.x, you should checkout the
documentation for code-migration options for lazysizes.


## Documentation

* [Lazy-load 8.x-3.x](https://www.drupal.org/docs/8/modules/lazy-load)
* [Lazy-load 8.x-2.x](https://git.drupalcode.org/project/lazy/blob/8.x-2.x/README.md)
* [Lazy-load 7.x](https://www.drupal.org/docs/7/modules/lazy-load)


## Change records for Lazy-load

https://www.drupal.org/list-changes/lazy
