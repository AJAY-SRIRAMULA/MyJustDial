UPDATE forms
SET short_code = '[form=contact_details]'
WHERE form_name IN ('member_contact_details', 'member_contact_details_custom')
  AND form_action_type <> 'default';

UPDATE users_meta um
JOIN forms f ON f.form_id = um.database_id
SET um.value = JSON_SET(um.value, '$.public_shortcode_name', 'contact_details')
WHERE um.`database` = 'forms'
  AND um.`key` = 'form_settings_json'
  AND f.form_name IN ('member_contact_details', 'member_contact_details_custom')
  AND f.form_action_type <> 'default';
