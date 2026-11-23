# Trusted PHP form components

Place developer-created PHP components in this directory. Reference a component by its filename in Form Manager → Custom HTML → Field Label, for example `profile_photo.php`.

Only simple PHP filenames are accepted. The loader resolves the file inside this directory and rejects paths, traversal, remote URLs, and files outside the directory. Do not allow members or public users to upload files into this directory. Components are included server-side and receive `$componentContext`, which can contain `pdo`, `member`, `form_id`, `field_id`, `user_id`, `context`, `preview`, `csrf`, and `base_url`.

Components must check the supplied `preview` flag before performing writes. Keep write operations in a separate authenticated endpoint with CSRF validation and authorization checks. Do not emit nested form elements from a component because it is rendered inside the existing form.


## Persisting values from a custom component

For public dynamic forms, name each submitted control using this pattern:

`component_fields[FIELD_ID][value_key]`

The component loader exposes `$fieldId`, `$formId`, `$userId`, `$renderContext`, `$isPreview`, `$pdo`, `$member`, `$csrf`, and `$baseUrl`.

Example:

```php
<input type="text" name="component_fields[<?= (int)$fieldId ?>][company_name]">
```

The public form renderer collects these controls on submit and sends them in `component_payload` as a fallback. The submission handler verifies that the field ID belongs to a configured trusted PHP component, then saves values in `form_inquiries.inquiry_content` and `users_meta` under `component_FIELD_ID_value_key`.

Do not use nested `<form>` elements inside a component. Do not process permanent writes during preview.


## Component field submission contract

Custom PHP component controls must use the standard form field collection, with the actual Form Manager field ID:

- `fields[component_FIELD_ID_course]`
- `fields[component_FIELD_ID_specialization]`

The public and member handlers validate that FIELD_ID belongs to a configured trusted PHP component before saving values. Public inquiries store values in `form_inquiries.inquiry_content` and `users_meta` (`database` = `form_inquiries`). Member forms store values in `users_meta` (`database` = `users_data`).

Do not use nested forms inside a component. Do not save data during preview.


## Course component persistence

`course.php` is an alias for `course_selection.php`. Both render the same course fields.
The controls submit as `fields[component_FIELD_ID_course]` and
`fields[component_FIELD_ID_specialization]`.

The public inquiry handler saves these values to `users_meta` with
`database = 'form_inquiries'` and `database_id = the new inquiry_id`.
The member account save handler saves them to `users_meta` with
`database = 'users_data'` and `database_id = the member user_id`.

Persistence is authorized by matching FIELD_ID to a Custom HTML field configured
on the submitted form, rather than requiring the field label to match a PHP filename.
