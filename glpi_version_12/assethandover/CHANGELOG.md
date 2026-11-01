## 1.2.0 UAT URL compatibility hotfix (2026-11-01)
- Corrected GLPI 12 crash caused by removed `Plugin::getWebDir()`.
- Canonical URLs for plugin menu, AJAX searches, and front forms.
- No database or handover data changes.

# Asset Handover & Return Plugin — CHANGELOG

## v1.2.0 — 2026-11-01 (GLPI 12 compatibility candidate)

- GLPI 12+ minimum version and PHP 8.3 requirement (future major versions require separate verification).
- Fixed inherited typed right name; GLPI 12 database API and installation/upgrade queries.
- Replaced obsolete HTML error helpers with GLPI 12 exceptions.
- Replaced obsolete GLPI CSRF-token forms with native GLPI 12 request CSRF handling.
- Updated GLPI 12 front-controller bootstrapping and fixed profile rights initialization.
- Added read/write permissions at the corresponding endpoints; bounded list search inputs.
- Preserved Handover, Return, asset/user autocomplete, activity history, PDF/print template, CSV and trash/restore/purge.
- Existing plugin tables and saved data are preserved by the installer/uninstaller.


## v1.0.0 — 2026-04-28

### Initial Release

**Features:**
- Asset Handover Form: record IT giving assets to users
- Asset Return Form: record users returning assets to IT
- Dynamic multi-row asset table with live GLPI computer search (name/asset tag autocomplete)
- Auto-fill: asset tag, asset type pulled from GLPI `glpi_computers`
- Auto-fill: department from user's GLPI profile
- Per-asset fields:
  - Handover: Condition (New/Good/Used), Accessories
  - Return: Return Status (Returned/Not Returned), Condition on Return (Good/Damaged/Missing)
- User confirmation checkbox (handover)
- Remark field
- List view with tabs: All / Handover / Return / Trash
- Search, sort by column, pagination, rows-per-page
- Soft delete / restore / purge (both single and bulk)
- Print slip (HTML print page with auto-print, signature blocks)
- GLPI 10.x native menu integration under Assets
- CSRF protection via GLPI's `Session::checkCSRF()` + `DO_NOT_CHECK_HTTP_REFERER`
- Profile rights (READ/CREATE/UPDATE/DELETE/PURGE)
- DB: `glpi_plugin_assethandover_handovers` + `glpi_plugin_assethandover_items`

**Database schema:**
- 1 row per handover/return event (header)
- 1 row per asset per event (items)
- Soft delete on header table
- All timestamps use `TIMESTAMP` type (GLPI 10 compatible)
