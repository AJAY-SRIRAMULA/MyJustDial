-- OPTIONAL, explicit cleanup. Export forms, form_fields and users_meta first.
-- Inspect the rows below before running the transaction. Keep the original lead form.
SELECT form_id, form_name, form_title, form_table, table_index FROM forms
WHERE form_name IN ('website_lead_form_get_matched','bootstrap_get_match','find_members','newsletter_modal_signup','listing_write_review');
-- Run the following transaction only after verifying that the older lead form is the one to keep.
START TRANSACTION;
CREATE TEMPORARY TABLE mjd_cleanup_ids (form_id INT PRIMARY KEY, form_name VARCHAR(255));
INSERT INTO mjd_cleanup_ids SELECT form_id, form_name FROM forms
WHERE (form_name='find_members' AND form_title='Find Members')
OR (form_name='bootstrap_get_match' AND form_title='Website - Lead Form - Get Matched' AND EXISTS (SELECT 1 FROM (SELECT form_name FROM forms) AS existing WHERE existing.form_name='website_lead_form_get_matched'));
DELETE ff FROM form_fields ff JOIN mjd_cleanup_ids c ON c.form_name=ff.form_name;
DELETE m FROM users_meta m JOIN mjd_cleanup_ids c ON c.form_id=m.database_id WHERE m.`database`='forms';
DELETE f FROM forms f JOIN mjd_cleanup_ids c ON c.form_id=f.form_id;
DROP TEMPORARY TABLE mjd_cleanup_ids;
COMMIT;
