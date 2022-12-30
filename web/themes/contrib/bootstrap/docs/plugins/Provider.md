<!-- @file Documentation for the @BootstrapProvider annotated plugin. -->
<!-- @defgroup -->
<!-- @ingroup -->
# @BootstrapProvider

- [Create a plugin](#create)
- [Rebuild the cache](#rebuild)

---

## Create a plugin {#create}

We'll use the `\Drupal\bootstrap\Plugin\Provider\JsDelivr` CDN Provider as an
example of how to create a quick custom CDN provider using its API URLs.

Replace all following instances of `THEMENAME` with the actual machine name of
your sub-theme.

You may also feel free to replace the provided URLs with your own. Most of the
popular CDN API output can be easily parsed, however you may need to provide
addition parsing in your custom CDN Provider if you're not getting the desired
results.

If you're truly interested in implementing a CDN Provider, it is highly
recommended that you read the accompanying PHP based documentation on the
classes and methods responsible for actually retrieving, parsing and caching
the data from the CDN's API.

Create a file at `./themes/THEMENAME/src/Plugin/Provider/MyCdn.php` with the
following contents:

```php
<?php

namespace Drupal\THEMENAME\Plugin\Provider;

use Drupal\bootstrap\Plugin\Provider\ApiProviderBase;

/**
 * The "mycdn" CDN Provider plugin.
 *
 * @ingroup plugins_provider
 *
 * @BootstrapProvider(
 *   id = "mycdn",
 *   label = @Translation("My CDN"),
 *   description = @Translation("My CDN (jsDelivr)"),
 *   weight = -1
 * )
 */
class JsDelivr extends ApiProviderBase {

  /**
   * {@inheritdoc}
   */
  protected function getApiAssetsUrlTemplate() {
    return 'https://data.jsdelivr.com/v1/package/npm/@library@@version/flat';
  }

  /**
   * {@inheritdoc}
   */
  protected function getApiVersionsUrlTemplate() {
    return 'https://data.jsdelivr.com/v1/package/npm/@library';
  }

  /**
   * {@inheritdoc}
   */
  protected function getCdnUrlTemplate() {
    return 'https://cdn.jsdelivr.net/npm/@library@@version/@file';
  }

}

?>
```

## Rebuild the cache {#rebuild}

Once you have saved, you must rebuild your cache for this new plugin to be
discovered. This must happen anytime you make a change to the actual file name
or the information inside the `@BootstrapProvider` annotation.

To rebuild your cache, navigate to `admin/config/development/performance` and
click the `Clear all caches` button. Or if you prefer, run `drush cr` from the
command line.

Voilà! After this, you should have a fully functional `@BootstrapProvider`
plugin!
