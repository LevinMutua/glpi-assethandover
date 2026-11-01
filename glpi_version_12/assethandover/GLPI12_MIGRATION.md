# Asset Handover & Return — GLPI 12 migration (UAT build)

## Existing features preserved

Asset handover and return forms, multi-asset rows, GLPI asset search across six item types, user lookup, linked returns, history, record list, activity log, status management, CSV export, and printable handover/return slips.

## Changes in v1.2.0

- Require GLPI >=12.0.0 and PHP >=8.3; no maximum GLPI version is declared. Later major versions are not verified.
- Use `public static string $rightname` for the GLPI 12 CommonGLPI property.
- Use GLPI 12 `DBmysql::escape()`, `doQuery()`, and `fetchAssoc()`; remove old `connection` access and nonexistent `doQueryOrDie()`.
- Replace removed HTML error helpers with GLPI 12 HTTP exceptions.
- Remove obsolete CSRF hidden tokens and the referer-check opt-out. GLPI 12 enforces CSRF via Fetch metadata/Origin headers.
- Rely on GLPI's web front-controller initialization instead of including `inc/includes.php`.
- Enforce READ on AJAX, CREATE for new records, UPDATE for edits, DELETE for trash/restore, and PURGE for permanent deletion.
- Limit asset lookup to search terms of at least 2 characters, with the existing per-table result caps.
- Fix profile-right helper to avoid querying a table that the plugin never creates.
- Preserve all existing handover/return data; install hook creates or adds missing columns but does not drop tables.

## Deployment

1. Back up the current `assethandover` directory and the GLPI database.
2. Extract this ZIP to the GLPI plugins directory, maintaining exactly `assethandover/`.
3. In GLPI **Setup > Plugins**, install or upgrade and enable Asset Handover & Return. Verify the profile right `plugin_assethandover` is granted to the intended profiles.
4. Restart the application container or PHP service to clear opcode caches, if needed.
5. Verify list, create/edit, asset search, user lookup, return linkage, activity history, CSV, print, trash/restore and purge in UAT.
6. Inspect GLPI PHP error logs if a page fails; this package has not been tested against a running GLPI 12 instance.

**Security/data note:** this is a compatibility port; the legacy plugin stores employee/asset records outside GLPI's standard entity-scoping conventions. Review organizational permissions before production deployment, and do not expose the plugin to untrusted profiles.
