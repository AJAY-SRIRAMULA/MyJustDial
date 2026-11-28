# Password retrieval setup

1. Back up the database and extract the project. Start MySQL using the existing configuration.
2. In a terminal in the project folder run `C:\xampp\php\php.exe tools\install-password-retrieval.php` (adjust PHP path if needed). Run only when you want to install the form and link. No page-load seeding or SQL import is required if the original password_retrieval_sessions table exists.
3. Check Form Manager for system name `listing_password_retrieval`. The installer adds a Custom HTML field with label `forgot_password.php` to the currently resolved member login form. Its order is before CAPTCHA or submit. The form is handled by a dedicated authentication endpoint, not general inquiry storage. Keep the email database variable `email`.
4. Configure PHP mail transport in your environment. For a deployed website set MYJUSTDIAL_RESET_ORIGIN to the trusted HTTPS origin (e.g. https://example.com); the configured base_url is appended. Default is http://localhost. Never use localhost links for remote recipients.
5. Test login link, dynamic field edits, CAPTCHA, request for a test member, email delivery, new password, expired/reused token rejection and login with the new password. The reset request response intentionally does not disclose whether the email exists. PHP mail acceptance is not proof of delivery. The new password form is a dedicated fixed authentication form, not a general destination form.

No existing forms other than the active login form's new link and field order are changed by the installer. No schema or database settings are modified. Reset tokens expire after 30 minutes and are consumed on successful password change. Existing signed-in sessions are not revoked by this feature.
