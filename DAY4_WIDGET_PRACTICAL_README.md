# Day 4 Widget Practical

## Default widget table investigation
The provided SQL dump defines `data_widgets` and `data_widgets_rel`. It does not define a separate table specifically named or clearly dedicated to default widgets. `data_widgets` is the existing widget content store; `data_widgets_rel` is a relation/placement table. The dump contains no insert rows for `data_widgets` in the inspected dump section. Default/custom classification is not structurally represented by a separate dedicated table in this schema.

## Practical
1. Start XAMPP Apache and MySQL and open the project as normal.
2. Log into the project Admin Panel.
3. Open `/day4_widget_practical.php`.
4. First visit creates the `Day 4 Hello World To-Do Widget` custom widget (Hello World content) in the existing `data_widgets` table, and creates the readable shortcode alias in existing `users_meta`.
5. The page resolves `[widget=day4_hello_world]` through the project's existing shortcode renderer and embeds the retrieved widget.
6. Click **Upgrade Hello World to To-Do List** to update that same database widget to the interactive To-Do widget.
7. Test add, check off, and remove tasks. Refresh: widget code remains saved and renders again. The task items themselves are client-side and are not saved between refreshes.

## Data
- No new table/column is added.
- Existing `data_widgets` is used for widget content.
- Existing `users_meta` shortcode alias mapping is used.
- No default widget table was found in the SQL dump.
- The practical page requires existing Admin authentication.

## Verification limitation
PHP and JavaScript syntax checks pass. Database insertion, iframe interaction, and page refresh testing must be performed in XAMPP with the project's configured database.
