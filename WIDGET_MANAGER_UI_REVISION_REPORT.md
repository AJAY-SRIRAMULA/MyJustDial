# Widget Manager UI revision report — V3

## Implemented

- Added a live Revisions dropdown to Edit Widget. It displays the current version timestamp and the saved revision timestamps (date and time) from the existing `users_meta` table.
- Selecting a previous revision prompts for confirmation and posts to the existing CSRF-protected `history.php` restore handler.
- Restoring a revision first snapshots the current widget content, then restores the selected snapshot. The user is returned to Edit Widget after restoration.
- Each normal widget save continues to create a snapshot of the previous widget name, type, HTML, CSS, and JavaScript, timestamped in `users_meta.date_added`.
- Added outside-click closing and Escape-key closing for all custom `<details>` dropdowns inside Widget Manager, including Revisions, Shortcode, Actions, and Date range. Opening one custom dropdown closes another.
- Reduced typography further across the Widget Manager list and Edit Widget page, including headings, alert, toolbar controls, table, dropdowns, tabs, editor hint, code text, and status bar.
- Form Manager's existing single-row-border correction is retained.

## Database

- Reuses `users_meta` with `database='data_widgets'`, `database_id=<widget_id>`, and `key='widget_revision'`.
- No new tables or columns added.
- Existing `history.php` restoration workflow is reused.

## Files modified in V3

- `admin/toolbox/widget-manager/edit.php`
- `admin/toolbox/widget-manager/history.php`
- `assets/js/widget-manager.js`
- `assets/css/widget-manager-polish.css`
- `WIDGET_MANAGER_UI_REVISION_REPORT.md`

## Verification

- PHP syntax: all 57 project PHP files passed `php -l`.
- JavaScript syntax: all 3 project JavaScript files passed `node --check`.
- ZIP integrity and extraction test passed.
- Browser interaction testing, live MySQL revision round-trip testing, and pixel comparison were not available in this environment. Test revision creation and restore against your XAMPP database before production use.


## V4 — Live unsaved-code indicators

- Editing HTML, CSS, or JavaScript now shows a short toast the first time that file becomes dirty.
- A small amber dot appears beside the corresponding HTML/CSS/JavaScript tab while that file differs from its initially loaded value.
- The dot clears when that file is reverted to its original content, including undo/redo changes.
- A compact floating unsaved-changes bar appears while any widget form field differs from its initial value. It includes Discard and Save actions.
- Discard restores the initially loaded form values after confirmation.
- Save uses the existing CSRF-protected widget update form and existing revision snapshot mechanism.
- The unsaved-changes bar disappears when all fields match their initial values.
- PHP and JavaScript syntax checks passed across the complete project after this update.
- Browser runtime testing remains required in XAMPP.


## V5 — Unsaved bar and discard confirmation UI

- Removed the duplicate inline `Save Changes` toolbar button. The floating unsaved-changes bar is now the only Save action.
- Moved the unsaved bar down into the available whitespace below the global website header and widened it to 560px (responsive on smaller screens).
- Replaced the browser-native `confirm()` prompt with a custom centered discard dialog matching the supplied reference: dimmed overlay, warning icon, heading, explanatory text, Keep Editing, and red Discard Changes actions.
- Clicking outside the dialog or pressing Escape closes it without discarding; only the red Discard Changes action restores the original values.
- Save continues through the existing widget form and revision-saving backend.
- Full PHP and JavaScript syntax checks passed after the update. ZIP integrity checked.
- Browser visual and interaction testing remains required in XAMPP.


## V6 — Readable widget shortcode aliases and Form Type removal

### Widget shortcode storage and resolution
- Public shortcode is now readable: `[widget=<sanitized-widget-name>]`, for example `[widget=test]`.
- `data_widgets.short_code` stores only a 32-character lowercase hexadecimal identifier generated with `bin2hex(random_bytes(16))`.
- The readable alias is stored in the existing `users_meta` table with `database='data_widgets'`, `database_id=<widget_id>`, `key='widget_shortcode'`, and `value=<slug>`. No new table or column is added.
- Duplicate widget names receive unique aliases using numeric suffixes (for example `test_2`). The alias is persisted, so renaming a widget does not change its shortcode. Clones receive a new hex identifier and a new unique alias.
- Existing legacy identifiers such as `data_widgets-<32 hex>.bd` are migrated to the raw hex value in `data_widgets.short_code`; the previous identifier is preserved in `users_meta` under `widget_legacy_shortcode` so old copied shortcode references continue resolving.
- Widget Manager list, Edit Widget, Copy Shortcode, PHP rendering, and JSON export now use the readable alias. The shortcode renderer resolves readable aliases, hex identifiers, legacy identifiers, and numeric widget IDs.

### Form Type removal and database relationship audit
- Removed the complete Form Type label/select from the Create New Form modal and removed the associated UI JavaScript.
- `create.php` now always creates a standard custom form (`forms.form_action_type='widget'`) from this modal and no longer accepts the Member Login creation branch or inserts login fields from this popup.
- The supplied SQL schema shows `forms.form_action_type` is a persisted database column, and application code uses it to distinguish default forms, custom/widget forms, and login forms. `includes/public_forms.php` specifically checks `form_action_type='login'` for Member Login rendering and submission.
- Existing Member Login forms are still provisioned independently by `includes/member_forms.php` (`memberFormDefinitions()` and `ensureMemberForms()`), which seeds `member_login` with `form_action_type='login'` and its required fields. This workflow was deliberately left unchanged.
- No database column, table, or existing form row was removed or altered as part of removing the UI field.

### V6 implementation and verification notes

- The supplied SQL dump contained one `data_widgets` row (`widget_id=1`, `widget_name='Test'`) whose prior `short_code` was `data_widgets-0b795b3c3b40c699152c8b8fd30016e6.bd`. The seed row now stores `0b795b3c3b40c699152c8b8fd30016e6` in `data_widgets.short_code`; the readable alias `test` and old identifier are mapped in `users_meta`.
- The existing `data_widgets_rel` table is not keyed by shortcode in the supplied schema and is not modified by shortcode conversion. Widget deletion now also clears that widget's `users_meta` mappings/revisions so a deleted alias can be reused.
- The `forms` SQL schema has no `form_type` column. The popup's `form_type` was only a POST control; it selected the value written to `forms.form_action_type` and optionally seeded login fields. `forms.form_action_type` remains intact because existing login rendering and submission depend on it.
- The existing `member_login` definition and `ensureMemberForms()` seeding logic were not changed. They continue to create/maintain the dedicated login form independently of the removed popup option.
- No new database tables or columns were added.
- Verification: all 57 PHP files passed `php -l`; all 3 JavaScript files passed `node --check`; the shortcode token regex and 32-character hex generation checks passed; static assertions confirmed the Form Type control/handler is removed while the Member Login seed logic remains.
- Live MySQL create/clone/legacy-migration/shortcode-render round-trip and browser testing were not available in this environment and should be verified in XAMPP.
