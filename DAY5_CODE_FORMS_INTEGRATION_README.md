# Day 5 – Code and Forms Integration

## Day 4 inspection first
The Day 4 practical page and README were inspected. It creates and retrieves a Hello World widget and can upgrade it to an interactive To-Do widget. Its interactive task list is client-side and does not persist task entries on refresh. The PHP page uses the existing widget shortcode renderer, which embeds widget HTML/CSS/JS in sandboxed iframes. Nested shortcode expansion inside a widget's HTML is not performed by that renderer.

## Day 5 implementation
- `formfieldsA`: first name and last name.
- `formfieldsB`: email and phone.
- `formfieldsC`: account type dropdown.
- `formfieldsD`: integrated five-field form (first name, last name, email, phone, account type) with Company Name and GST Number shown conditionally for Business.
- All four widgets are created/updated in the existing `data_widgets` table and readable aliases in existing `users_meta`.
- No new tables or columns are added.
- The Day 5 page displays A, B, C independently and D as the integrated widget.

## Test
1. Start Apache and MySQL in XAMPP and open the project.
2. Log in as Admin.
3. Open `/day5_code_forms_integration.php`.
4. Confirm A, B, and C render separately.
5. In D, select Individual: Company Name and GST Number remain hidden.
6. Select Business: both conditional fields appear.
7. Open the widget links to inspect their records in Widget Manager.

Browser/database runtime verification was not available in this build environment.
