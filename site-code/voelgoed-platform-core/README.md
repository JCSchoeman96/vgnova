# Voelgoed Platform Core content topic slice

This directory is the current canonical, source-controlled Platform Core code
in the `vgnova` repository. It tracks the `content_topic` module installed into
the existing Voelgoed Platform Core plugin at
`wp-content/plugins/voelgoed-platform-core`. The Local WordPress copy is not a
Git checkout. `integration.patch` records the two bootstrap changes made to
load and register this module.

Any Platform Core code change must first exist in this source-controlled
module. The active local WordPress plugin directory is a runtime deployment
copy and must not contain unique architecture changes that are absent from the
repository. Do not create a competing `content_topic` definition in ACF,
FluentSnippets, or theme code.

Run the focused checks with:

```sh
php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php
```

The active site seeds terms through `wp vg-content-topic-setup`. That command
creates only missing canonical terms, stops on slug or parent conflicts, and
flushes rewrite rules once after successful setup.
