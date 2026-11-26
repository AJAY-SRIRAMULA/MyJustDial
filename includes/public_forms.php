<?php
require_once __DIR__ . '/form_fields.php';
require_once __DIR__ . '/form_destination.php';
require_once __DIR__ . '/recaptcha.php';
require_once __DIR__ . '/custom_components.php';
require_once __DIR__ . '/widgets.php';

function getPublicFormByShortcode(PDO $pdo, string $shortcode): ?array
{
    $shortcode = trim($shortcode);

    if (preg_match('/^\\[form=([a-zA-Z0-9\_-]+)\\]$/', $shortcode, $matches)) {
        $shortcode = $matches[1];
    }

    if (in_array($shortcode, ['newsletter_modal_signup', 'website_newsletter_signup_form'], true)) {
        $shortcode = 'newsletter_modal_signup';
    }

    if ($shortcode === 'find_members') {
        $defs = [['q','What do you need:','text','Name or Keyword'],['location','Search by location:','text','City or Post Code']];
        $fields = [];
        foreach ($defs as $i => $d) $fields[] = ['field_id'=>900000+$i,'field_name'=>$d[0],'field_text'=>$d[1],'field_type'=>$d[2],'field_placeholder'=>$d[3],'field_required'=>false,'default_value'=>'','field_options'=>'','field_sdesc'=>'','field_input_view'=>'1'];
        return ['form_id'=>0,'form_name'=>'find_members','form_table'=>'form_inquiries','form_element_id'=>'','form_class'=>'','form_style'=>'','fields'=>$fields];
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
        if ($shortcode === 'newsletter_modal_signup') $candidates = ['website_newsletter_signup_form', 'newsletter_modal_signup'];
        if ($shortcode === 'bootstrap_get_match') $candidates = ['website_lead_form_get_matched','bootstrap_get_match'];

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
                    WHEN form_name = \'website_newsletter_signup_form\' THEN -2
                    WHEN form_name = \'website_lead_form_get_matched\' THEN -1
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
            $fallbackNames = $shortcode === 'bootstrap_get_match' ? ['website_lead_form_get_matched','bootstrap_get_match'] : [$shortcode];
            if ($shortcode === 'newsletter_modal_signup') $fallbackNames = ['website_newsletter_signup_form', 'newsletter_modal_signup'];
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
    if (in_array($shortcode, ['website_newsletter_signup_form', '[form=website_newsletter_signup_form]', '[form=newsletter_modal_signup]'], true)) {
        $shortcode = 'newsletter_modal_signup';
    }
    $form = getPublicFormByShortcode($pdo, $shortcode);

    if (!$form) {
        return '<div class="contact-form-alert contact-form-alert-error">The Contact Form is not available. Please create or enable the form in Admin → Toolbox → Form Manager.</div>';
    }

    $settings = [];
    if ((int)$form['form_id'] > 0) {
        $settingsQuery = $pdo->prepare("SELECT value FROM users_meta WHERE `database`='forms' AND database_id=? AND `key`='form_settings_json' LIMIT 1");
        $settingsQuery->execute([$form['form_id']]);
        $settings = json_decode((string)$settingsQuery->fetchColumn(), true) ?: [];
    }
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
        if ($shortcode === 'find_members' && in_array($name, ['q', 'location'], true)) {
            require_once __DIR__ . '/directory_search.php';
            $value = directoryText($_GET, $name);
        }
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

         $rating = $shortcode === 'listing_write_review' && $type === 'radio' && str_starts_with($name, 'rating_');
        $html .= '<div class="public-form-field' . ($rating ? ' profile-rating-row' : '') . '"' . (($settings['label_position'] ?? '') === 'left' ? ' style="display:grid;grid-template-columns: minmax(100px, 1fr) 2fr;gap:10px;align-items:center"' : '') . '>';

        if ($type !== 'hidden') {
            $newsletterPlaceholderLabel = $shortcode === 'newsletter_modal_signup' && in_array($name, ['yourname', 'first_name', 'inquiry_email', 'email'], true) && $placeholder !== '' && empty($settings['label_position']);
            $html .= '<label' . ($newsletterPlaceholderLabel ? ' class="newsletter-placeholder-label"' : '') . ' for="' . e($id) . '">' . e($label ?: $name) . $requiredMark . '</label>';
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
                $html .= '<label class="public-form-choice" for="' . e($choiceId) . '"><input id="' . e($choiceId) . '" type="radio" name="' . $inputName . '" value="' . e($option) . '"' . ($value === $option ? ' checked' : '') . $requiredAttribute . '> ' . ($rating ? '<span aria-hidden="true">★</span><span class="rating-accessible">'.e($option).'</span>' : e($option)) . '</label>';
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

    if ($shortcode === 'find_members') {
        require_once __DIR__ . '/directory_search.php';
        $html .= '<label class="directory-category-field">Category' . directoryCategorySelect($pdo, $_GET, 'fields') . '</label>';
    }
    $buttonLabel = match ($shortcode) { 'newsletter_modal_signup' => 'Subscribe Now', 'find_members' => 'Search Now', 'listing_write_review' => 'Submit Review', default => 'Send Message' };
    if ($shortcode === 'newsletter_modal_signup') {
        foreach ($form['fields'] as $field) {
            if (publicFormFieldType($field) === 'submit' && (string)($field['field_input_view'] ?? '1') !== '0' && trim((string)$field['field_text']) !== '') {
                $buttonLabel = trim((string)$field['field_text']);
                break;
            }
        }
    }
    $html .= '<button class="public-form-submit" type="submit">' . e($buttonLabel) . '</button>';
    $html .= '</form>';

    return $html;
}

function processPublicFormSubmission(PDO $pdo, string $shortcode, ?int $profileMemberId = null): array
{
    if (in_array($shortcode, ['website_newsletter_signup_form', '[form=website_newsletter_signup_form]', '[form=newsletter_modal_signup]'], true)) {
        $shortcode = 'newsletter_modal_signup';
    }
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

        if ($isEmpty && $shortcode === 'listing_write_review' && str_starts_with($fieldName, 'rating_')) { continue; }
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

    // Validate only components attached to enabled form widgets.
    foreach ($form['fields'] as $configuredField) {
        if (publicFormFieldType($configuredField) !== 'html' || (string)($configuredField['field_input_view'] ?? '1') === '0') continue;
        $content = (string)$configuredField['field_text'] . ' ' . (string)($configuredField['field_ldesc'] ?? '');
        try {
            foreach (widgetComponentsInContent($pdo, $content) as $component) {
                if (isset($component['validate'])) {
                    $values += ($component['validate'])($pdo, $submitted);
                }
            }
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
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

        [$destinationTable, $destinationKey, $writableColumns] = configuredFormDestination(
            $pdo, (string)$form['form_name'], 'form_inquiries', 'inquiry_id'
        );
        if (preg_match('/^(admin|bd_api|users_meta$|forms$|form_fields$|users_data$|subscription|membership|data_widgets|website_)/i', $destinationTable)) {
            throw new RuntimeException('This destination is not permitted for public inquiries.');
        }
        [$matched, $unmatched] = splitDestinationFields($writableColumns, $destinationKey, $values);
        $aliases = ['inquiry_email' => 'email', 'phone' => 'phone_number', 'enter_a_message' => 'comments'];
        foreach ($aliases as $source => $target) {
            if (array_key_exists($source, $unmatched) && in_array($target, $writableColumns, true) && !array_key_exists($target, $matched)) {
                $matched[$target] = $unmatched[$source];
                unset($unmatched[$source]);
            }
        }
        $systemValues = [
            'inquiry_ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 40),
            'inquiry_user_id' => 0,
            'inquiry_status' => 'New',
            'inquiry_archived' => 0,
            'inquiry_notes' => '',
            'user_consent' => '',
            'inquiry_form' => (string)$form['form_name'],
            'inquiry_content' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'date_submitted' => date('YmdHis'),
            'url_origin' => substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 255),
        ];
        $matched = array_replace($matched, array_intersect_key($systemValues, array_flip($writableColumns)));
        $profileReview = $profileMemberId !== null && $shortcode === 'listing_write_review';
        $profileLead = $profileMemberId !== null && $shortcode === 'bootstrap_get_match';
        if ($profileReview || $profileLead) {
            $check = $pdo->prepare('SELECT user_id FROM users_data WHERE user_id=? AND active=2');
            $check->execute([$profileMemberId]);
            if (!$check->fetchColumn()) throw new RuntimeException('Member unavailable.');
            $trusted = $profileReview ? ['user_id'=>$profileMemberId,'member_id'=>(int)($_SESSION['member_id']??0),'review_status'=>0,'review_added'=>date('YmdHis'),'review_token'=>bin2hex(random_bytes(16)),'ip'=>$_SERVER['REMOTE_ADDR']??'','formname'=>$form['form_name']] : ['token'=>bin2hex(random_bytes(16)),'date_added'=>date('YmdHis'),'status'=>'New','formname'=>$form['form_name'],'origin_ip'=>$_SERVER['REMOTE_ADDR']??''];
            if ($profileReview && $destinationTable === 'users_reviews') {
                // Legacy enum has no unspecified value; require explicit choice rather than invent it.
                if (!isset($matched['gender']) || !in_array($matched['gender'], ['male','female'],true)) throw new RuntimeException('Review gender is required by the legacy schema.');
            }
            unset($unmatched['__profile_member_id']);
            $matched = array_replace($matched,array_intersect_key($trusted,array_flip($writableColumns)));
            if ($profileReview && $destinationTable === 'users_reviews') { foreach ($matched as $k=>$v) if (str_starts_with($k,'rating_') && !in_array((string)$v,['1','2','3','4','5'],true)) throw new RuntimeException('Invalid review rating.'); }
            $unmatched['__profile_member_id'] = (string)$profileMemberId;
        }
        $inquiryId = insertDynamicDestination($pdo, [$destinationTable, $destinationKey, $writableColumns], $matched, null, $profileReview);
        if ($profileLead && $destinationTable === 'leads') {
            $pdo->prepare("INSERT INTO lead_matches (lead_id,user_id,lead_matched,lead_points,lead_match_notes,lead_status,match_price,lead_token,lead_matched_by,lead_viewed,lead_type,lead_distance,lead_rating,lead_chosen,lead_response,lead_accepted,lead_updated) VALUES (?,?,?,0,'',0,0,?,'website','','direct','','',0,'','','')")->execute([$inquiryId,$profileMemberId,date('YmdHis'),bin2hex(random_bytes(16))]);
        }
        $customValues = [];
        foreach ($unmatched as $name => $value) {
            $customValues[] = ['key' => $name, 'value' => $value];
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

        error_log('Public form submission failed [dynamic-v4]: ' . $exception->getMessage());
        return ['ok' => false, 'message' => 'Your message could not be saved. Please try again.'];
    }

    return ['ok' => true, 'message' => $shortcode === 'newsletter_modal_signup' ? 'Thank you! Your newsletter signup has been saved.' : 'Thank you! Your message has been sent successfully.'];
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
