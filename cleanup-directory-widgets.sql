-- Back up forms, form_fields, users_meta and data_widgets before running.
-- Run once manually in the existing database; no PHP page executes this script.
START TRANSACTION;
UPDATE data_widgets SET widget_data = REPLACE(widget_data, '[form=find_members]', '{{component:directory_find_members.php}}') WHERE widget_name = 'Find Members';
UPDATE data_widgets SET widget_data = REPLACE(widget_data, '[form=newsletter_modal_signup]', '{{component:directory_newsletter.php}}') WHERE widget_name = 'Join Our Newsletter';
DELETE ff FROM form_fields ff JOIN forms f ON f.form_name = ff.form_name WHERE (f.form_name = 'find_members' AND f.form_title = 'Find Members');
DELETE m FROM users_meta m JOIN forms f ON f.form_id = m.database_id WHERE m.`database` = 'forms' AND ((f.form_name = 'find_members' AND f.form_title = 'Find Members'));
DELETE FROM forms WHERE (form_name = 'find_members' AND form_title = 'Find Members');
COMMIT;
SELECT form_id, form_name, form_title FROM forms WHERE form_name IN ('find_members','newsletter_modal_signup');
