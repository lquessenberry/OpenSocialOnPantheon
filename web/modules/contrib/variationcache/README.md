# VariationCache

The variation cache allows you to use cache contexts with any cache backend.

On top of introducing this new system, this module contains a submodule which swaps out core's dynamic page cache and render cache with a copy that runs on the new variation cache. The goal is for this to eventually land in core, but by exposing it here, we can already have people run it on live sites and report back with performance results.

The Group module already depends on the main module, so yours can too! If the variation cache eventually makes it into core, then you can just drop the dependency and start using the core version.

For more information, please see <https://www.drupal.org/project/drupal/issues/2551419>.