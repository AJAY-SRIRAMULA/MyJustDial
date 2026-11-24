# Razorpay test checkout setup

1. Back up the database and import `database/razorpay_test_payments.sql` into the existing database using phpMyAdmin. This creates a unique payment/order ledger; no existing tables are altered.
2. Rotate the previously shared test secret in the Razorpay dashboard. In XAMPP Apache configuration (httpd.conf), add these directives with your own replacement test credentials, then restart Apache:

    SetEnv RAZORPAY_KEY_ID "rzp_test_REPLACE_ME"
    SetEnv RAZORPAY_KEY_SECRET "REPLACE_ME"

3. Enable PHP cURL and ensure TLS certificate verification works. Do not disable TLS verification. Enable automatic capture in the Razorpay test dashboard.
4. Choose a paid plan, select monthly or yearly billing, fill the signup form and submit. Complete Razorpay test checkout using dashboard-provided test payment details. The server verifies the signature, captured status, amount, currency and order before creating the account and recording payment. Free signup is unchanged except for widget rendering and hidden honeypot markup.
5. Check membership_test_payments and users_data. Cancelled or failed payments must not create accounts. Do not use real payment details.

Limitations: This is a test-only, one-time payment integration, not recurring billing. There is no webhook or automatic refund/reconciliation workflow. If a payment succeeds but registration fails, retry in the same session using the returned payment fields; if the session is lost, reconcile manually in the Razorpay dashboard. Server-side custom-field validation still happens at final signup, so test with valid data. The environment used to edit this ZIP has no MySQL/cURL PHP extensions and no browser verification was performed. The previous six-form routing limitations remain unchanged.

Widget preview: shortcode HTML inserted dynamically now executes its script elements; outdated AJAX responses are ignored. The Widget Manager replaces its sandboxed iframe on each preview. The bundled Day 5 seed defines company/GST fields in formfieldsD, not formfieldsC. If your installed formfieldsC only contains the account dropdown, use formfieldsD or add the conditional fields and JavaScript to your actual widget. No database widget content was overwritten.
