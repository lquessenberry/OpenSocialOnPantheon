Typed Data API Enhancements
===========================

Project site: http://www.drupal.org/project/typed_data

Code: https://www.drupal.org/project/typed_data/git-instructions

Issues: https://www.drupal.org/project/issues/typed_data

For example usage, see the Rules module: https://www.drupal.org/project/rules


Introduction
------------
The Typed Data API Enhancements module adds functionality to the core Drupal
TypedData API without altering the operation of the existing core API. These new
features are available for developers to use within modules that rely on
manipulation of typed data. Specifically, the functionality in this module was
originally part of Rules (https://www.drupal.org/project/rules), but was split
off because it can be of general use.

This module should only be installed as a dependency, if required by another
module, as it does nothing by itself.

The new features added by this module are:
 * TypedData Form Widgets
 * TypedData Tokens
 * DataFetcher Utility


TypedData Form Widgets
----------------------
This module adds a new plugin type - TypedDataFormWidget.

Each typed data datatype may have its own associated form widget, implemented
by a plugin, and used as a UI component for entering data of that type.

Similar in concept to Field Widgets, but can be used by any typed data
datatype, not just Fields.

    # Form widgets for data types
    plugin.manager.typed_data_form_widget:
      class: Drupal\typed_data\Widget\FormWidgetManager
      arguments: ['@container.namespaces', '@module_handler']


TypedData Tokens
----------------
Provided by PlaceholderResolver service to parse and replace these tokens.
Like core token service, except instead of core tokens like [node:title]
we use Twig-like tokens with typed data variables enclosed within {{ and }},
e.g. {{node.title}} Note that unlike core tokens, which must be strings, we
may use any typed data datatype inside these new tokens. Making data available
is as simple as creating a typed data datatype and does not involve implementing
hooks as core tokens do.

    # PlaceholderResolver needed to parse tokens
    typed_data.placeholder_resolver:
      class: Drupal\typed_data\PlaceholderResolver
      arguments: ['@typed_data.data_fetcher', '@plugin.manager.typed_data_filter']

As part of the PlaceholderResolver we have a new plugin type, TypedDataFilter,
which may be used to transform a typed data value in a typed data token.
For example, {{node.title|lower}} will be the lower case version of the node
title. Or, as an example non-string typed data, {{account|entity_url}} will
resolve to the canonical URL of the entity.

These TypedDataFilter plugins provide a powerful mechanism to manipulate
the typed data values. The Twig syntax makes them consistent with Drupal
front-end templates, which makes it easier for site builders to create Rules.

    # Token filters
    plugin.manager.typed_data_filter:
      class: Drupal\typed_data\DataFilterManager
      parent: default_plugin_manager


DataFetcher Utility
-------------------
Intended primarily for internal use by the TypedData API Enhancements module.

    # Retrieves data values for token replacement
    typed_data.data_fetcher:
      class: Drupal\typed_data\DataFetcher

May be useful when a hierarchical array representation of a datatype is needed.
