# Widget Manager implementation and verification report

Date: 19 November 2026

## Scope

This revision builds on the existing MyJustDial project ZIP. The existing Form Manager, public forms, project assets, configuration, and database dump remain in the archive. No database tables or configuration changes were introduced.

## Database analysis

- `data_widgets` stores `widget_id`, `widget_name`, `widget_type`, `widget_data` (HTML), `widget_style` (CSS), `widget_javascript`, `short_code`, `widget_viewport`, and timestamps.
- The supplied SQL dump contains a `data_widgets` record for `Test`; other widget names visible in reference screenshots were not supplied as actual records.
- `data_widgets_rel` holds placement records. Some `widget_id` values in this table are widget names rather than numeric `data_widgets.widget_id` foreign keys. This implementation does not infer unsupported mappings.
- `users_meta` already holds metadata for many tables. This revision uses `database='data_widgets'`, `key='widget_revision'`, and `database_id=widget_id` to store previous widget content when saving/restoring.
- Widget shortcode strings are created once, stored in `data_widgets.short_code`, and kept stable during updates.
- No SQL schema modifications are required. See `database/verify_widget_manager.sql` for read-only verification queries.

## Widget lifecycle and controls

| Control or operation | Implementation |
| --- | --- |
| Create Widget | Validated, CSRF-protected POST to `actions.php`; inserts into `data_widgets`. |
| Edit and Save | Existing record selected by `widget_id`; updates its HTML, CSS, JS, name and type without changing shortcode. |
| Save visibility | Browser compares the current form data with initial values; Save remains hidden until a modification is made. |
| Delete | Confirmation, CSRF verification and transaction; deletes the widget and matching numeric placement references. |
| Enable/disable | Updates `widget_viewport` to `front` or `disabled`. |
| Clone | Copies supported widget fields and generates a different unique shortcode. |
| Bulk enable/disable/delete | Validated selected widget IDs and a single transaction. |
| Search | Widget name/shortcode search using bound parameters; Enter submits the combined status/search control. |
| Status filter | Enabled/disabled filtering in SQL; changing status applies the filter. |
| Sort | ID, name, status, `date_updated`, or `revision_timestamp`, using allow-listed SQL columns. |
| Date range | From/to date controls filter `revision_timestamp` with inclusive start and end dates. |
| Page size and pagination | Limited SELECT with permitted page sizes and validated page number. |
| Table density and column visibility | Browser-side table spacing and visibility controls for the BD-style visible columns. |
| Contextual bulk actions | Bulk action controls appear only after one or more rows are selected, keeping the default table layout aligned with the BD reference. |
| Shortcode copying | Clipboard API with a `document.execCommand('copy')` fallback; success/failure toast is shown based on the result. |
| Export | Authenticated JSON download containing widget data and shortcode. |
| Revision history | Previous content is stored in `users_meta`; selected prior revision can be restored. |
| New Widget modal | Create form with code editor tabs and validation. |
| Code editor | HTML/CSS/JavaScript tabs, line numbers, syntax highlighting, find, indentation, undo/redo, code copy, fullscreen, status information. |
| Dark/light mode | Dark mode is scoped to the HTML/CSS/JavaScript code editor only; persisted `localStorage` preference. |
| Widget preview | Isolated `iframe` with the unsaved editor content, CSS and JavaScript. |

## Shortcode integration

- `includes/widgets.php` looks up `[widget=stored_code]` in `data_widgets`.
- The renderer creates a sandboxed iframe for widget HTML, CSS and JavaScript, to prevent widget code from modifying the parent Admin or member page.
- Shortcode resolution is wired to the Form Manager server preview, live Form Builder preview, public forms, signup, and member form rendering.
- Disabled/deleted/missing widgets show an unavailable message instead of stale HTML.
- Rendering multiple different shortcodes resolves each independently.
- Existing PHP custom component handling remains in place for ordinary `.php` component labels.

## UI corrections in this ZIP

- Reworked the listing toolbar to use the BD reference's combined status/search control and combined Updated/date-range control; removed the separate Search button.
- Removed the always-visible bulk-action row. Bulk actions now appear only when at least one row is selected, preserving the operation without adding clutter to the default view.
- Matched the BD table structure: selection, ID, row actions, custom widget name, status, created and updated. The Actions heading and standalone Shortcode column are no longer displayed.
- Moved Copy into the Updated cell and retained Copy Shortcode in the Actions menu.
- Added BD-style date/time stacking, alternating row backgrounds, sort arrows and right-aligned pagination controls.
- Added a functional from/to date-range popover and applied the date filter to `revision_timestamp`.
- Preserved the existing sidebar, header, dashboard shell and database configuration.

## Database date limitation

The supplied `data_widgets` schema has `date_updated` and `revision_timestamp`, but no dedicated creation timestamp. To avoid inventing a schema field, the Created display uses the existing `date_updated` value and Updated uses `revision_timestamp`. The Created value may therefore reflect a later update for records whose `date_updated` is changed by the application. No database schema changes were made.

## Static verification performed

- PHP syntax checks passed for the modified Widget Manager index, edit and actions files.
- Node syntax check passed for `assets/js/widget-manager.js`.
- SQL column references used by the listing (`date_updated`, `revision_timestamp`, `widget_viewport`, `widget_name`, `short_code`) exist in the supplied `data_widgets` schema.
- `config/database.php` and `config/config.php` remain unchanged from the supplied archive.
- The final ZIP is checked for archive integrity and a single `MyJustDialNew/` root folder.

## Runtime tests not performed

- No live PHP/MySQL application session was available, so database writes, login-dependent actions, and end-to-end form shortcode rendering were not executed.
- No browser screenshot comparison or interactive browser pass was completed in this environment. The visual changes are based on the supplied screenshots and static code review; pixel-perfect runtime matching remains to be confirmed in the user's XAMPP environment.
- Clipboard behavior, date-range interaction, sorting, filtering, pagination, and bulk actions have code-level implementations but still require browser/database runtime testing.
- Actual behavior of custom widget JavaScript and external assets depends on widget content, hosting origin, and iframe sandbox restrictions.

## Remaining known limitations

- Brilliant Directories' 520 default widget *templates* are not included as editable HTML/CSS/JS records in the provided SQL. The project does not invent content for those templates.
- Historical revisions made on Brilliant Directories before this implementation are not available as restorable snapshots in the provided database. Newly saved widget revisions are stored from this implementation onward.
- The screenshot editor is a custom source-code editor, not CKEditor. This follows the user's explicit final choice.
- Exact pixel-level visual acceptance and all database write tests require running the ZIP on a PHP/MySQL server.
