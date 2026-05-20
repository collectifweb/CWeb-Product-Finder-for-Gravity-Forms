# Translations

This folder holds `.po` / `.mo` files for **Gravity Recommender** translations.

The plugin uses text domain `gravity-recommender`. On wordpress.org, translations
are loaded automatically — no `load_plugin_textdomain()` call is needed.

To contribute a translation, please use the wordpress.org translation platform
once the plugin is published, or open a PR against this folder for development
languages.

To generate a fresh POT template from the source:

    wp i18n make-pot gravity-recommender languages/gravity-recommender.pot
