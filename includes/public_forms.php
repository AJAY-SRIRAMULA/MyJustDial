<?php
require_once __DIR__ . '/form_fields.php';
require_once __DIR__ . '/recaptcha.php';
require_once __DIR__ . '/custom_components.php';
require_once __DIR__ . '/widgets.php';

function getPublicFormByShortcode(PDO $pdo, string $shortcode): ?array
{
    $shortcode = trim($shortcode);

    if (preg_match('/^\\[form=([a-zA-Z0-9\_-]+)\\]$/', $shortcode, $matches)) {
        $shortcode = $matches[1];
    }

    $publicAliases = [
        'contact_form' => 'public_shortcode_contact_form',
        'member_login' => 'public_shortcode_member_login',
        'signup_free' => 'public_shortcode_signup_free',
        'signup_paid' => 'public_shortcode_signup_paid',
    ];

    $form = null;
    $mappedFormId = 0;

    if (isset($publicAliases[$shortcode]) && tableExists($pdo, 'users_meta')) {
        $mapping = $pdo->prepare(
            "SELECT m.database_id
             FROM users_meta m
             INNER JOIN forms f ON f.form_id = m.database_id
             WHERE m.`database` = 'forms'
             AND m.`key` = ?
             AND m.`value` = ?
             AND (m.`key` <> 'public_shortcode_contact_form' OR (
                 f.form_name NOT LIKE 'member\_contact\_details%'
                 AND NOT EXISTS (SELECT 1 FROM users_meta identity_meta WHERE identity_meta.`database` = 'forms' AND identity_meta.database_id = f.form_id AND identity_meta.`key` = 'member_form_seeded' AND identity_meta.value = 'member_contact_details')
             ))
             ORDER BY m.database_id DESC
             LIMIT 1"
        );

        $mapping->execute([$publicAliases[$shortcode], $shortcode]);
        $mappedFormId = (int)$mapping->fetchColumn();
    }

    if ($mappedFormId > 0) {
        $statement = $pdo->prepare(
            "SELECT *
             FROM forms
             WHERE form_id = ?
             AND form_action_type <> 'default'
             LIMIT 1"
        );

        $statement->execute([$mappedFormId]);
        $form = $statement->fetch(PDO::FETCH_ASSOC);
    }

    if (!$form) {
        $candidates = [$shortcode];

        if ($shortcode === 'contact_form') {
            $candidates = ['contact_form_custom', 'nxt_wave_form_custom', 'contact_form'];
        } elseif ($shortcode === 'member_login') {
            $candidates = ['member_login_custom', 'login_custom', 'member_login'];
        } elseif ($shortcode === 'signup_free') {
            $candidates = ['signup_free_custom', 'signup_free'];
        }

        $conditions = [];
        $params = [];

        foreach ($candidates as $candidate) {
            $conditions[] = 'form_name = ?';
            $params[] = $candidate;

            $conditions[] = 'short_code = ?';
            $params[] = '[form=' . $candidate . ']';
        }

        if ($shortcode === 'contact_form') {
            $conditions = ['form_name IN (?, ?, ?)'];
            $params = $candidates;
        } elseif ($shortcode === 'member_login') {
            $conditions[] = "(form_title LIKE '%Login%' AND form_action_type <> 'default')";
        }

        $statement = $pdo->prepare(
            'SELECT *
             FROM forms
             WHERE (' . implode(' OR ', $conditions) . ')
             AND form_action_type <> ?
             ORDER BY
                CASE
                    WHEN form_name LIKE ? THEN 0
                    WHEN form_name LIKE ? THEN 1
                    ELSE 2
                END,
                form_id DESC
             LIMIT 1'
        );

        $statement->execute(array_merge(
            $params,
            ['default', '%\_custom%', '%custom%']
        ));

        $form = $statement->fetch(PDO::FETCH_ASSOC);
    }

    if (!$form) {
        if ($shortcode === 'contact_form') {
            $fallbackNames = ['nxt_wave_form', 'contact_form'];
            $fallbackTitle = '';
        } elseif ($shortcode === 'member_login') {
            $fallbackNames = ['member_login', 'login'];
            $fallbackTitle = '%Login%';
        } elseif ($shortcode === 'signup_free') {
            $fallbackNames = ['signup_free'];
            $fallbackTitle = '%Membership%';
        } else {
            $fallbackNames = [$shortcode];
            $fallbackTitle = '';
        }

        $fallbackConditions = [];
        $fallbackParams = [];

        foreach ($fallbackNames as $fallbackName) {
            $fallbackConditions[] = 'form_name = ?';
            $fallbackParams[] = $fallbackName;
        }

        if ($fallbackTitle !== '') {
            $fallbackConditions[] = 'form_title LIKE ?';
            $fallbackParams[] = $fallbackTitle;
        }

        $fallbackStatement = $pdo->prepare(
            "SELECT *
             FROM forms
             WHERE (" . implode(' OR ', $fallbackConditions) . ")
             ORDER BY
                CASE WHEN form_action_type = 'default' THEN 0 ELSE 1 END,
                form_id DESC
             LIMIT 1"
        );

        $fallbackStatement->execute($fallbackParams);
        $form = $fallbackStatement->fetch(PDO::FETCH_ASSOC);
    }

    if (!$form) {
        return null;
    }

    if (tableExists($pdo, 'users_meta')) {
        $status = $pdo->prepare(
            "SELECT value
             FROM users_meta
             WHERE `database` = 'forms'
             AND database_id = ?
             AND `key` = 'form_status'
             LIMIT 1"
        );

        $status->execute([(int)$form['form_id']]);

        if ($status->fetchColumn() === 'disabled') {
            if ($shortcode === 'contact_form') {
                $defaultNames = ['contact_form', 'nxt_wave_form'];
            } elseif ($shortcode === 'member_login') {
                $defaultNames = ['member_login', 'login'];
            } elseif ($shortcode === 'signup_free') {
                $defaultNames = ['signup_free'];
            } else {
                $defaultNames = [];
            }

            if ($defaultNames) {
                $placeholders = implode(',', array_fill(0, count($defaultNames), '?'));

                $defaultStatement = $pdo->prepare(
                    "SELECT *
                     FROM forms
                     WHERE form_action_type = 'default'
                     AND form_name IN ($placeholders)
                     ORDER BY FIELD(form_name, " . implode(',', array_fill(0, count($defaultNames), '?')) . ")
                     LIMIT 1"
                );

                $defaultStatement->execute(array_merge($defaultNames, $defaultNames));
                $defaultForm = $defaultStatement->fetch(PDO::FETCH_ASSOC);

                if ($defaultForm) {
                    $defaultStatus = $pdo->prepare(
                        "SELECT value
                         FROM users_meta
                         WHERE `database` = 'forms'
                         AND database_id = ?
                         AND `key` = 'form_status'
                         LIMIT 1"
                    );

                    $defaultStatus->execute([(int)$defaultForm['form_id']]);

                    if ($defaultStatus->fetchColumn() !== 'disabled') {
                        $form = $defaultForm;
                    } else {
                        return null;
                    }
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
    }

    $fields = $pdo->prepare(
        'SELECT *
         FROM form_fields
         WHERE form_name = ?
         ORDER BY field_order, field_id'
    );

    $fields->execute([$form['form_name']]);
    $form['fields'] = $fields->fetchAll(PDO::FETCH_ASSOC);

    return $form;
}

function publicFormFieldType(array $field): string
{
    return formFieldInternalType((string)($field['field_type'] ?? 'text'));
}

function publicFormFieldOptions(array $field): array
{
    return array_values(array_filter(
        preg_split('/\r\n|\r|\n/', (string)($field['field_options'] ?? '')),
        static fn($option) => trim((string)$option) !== ''
    ));
}

function renderPublicForm(PDO $pdo, string $shortcode, string $csrfToken): string
{
    $form = getPublicFormByShortcode($pdo, $shortcode);

    if (!$form) {
        return '<div class="contact-form-alert contact-form-alert-error">The Contact Form is not available. Please create or enable the form in Admin → Toolbox → Form Manager.</div>';
    }

    $settingsQuery = $pdo->prepare("SELECT value FROM users_meta WHERE `database`='forms' AND database_id=? AND `key`='form_settings_json' LIMIT 1");
    $settingsQuery->execute([$form['form_id']]);
    $settings = json_decode((string)$settingsQuery->fetchColumn(), true) ?: [];
    $action = e($_SERVER['REQUEST_URI'] ?? '');
    $html = '<form id="' . e($form['form_element_id'] ?: $form['form_name']) . '" class="public-dynamic-form ' . e($form['form_class'] ?? '') . '" style="' . e($form['form_style'] ?? '') . '" method="post" action="' . $action . '">';

    $html .= '<input type="hidden" name="public_form_shortcode" value="' . e(preg_match('/^\\[form=([a-zA-Z0-9\_-]+)\\]$/', trim($shortcode), $shortcodeMatches) ? $shortcodeMatches[1] : $shortcode) . '">';
    $html .= '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">';
    $html .= '<div class="public-form-honeypot" aria-hidden="true"><label>Leave this field empty<input type="text" name="website_address" tabindex="-1" autocomplete="off" data-fake-filler-ignore="true" hidden></label></div>';

    foreach ($form['fields'] as $field) {
        if (($field['field_input_view'] ?? '1') === '0') {
            continue;
        }

        $type = publicFormFieldType($field);
        $name = trim((string)$field['field_name']);
        $label = trim((string)$field['field_text']);
        $required = !empty($field['field_required']);
        $placeholder = !empty($settings['label_placeholder']) ? ($label ?: $name) : (string)$field['field_placeholder'];
        $value = (string)$field['default_value'];
        $options = publicFormFieldOptions($field);

        if ($type === 'captcha') { $html .= renderConfiguredCaptcha($pdo); continue; }
        if (in_array($type, ['submit', 'honeypot'], true)) {
            continue;
        }

        if ($type === 'section') {
            $html .= '<div class="public-form-section-title">' . e($label) . '</div>';
            continue;
        }

        if ($type === 'html') {
            $widgetContent = $label . ' ' . (string)($field['field_ldesc'] ?? '');
            if (preg_match('/\[widget=[^\]\r\n]+\]/', $widgetContent)) {
                $html .= renderWidgetShortcodes($pdo, $widgetContent);
                continue;
            }
            if (isTrustedComponentReference($label)) {
                $componentMember = null;
                $componentUserId = (int)($_SESSION['member_id'] ?? 0);
                if ($componentUserId > 0) {
                    $componentMemberQuery = $pdo->prepare('SELECT * FROM users_data WHERE user_id = ? LIMIT 1');
                    $componentMemberQuery->execute([$componentUserId]);
                    $componentMember = $componentMemberQuery->fetch(PDO::FETCH_ASSOC) ?: null;
                }
                $html .= renderTrustedPhpComponent($label, [
                    'pdo' => $pdo,
                    'member' => $componentMember,
                    'user_id' => $componentUserId,
                    'form_id' => (int)($form['form_id'] ?? 0),
                    'field_id' => (int)$field['field_id'],
                    'context' => 'public-form',
                    'preview' => !$componentMember,
                    'csrf' => $csrfToken,
                    'base_url' => $GLOBALS['config']['base_url']
                ]);
            } elseif ($label !== '') {
                $html .= '<div class="public-form-description">' . nl2br(e($label)) . '</div>';
            }
            continue;
        }

        if ($type === 'help_alert') {
            $html .= '<div class="public-form-help">' . nl2br(e($field['field_sdesc'] ?: $label)) . '</div>';
            continue;
        }

        if ($name === '') {
            continue;
        }

        $inputName = 'fields[' . e($name) . ']';
        $id = 'contact-field-' . (int)$field['field_id'];
        $requiredAttribute = $required ? ' required' : '';
        $requiredMark = $required ? '<span class="public-form-required" aria-hidden="true">*</span>' : '';
        $help = trim((string)$field['field_sdesc']);

        $html .= '<div class="public-form-field"' . (($settings['label_position'] ?? '') === 'left' ? ' style="display:grid;grid-template-columns: minmax(100px, 1fr) 2fr;gap:10px;align-items:center"' : '') . '>';

        if ($type !== 'hidden') {
            $html .= '<label for="' . e($id) . '">' . e($label ?: $name) . $requiredMark . '</label>';
        }

        if ($type === 'textarea' || in_array($type, ['richtext', 'richtext_image', 'richtext_elements', 'richtext_media'], true)) {
            $html .= '<textarea class="' . e($settings['input_class'] ?? '') . '" id="' . e($id) . '" name="' . $inputName . '" placeholder="' . e($placeholder) . '"' . $requiredAttribute . '>' . e($value) . '</textarea>';
        } elseif ($type === 'select' || $type === 'country' || $type === 'state' || $type === 'top_category' || $type === 'years') {
            $html .= '<select class="' . e($settings['input_class'] ?? '') . '" id="' . e($id) . '" name="' . $inputName . '"' . $requiredAttribute . '>';
            $html .= '<option value="">' . e($placeholder ?: 'Select an option') . '</option>';

            foreach ($options as $option) {
                $html .= '<option value="' . e($option) . '"' . ($value === $option ? ' selected' : '') . '>' . e($option) . '</option>';
            }

            $html .= '</select>';
        } elseif ($type === 'radio' || $type === 'yes_no') {
            $radioOptions = $type === 'yes_no' ? ['Yes', 'No'] : $options;
            $html .= '<div class="public-form-choice-group">';

            foreach ($radioOptions as $index => $option) {
                $choiceId = $id . '-' . $index;
                $html .= '<label class="public-form-choice" for="' . e($choiceId) . '"><input id="' . e($choiceId) . '" type="radio" name="' . $inputName . '" value="' . e($option) . '"' . ($value === $option ? ' checked' : '') . $requiredAttribute . '> ' . e($option) . '</label>';
            }

            $html .= '</div>';
        } elseif ($type === 'checkbox_group') {
            $html .= '<div class="public-form-choice-group">';

            foreach ($options as $index => $option) {
                $choiceId = $id . '-' . $index;
                $html .= '<label class="public-form-choice" for="' . e($choiceId) . '"><input id="' . e($choiceId) . '" type="checkbox" name="' . $inputName . '[]" value="' . e($option) . '"> ' . e($option) . '</label>';
            }

            $html .= '</div>';
        } else {
            $inputType = match ($type) {
                'email' => 'email',
                'tel', 'phone_country' => 'tel',
                'number', 'price', 'years' => 'number',
                'url' => 'url',
                'date' => 'date',
                'datetime-local' => 'datetime-local',
                'password' => 'password',
                'hidden' => 'hidden',
                default => 'text'
            };

            $html .= '<input class="' . e($settings['input_class'] ?? '') . '" id="' . e($id) . '" type="' . e($inputType) . '" name="' . $inputName . '" value="' . e($value) . '" placeholder="' . e($placeholder) . '"' . $requiredAttribute . '>';
        }

        if ($help !== '') {
            $html .= '<small class="public-form-help-text">' . e($help) . '</small>';
        }

        $html .= '</div>';
    }

    $html .= '<button class="public-form-submit" type="submit">Send Message</button>';
    $html .= '</form>';

    return $html;
}

function processPublicFormSubmission(PDO $pdo, string $shortcode): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['ok' => false, 'message' => 'Invalid request.'];
    }

    if (!hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) {
        return ['ok' => false, 'message' => 'Your session expired. Refresh the page and try again.'];
    }

    if (!empty($_POST['website_address'])) {
        return ['ok' => false, 'message' => 'Your submission could not be processed.'];
    }

    $form = getPublicFormByShortcode($pdo, $shortcode);

    if (!$form) {
        return ['ok' => false, 'message' => 'This form is currently unavailable.'];
    }

    $captchaError = configuredCaptchaError($pdo, $form['fields']);
    if ($captchaError !== '') return ['ok' => false, 'message' => $captchaError];

    $destinationTable = trim((string)($form['form_table'] ?? 'form_inquiries'));
    if ($destinationTable === '') {
        $destinationTable = 'form_inquiries';
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $destinationTable) || !tableExists($pdo, $destinationTable)) {
        return ['ok' => false, 'message' => 'The configured form destination table is not available.'];
    }

    foreach ($form['fields'] as $field) {
        if (publicFormFieldType($field) === 'password') {
            return [
                'ok' => false,
                'message' => 'Password fields cannot be stored by standard forms. Use the dedicated Member Login Form.'
            ];
        }
    }

    $submitted = $_POST['fields'] ?? [];

    if (!is_array($submitted)) {
        $submitted = [];
    }

    $values = [];
    $customValues = [];
    $defaultFieldNames = [];
    $defaultFields = $pdo->prepare(
        "SELECT field_name
         FROM form_fields
         WHERE form_name = ? AND field_name <> ''"
    );
    $defaultFields->execute(['nxt_wave_form']);

    foreach ($defaultFields->fetchAll(PDO::FETCH_COLUMN) as $defaultFieldName) {
        $defaultFieldNames[(string)$defaultFieldName] = true;
    }

    if (!$defaultFieldNames) {
        $defaultFieldNames = [
            'yourname' => true,
            'inquiry_email' => true,
            'phone' => true,
            'comments' => true,
        ];
    }

    $defaultFieldNames['enter_a_message'] = true;

    $errors = [];
    $nameValue = '';
    $emailValue = '';
    $phoneValue = '';
    $commentsValue = '';

    foreach ($form['fields'] as $field) {
        if (($field['field_input_view'] ?? '1') === '0') {
            continue;
        }

        $type = publicFormFieldType($field);
        $fieldName = trim((string)$field['field_name']);

        if (
            $fieldName === ''
            || in_array($type, ['submit', 'section', 'html', 'help_alert', 'honeypot', 'captcha'], true)
        ) {
            continue;
        }

        $rawValue = $submitted[$fieldName] ?? '';

        if (is_array($rawValue)) {
            $rawValue = array_map(
                static fn($item) => trim((string)$item),
                $rawValue
            );
        } else {
            $rawValue = trim((string)$rawValue);
        }

        $isEmpty = is_array($rawValue)
            ? count(array_filter($rawValue, static fn($item) => $item !== '')) === 0
            : $rawValue === '';

        if (!empty($field['field_required']) && $isEmpty) {
            $errors[] = ($field['field_text'] ?: $fieldName) . ' is required.';
            continue;
        }

        if ($isEmpty) {
            $values[$fieldName] = is_array($rawValue) ? [] : '';

            if (!isset($defaultFieldNames[$fieldName])) {
                $customValues[] = [
                    'key' => $fieldName,
                    'value' => $rawValue,
                ];
            }

            continue;
        }

        if (is_array($rawValue)) {
            if ($type !== 'checkbox_group') {
                $errors[] = 'Invalid value submitted for ' . ($field['field_text'] ?: $fieldName) . '.';
                continue;
            }

            $allowed = publicFormFieldOptions($field);
            $rawValue = array_values(array_intersect($rawValue, $allowed));
        } else {
            if (in_array($type, ['select', 'radio', 'yes_no'], true)) {
                $allowed = $type === 'yes_no' ? ['Yes', 'No'] : publicFormFieldOptions($field);

                if (!in_array($rawValue, $allowed, true)) {
                    $errors[] = 'Invalid option selected for ' . ($field['field_text'] ?: $fieldName) . '.';
                    continue;
                }
            }

            if ($type === 'email' && !filter_var($rawValue, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid email address.';
                continue;
            }

            if ($type === 'url' && !filter_var($rawValue, FILTER_VALIDATE_URL)) {
                $errors[] = 'Enter a valid URL.';
                continue;
            }

            if (in_array($type, ['number', 'price', 'years'], true) && !is_numeric($rawValue)) {
                $errors[] = 'Enter a valid number for ' . ($field['field_text'] ?: $fieldName) . '.';
                continue;
            }

            if (in_array($type, ['tel', 'phone_country'], true) && !validFormPhone($rawValue)) {
                $errors[] = 'Enter a valid phone number.';
                continue;
            }
        }

        $values[$fieldName] = $rawValue;

        if (!isset($defaultFieldNames[$fieldName])) {
            $customValues[] = [
                'key' => $fieldName,
                'value' => $rawValue,
            ];
        }

        $label = strtolower(trim((string)$field['field_text']));
        $normalizedFieldName = strtolower(trim((string)$fieldName));
        $normalizedFieldName = preg_replace('/[^a-z0-9]+/', '_', $normalizedFieldName);
        $normalizedLabel = preg_replace('/[^a-z0-9]+/', ' ', $label);
        $normalizedLabel = trim(preg_replace('/\s+/', ' ', $normalizedLabel));
        $isCommentsField = in_array($normalizedFieldName, ['comments', 'enter_a_message'], true)
            || in_array($normalizedLabel, ['comments', 'message', 'enter a message'], true);

        if ($type === 'email') {
            $emailValue = is_array($rawValue) ? '' : $rawValue;
        } elseif (in_array($type, ['tel', 'phone_country'], true)) {
            $phoneValue = is_array($rawValue) ? '' : $rawValue;
        } elseif ($isCommentsField) {
            $commentsValue = is_array($rawValue) ? implode(', ', $rawValue) : $rawValue;
        } elseif (
            $nameValue === ''
            && (
                in_array($normalizedFieldName, [
                    'name',
                    'full_name',
                    'your_name',
                    'contact_name',
                    'first_name',
                    'yourname',
                    'customer_name'
                ], true)
                || in_array($normalizedLabel, [
                    'name',
                    'full name',
                    'your name',
                    'your full name',
                    'contact name',
                    'first name',
                    'customer name'
                ], true)
            )
        ) {
            $nameValue = is_array($rawValue) ? implode(' ', $rawValue) : $rawValue;
        }
    }

    // Save values submitted by controls rendered inside configured Custom HTML fields.
    // Verify the field ID against this form; do not require a particular PHP filename.
    $customHtmlFieldIds = [];
    foreach ($form['fields'] as $configuredField) {
        if (publicFormFieldType($configuredField) === 'html'
            && (string)($configuredField['field_input_view'] ?? '1') !== '0') {
            $customHtmlFieldIds[(int)$configuredField['field_id']] = true;
        }
    }

    $componentValuesByField = [];
    foreach ($submitted as $submittedKey => $submittedValue) {
        if (!preg_match('/^component_(\d+)_([a-zA-Z0-9_-]{1,80})$/', (string)$submittedKey, $matches)) {
            continue;
        }

        $componentFieldId = (int)$matches[1];
        $valueKey = $matches[2];
        if (!isset($customHtmlFieldIds[$componentFieldId])) {
            continue;
        }

        if (is_array($submittedValue)) {
            $storedValue = json_encode(
                array_values(array_map(static fn($item) => trim((string)$item), $submittedValue)),
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
            if ($storedValue === false) {
                $errors[] = 'A custom field value could not be processed.';
                continue;
            }
        } elseif (is_scalar($submittedValue) || $submittedValue === null) {
            $storedValue = trim((string)$submittedValue);
        } else {
            continue;
        }

        $metaKey = 'component_' . $componentFieldId . '_' . $valueKey;
        $values[$metaKey] = $storedValue;
        $customValues[] = [
            'key' => $metaKey,
            'value' => $storedValue,
        ];
        $componentValuesByField[$componentFieldId][$valueKey] = $storedValue;
    }

    foreach ($componentValuesByField as $componentValues) {
        if (!array_key_exists('course', $componentValues) && !array_key_exists('specialization', $componentValues)) {
            continue;
        }

        $courseOptions = [
            'btech' => ['Computer Science', 'Information Technology', 'Electronics', 'Mechanical', 'Civil'],
            'mba' => ['Finance', 'Marketing', 'Human Resources', 'Business Analytics'],
            'bca' => ['Computer Applications', 'Web Development', 'Data Science'],
            'mtech' => ['Computer Science', 'VLSI', 'Structural Engineering'],
        ];

        $selectedCourse = (string)($componentValues['course'] ?? '');
        $selectedSpecialization = (string)($componentValues['specialization'] ?? '');

        if (!isset($courseOptions[$selectedCourse])
            || !in_array($selectedSpecialization, $courseOptions[$selectedCourse], true)) {
            $errors[] = 'Choose a valid course and specialization.';
        }
    }

    if ($errors) {
        return ['ok' => false, 'message' => implode(' ', $errors)];
    }

    if ($emailValue === '') {
        foreach ($values as $key => $value) {
            if (stripos($key, 'email') !== false && is_string($value)) {
                $emailValue = $value;
                break;
            }
        }
    }

    if ($customValues && !tableExists($pdo, 'users_meta')) {
        return ['ok' => false, 'message' => 'Customized form fields could not be saved. Please try again later.'];
    }

    try {
        $pdo->beginTransaction();

        if ($destinationTable === 'form_inquiries') {
            $insert = $pdo->prepare(
                'INSERT INTO form_inquiries (
                    inquiry_email, inquiry_ip, yourname, phone, comments,
                    inquiry_user_id, inquiry_form, inquiry_content, inquiry_status,
                    inquiry_archived, inquiry_notes, url_origin, date_submitted, user_consent
                ) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, 0, ?, ?, ?, ?)'
            );
            $insert->execute([
                $emailValue,
                substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 40),
                $nameValue,
                $phoneValue,
                $commentsValue,
                $form['form_name'],
                json_encode($values, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'New',
                '',
                substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 255),
                date('YmdHis'),
                ''
            ]);
            $inquiryId = $pdo->lastInsertId();
        } else {
            $columnQuery = $pdo->prepare(
                'SELECT COLUMN_NAME, COLUMN_KEY, EXTRA, IS_NULLABLE, COLUMN_DEFAULT
                 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION'
            );
            $columnQuery->execute([$destinationTable]);
            $destinationColumns = $columnQuery->fetchAll(PDO::FETCH_ASSOC);
            $insertData = array_diff_key($values, array_fill_keys(array_column($customValues, 'key'), true));
            $insertData += [
                'inquiry_ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 40),
                'inquiry_user_id' => 0, 'inquiry_status' => 'New', 'inquiry_archived' => 0,
                'inquiry_notes' => '', 'user_consent' => '',
                'email' => $emailValue, 'inquiry_email' => $emailValue,
                'name' => $nameValue, 'yourname' => $nameValue, 'full_name' => $nameValue,
                'phone' => $phoneValue, 'comments' => $commentsValue, 'message' => $commentsValue,
                'inquiry_content' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'inquiry_form' => (string)$form['form_name'], 'date_submitted' => date('YmdHis'),
                'created_at' => date('Y-m-d H:i:s'), 'date_added' => date('YmdHis'),
                'url_origin' => substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 255)
            ];
            $normalizedInsertData = [];
            foreach ($insertData as $dataKey => $dataValue) {
                $normalizedKey = strtolower(trim((string)$dataKey));
                $normalizedKey = preg_replace('/[^a-z0-9]+/', '_', $normalizedKey);
                $normalizedInsertData[$normalizedKey] = $dataValue;
            }
            foreach ($form['fields'] as $configuredField) {
                $fieldKey = trim((string)($configuredField['field_name'] ?? ''));
                $fieldLabel = trim((string)($configuredField['field_text'] ?? ''));
                if ($fieldKey === '' || !array_key_exists($fieldKey, $values)) {
                    continue;
                }
                foreach ([$fieldKey, $fieldLabel] as $candidateKey) {
                    $normalizedKey = strtolower(trim($candidateKey));
                    $normalizedKey = preg_replace('/[^a-z0-9]+/', '_', $normalizedKey);
                    if ($normalizedKey !== '') {
                        $normalizedInsertData[$normalizedKey] = $values[$fieldKey];
                    }
                }
            }
            $matched = [];
            foreach ($destinationColumns as $column) {
                $columnName = (string)$column['COLUMN_NAME'];
                if (($column['COLUMN_KEY'] === 'PRI' && stripos((string)$column['EXTRA'], 'auto_increment') !== false)
                    || stripos((string)$column['EXTRA'], 'generated') !== false) {
                    continue;
                }
                if (array_key_exists($columnName, $insertData)) {
                    $columnValue = $insertData[$columnName];
                } else {
                    $normalizedColumn = strtolower(trim($columnName));
                    $normalizedColumn = preg_replace('/[^a-z0-9]+/', '_', $normalizedColumn);
                    if (array_key_exists($normalizedColumn, $normalizedInsertData)) {
                        $columnValue = $normalizedInsertData[$normalizedColumn];
                    } elseif ($column['IS_NULLABLE'] === 'NO' && $column['COLUMN_DEFAULT'] === null) {
                        throw new RuntimeException('A required destination column has no matching form field: ' . $columnName);
                    } else {
                        continue;
                    }
                }
                $matched[$columnName] = is_array($columnValue) ? json_encode($columnValue, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : $columnValue;
            }
            if (!$matched) {
                throw new RuntimeException('No submitted form values match the selected destination table.');
            }
            $names = array_keys($matched);
            $quotedNames = array_map(static fn($name) => '`' . str_replace('`', '``', $name) . '`', $names);
            $placeholders = implode(',', array_fill(0, count($names), '?'));
            $genericInsert = $pdo->prepare('INSERT INTO `' . str_replace('`', '``', $destinationTable) . '` (' . implode(',', $quotedNames) . ') VALUES (' . $placeholders . ')');
            $genericInsert->execute(array_values($matched));
            $inquiryId = $pdo->lastInsertId();
        }

        if ($customValues) {
            if ($inquiryId === false || $inquiryId === '0' || $inquiryId === '') {
                throw new RuntimeException('The inquiry ID could not be retrieved.');
            }

            $saveCustomValue = $pdo->prepare(
                "INSERT INTO users_meta (`database`, database_id, `key`, `value`, date_added)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $dateAdded = date('YmdHis');

            foreach ($customValues as $customValue) {
                $storedValue = is_array($customValue['value'])
                    ? json_encode($customValue['value'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
                    : (string)$customValue['value'];

                if ($storedValue === false) {
                    throw new RuntimeException('A customized field value could not be encoded.');
                }

                $saveCustomValue->execute([
                    $destinationTable,
                    $inquiryId,
                    $customValue['key'],
                    $storedValue,
                    $dateAdded,
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Public form submission failed: ' . $exception->getMessage());
        return ['ok' => false, 'message' => 'Your message could not be saved. Please try again.'];
    }

    return ['ok' => true, 'message' => 'Thank you! Your message has been sent successfully.'];
}

function renderPublicLoginForm(PDO $pdo, string $shortcode, string $csrfToken, string $email = ''): string
{
    $form = getPublicFormByShortcode($pdo, $shortcode);
    $shortcodeKey = $shortcode;

    if (preg_match('/^\\[form=([a-zA-Z0-9\_-]+)\\]$/', trim($shortcode), $matches)) {
        $shortcodeKey = $matches[1];
    }

    if (
        !$form
        || (
            ($form['form_action_type'] ?? '') !== 'login'
            && $shortcodeKey !== 'member_login'
        )
    ) {
        return '<div class="login-message" role="alert">The Member Login Form is not available. Please create it in Admin → Toolbox → Form Manager.</div>';
    }

    $html = '<form class="public-login-form" method="post" action="' . e($_SERVER['PHP_SELF'] ?? '') . '">';
    $html .= '<input type="hidden" name="public_form_shortcode" value="' . e($shortcodeKey) . '">';
    $html .= '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">';
    $html .= '<div class="public-form-honeypot" aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;"><label>Leave this field empty<input type="text" name="website_address" tabindex="-1" autocomplete="off" data-fake-filler-ignore="true" hidden></label></div>';

    $submitLabel = 'Login Now';

    foreach ($form['fields'] as $field) {
        if (($field['field_input_view'] ?? '1') === '0') {
            continue;
        }

        $type = publicFormFieldType($field);
        $name = trim((string)($field['field_name'] ?? ''));
        $label = trim((string)($field['field_text'] ?? ''));
        $placeholder = (string)($field['field_placeholder'] ?? '');
        $required = !empty($field['field_required']);
        $requiredAttribute = $required ? ' required' : '';
        $requiredMark = $required ? '<span class="required-mark" aria-hidden="true">*</span>' : '';
        $id = 'login-field-' . (int)$field['field_id'];
        $options = publicFormFieldOptions($field);
        $semantic = strtolower($name . ' ' . $label);

        $isEmailField = $type === 'email' || preg_match('/\\b(email|e-mail)\\b/i', $semantic);
        $isPasswordField = $type === 'password' || preg_match('/\\b(password|passcode)\\b/i', $semantic);

        if ($type === 'submit') {
            if ($label !== '') {
                $submitLabel = (
                    $shortcodeKey === 'member_login'
                    && strtolower($label) === 'submit message'
                ) ? 'Log In Now' : $label;
            }

            continue;
        }

        if ($type === 'honeypot') {
            $html .= '<div class="public-form-honeypot" aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;"><input type="text" name="extra_honeypot_' . (int)$field['field_id'] . '" tabindex="-1" autocomplete="off"></div>';
            continue;
        }

        if ($type === 'captcha') {
            $html .= renderConfiguredCaptcha($pdo);
            continue;
        }

        if ($type === 'section') {
            $html .= '<div class="login-field login-section-title">' . e($label) . '</div>';
            continue;
        }

        if ($type === 'html') {
            $widgetContent = $label . ' ' . (string)($field['field_ldesc'] ?? '');
            if (preg_match('/\[widget=[^\]\r\n]+\]/', $widgetContent)) {
                $html .= renderWidgetShortcodes($pdo, $widgetContent);
                continue;
            }
            if (isTrustedComponentReference($label)) {
                $html .= renderTrustedPhpComponent($label, [
                    'pdo' => $pdo,
                    'member' => null,
                    'form_id' => (int)($form['form_id'] ?? 0),
                    'field_id' => (int)$field['field_id'],
                    'user_id' => 0,
                    'context' => 'public-login',
                    'preview' => true,
                    'csrf' => $csrfToken,
                    'base_url' => $GLOBALS['config']['base_url']
                ]);
            } elseif ($label !== '') {
                $html .= '<div class="login-field login-description">' . nl2br(e($label)) . '</div>';
            }
            continue;
        }

        if ($type === 'help_alert') {
            $helpText = trim((string)($field['field_sdesc'] ?? $label));

            if ($helpText !== '') {
                $html .= '<div class="login-field login-help">' . nl2br(e($helpText)) . '</div>';
            }

            continue;
        }

        if ($name === '') {
            continue;
        }

        $isEmail = $type === 'email' || preg_match('/\b(email|e-mail)\b/i', $semantic);
        $isPassword = $type === 'password' || preg_match('/\b(password|passcode)\b/i', $semantic);
        $postName = $isEmail ? 'email' : ($isPassword ? 'password' : $name);

        $inputType = $isPassword ? 'password' : ($isEmail ? 'email' : match ($type) {
            'tel', 'phone_country' => 'tel',
            'number', 'price' => 'number',
            'url' => 'url',
            'date' => 'date',
            'datetime-local' => 'datetime-local',
            'hidden' => 'hidden',
            default => 'text',
        });

        $value = $isEmail ? $email : (string)($field['default_value'] ?? '');
        $help = trim((string)($field['field_sdesc'] ?? ''));
        $inputName = 'fields[' . e($postName) . ']';

        $html .= '<div class="login-field">';

        if ($inputType !== 'hidden') {
            $html .= '<label for="' . e($id) . '"><span>' . $requiredMark . e($label ?: $name) . '</span></label>';
        }

        if (in_array($type, ['textarea', 'richtext', 'richtext_image', 'richtext_elements', 'richtext_media'], true)) {
            $html .= '<textarea id="' . e($id) . '" name="' . $inputName . '" placeholder="' . e($placeholder) . '"' . $requiredAttribute . '>' . e($value) . '</textarea>';
        } elseif (in_array($type, ['select', 'country', 'state', 'top_category', 'years'], true)) {
            $html .= '<select id="' . e($id) . '" name="' . $inputName . '"' . $requiredAttribute . '>';
            $html .= '<option value="">' . e($placeholder ?: 'Select an option') . '</option>';

            foreach ($options as $option) {
                $html .= '<option value="' . e($option) . '"' . ($value === $option ? ' selected' : '') . '>' . e($option) . '</option>';
            }

            $html .= '</select>';
        } elseif ($type === 'radio' || $type === 'yes_no') {
            $choiceOptions = $type === 'yes_no' ? ['Yes', 'No'] : $options;
            $html .= '<div class="login-choice-group">';

            foreach ($choiceOptions as $index => $option) {
                $choiceId = $id . '-' . $index;
                $html .= '<label for="' . e($choiceId) . '"><input id="' . e($choiceId) . '" type="radio" name="' . $inputName . '" value="' . e($option) . '"' . $requiredAttribute . '> ' . e($option) . '</label>';
            }

            $html .= '</div>';
        } elseif ($type === 'checkbox_group') {
            $html .= '<div class="login-choice-group">';

            foreach ($options as $index => $option) {
                $choiceId = $id . '-' . $index;
                $html .= '<label for="' . e($choiceId) . '"><input id="' . e($choiceId) . '" type="checkbox" name="' . $inputName . '[]" value="' . e($option) . '"> ' . e($option) . '</label>';
            }

            $html .= '</div>';
        } else {
            $autocomplete = $isPassword ? 'current-password' : ($isEmail ? 'username' : 'off');
            $html .= '<input id="' . e($id) . '" type="' . e($inputType) . '" name="' . $inputName . '" value="' . e($value) . '" placeholder="' . e($placeholder) . '" autocomplete="' . $autocomplete . '"' . $requiredAttribute . '>';
        }

        if ($help !== '') {
            $html .= '<small class="login-help-text">' . e($help) . '</small>';
        }

        $html .= '</div>';
    }

    $html .= '<button class="login-submit" type="submit">' . e($submitLabel) . '</button>';
    $html .= '</form>';

    return $html;
}

function processPublicLoginSubmission(PDO $pdo, string $shortcode): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['ok' => false, 'message' => 'Invalid request.'];
    }

    if (!hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) {
        return ['ok' => false, 'message' => 'Your session expired. Refresh the page and try again.'];
    }

    if (!empty($_POST['website_address'])) {
        return ['ok' => false, 'message' => 'The email address or password is incorrect.'];
    }

    $form = getPublicFormByShortcode($pdo, $shortcode);

    $shortcodeKey = trim($shortcode);

    if (preg_match('/^\\[form=([a-zA-Z0-9\_-]+)\\]$/', $shortcodeKey, $matches)) {
        $shortcodeKey = $matches[1];
    }

    if (
        !$form
        || (
            ($form['form_action_type'] ?? '') !== 'login'
            && $shortcodeKey !== 'member_login'
        )
    ) {
        return ['ok' => false, 'message' => 'The Member Login Form is currently unavailable.'];
    }

    $captchaError = configuredCaptchaError($pdo, $form['fields']);
    if ($captchaError !== '') return ['ok' => false, 'message' => $captchaError];
    $hasEmailField = false;
    $hasPasswordField = false;

    foreach ($form['fields'] as $field) {
        $fieldName = trim((string)($field['field_name'] ?? ''));
        $fieldLabel = trim((string)($field['field_text'] ?? ''));
        $semantic = strtolower($fieldName . ' ' . $fieldLabel);
        $fieldType = publicFormFieldType($field);

        if ($fieldType === 'email' || preg_match('/\b(email|e-mail)\b/i', $semantic)) {
            $hasEmailField = true;
        }

        if ($fieldType === 'password' || preg_match('/\b(password|passcode)\b/i', $semantic)) {
            $hasPasswordField = true;
        }
    }

    if (!$hasEmailField || !$hasPasswordField) {
        return ['ok' => false, 'message' => 'The Login Form must include an email field and a password field.'];
    }

    $submitted = $_POST['fields'] ?? [];

    if (!is_array($submitted)) {
        $submitted = [];
    }

    $email = trim((string)($submitted['email'] ?? ''));
    $password = (string)($submitted['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        return ['ok' => false, 'message' => 'Enter a valid email address and password.'];
    }

    $statement = $pdo->prepare(
        'SELECT user_id, first_name, last_name, email, password, active
         FROM users_data
         WHERE email = ?
         LIMIT 1'
    );

    $statement->execute([$email]);
    $member = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$member || !password_verify($password, (string)$member['password'])) {
        return ['ok' => false, 'message' => 'The email address or password is incorrect.'];
    }

    if (!isActiveMemberRecord($member['active'])) {
        return ['ok' => false, 'message' => 'Your account is not active yet. Please contact support or your administrator.'];
    }

    session_regenerate_id(true);

    $_SESSION['member_id'] = (int)$member['user_id'];
    $_SESSION['member_email'] = (string)$member['email'];
    $_SESSION['member_name'] = trim(
        (string)$member['first_name'] . ' ' . (string)$member['last_name']
    );

    $lastLogin = $pdo->prepare(
        'UPDATE users_data SET last_login = ? WHERE user_id = ?'
    );

    $lastLogin->execute([date('YmdHis'), (int)$member['user_id']]);

    return ['ok' => true, 'message' => 'Login successful.'];
}
