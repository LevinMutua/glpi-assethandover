# GLPI 12 plugin URL compatibility hotfix

## Confirmed root cause

GLPI log: `Call to undefined method Plugin::getWebDir()` in `inc/handover.class.php:20`.

GLPI 12 removed the deprecated `Plugin::getWebDir()` helper. The official migration guide recommends `/plugins/<key>/...` URLs prefixed with `$CFG_GLPI['root_doc']` where needed.

## Changes

- Added `PluginAssethandoverHandover::webBase()` returning root-doc-prefixed `/plugins/assethandover`.
- Updated menu page, search/add links, listing AJAX endpoint, JavaScript base URLs, and form AJAX endpoint.
- JavaScript URLs now use `json_encode()` to prevent quote handling errors.
- No database schema changes, no installer or uninstall edits, no data deletion.
- No changes to handover/return business logic, permissions, search, print, export, or saved records.

## Installation

Install only on GLPI 12+ UAT. Back up the current plugin directory and database, replace the whole `assethandover` folder, restart GLPI container or clear opcode cache as appropriate, then verify menu access, list, form, AJAX search, print/export, and handover/return creation.

## Limitations

Checked with static PHP syntax checks and package validation, not on a running GLPI instance. If the app still reports an error, capture fresh log lines produced after the page reload.
