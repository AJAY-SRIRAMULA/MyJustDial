-- MyJustDial Widget Manager database verification
-- Read-only. Run against a COPY of the intended MyJustDial database in phpMyAdmin.
-- This script does not create, update or delete any rows.

-- 1. Confirm the original storage tables exist.
SHOW TABLES LIKE 'data_widgets';
SHOW TABLES LIKE 'data_widgets_rel';
SHOW TABLES LIKE 'users_meta';

-- 2. Verify column mappings and auto-increment identifiers.
SHOW CREATE TABLE data_widgets;
SHOW CREATE TABLE data_widgets_rel;
SHOW CREATE TABLE users_meta;

-- 3. Inspect actual widget HTML, CSS, JavaScript and shortcode mappings.
SELECT widget_id, widget_name, widget_type, short_code, widget_viewport,
       LENGTH(widget_data) AS html_bytes,
       LENGTH(widget_style) AS css_bytes,
       LENGTH(widget_javascript) AS javascript_bytes,
       date_updated, revision_timestamp
FROM data_widgets
ORDER BY widget_id DESC;

-- 4. Each non-empty shortcode should identify one and only one widget.
SELECT short_code, COUNT(*) AS matches
FROM data_widgets
WHERE short_code <> ''
GROUP BY short_code
HAVING COUNT(*) > 1;

-- 5. Check whether a readable alias can resolve an enabled widget.
-- Replace this value with the copied shortcode value WITHOUT [widget=] and ].
SET @widget_reference = 'test';
SELECT w.widget_id, w.widget_name, w.short_code AS hexadecimal_identifier,
       a.value AS readable_shortcode_alias, w.widget_viewport,
       w.widget_data, w.widget_style, w.widget_javascript
FROM data_widgets w
INNER JOIN users_meta a
    ON a.`database` = 'data_widgets'
   AND a.database_id = w.widget_id
   AND a.`key` = 'widget_shortcode'
WHERE a.value = @widget_reference
  AND w.widget_viewport <> 'disabled';

-- 6. Review existing widget placement links (do not assume numeric widget IDs).
SELECT rel_id, widget_id, data_id, page_type, active, display_order
FROM data_widgets_rel
ORDER BY rel_id DESC
LIMIT 30;

-- 7. Review saved revision snapshots created by the Widget Manager.
SELECT meta_id, database_id AS widget_id, date_added,
       JSON_VALID(value) AS valid_revision_json
FROM users_meta
WHERE `database` = 'data_widgets' AND `key` = 'widget_revision'
ORDER BY meta_id DESC
LIMIT 100;

-- 8. Inspect Custom HTML fields that contain widget shortcodes.
SELECT form_name, field_id, field_text, field_ldesc
FROM form_fields
WHERE (LOWER(field_type) IN ('html','custom'))
  AND (field_text LIKE '%[widget=%' OR field_ldesc LIKE '%[widget=%')
ORDER BY form_name, field_order;

-- 9. Verify that shortcode references inside Custom HTML fields exist.
-- PHP verifies the complete field content and resolves each reference at render time.


-- 10. Verify readable shortcode aliases and hexadecimal storage.
SELECT w.widget_id, w.widget_name, w.short_code AS hexadecimal_identifier,
       a.value AS readable_shortcode_alias, l.value AS legacy_shortcode_alias
FROM data_widgets w
LEFT JOIN users_meta a ON a.`database`='data_widgets' AND a.database_id=w.widget_id AND a.`key`='widget_shortcode'
LEFT JOIN users_meta l ON l.`database`='data_widgets' AND l.database_id=w.widget_id AND l.`key`='widget_legacy_shortcode'
ORDER BY w.widget_id DESC;
