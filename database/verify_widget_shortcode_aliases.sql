-- Read-only verification for readable widget shortcode aliases.
-- data_widgets.short_code must contain the 32-character hexadecimal identifier.
-- The copied shortcode alias and legacy aliases are stored in users_meta.

SELECT
    w.widget_id,
    w.widget_name,
    w.short_code AS hexadecimal_identifier,
    LENGTH(w.short_code) AS identifier_length,
    alias.value AS readable_shortcode_alias,
    legacy.value AS legacy_shortcode_alias
FROM data_widgets w
LEFT JOIN users_meta alias
    ON alias.`database` = 'data_widgets'
   AND alias.database_id = w.widget_id
   AND alias.`key` = 'widget_shortcode'
LEFT JOIN users_meta legacy
    ON legacy.`database` = 'data_widgets'
   AND legacy.database_id = w.widget_id
   AND legacy.`key` = 'widget_legacy_shortcode'
ORDER BY w.widget_id DESC;

-- A valid hexadecimal identifier is exactly 32 hexadecimal characters.
SELECT widget_id, widget_name, short_code
FROM data_widgets
WHERE short_code NOT REGEXP '^[0-9a-fA-F]{32}$';

-- Readable aliases must be unique across widgets.
SELECT value AS readable_shortcode_alias, COUNT(DISTINCT database_id) AS widget_count
FROM users_meta
WHERE `database` = 'data_widgets'
  AND `key` = 'widget_shortcode'
GROUP BY value
HAVING COUNT(DISTINCT database_id) > 1;
