<?php

function formFieldGroups(): array
{
    return [
        'Select Fields' => [
            'Checkboxes' => 'checkbox_group',
            'Dropdown List' => 'select',
            'Multiple Choice Radios' => 'radio',
            'Yes or No' => 'yes_no'
        ],
        'Text Inputs' => [
            'Custom HTML' => 'html',
            'Email Address' => 'email',
            'Section Title' => 'section',
            'Submit Form Button' => 'submit',
            'Text - Single Line' => 'text',
            'Textarea - Paragraph' => 'textarea',
            'Website URL' => 'url'
        ],
        'Fancy Fields' => [
            'Calendar - Date Picker' => 'date',
            'Calendar - Date & Time Picker' => 'datetime-local',
            'File Upload Field' => 'file',
            'Froala Text Editor - Basic' => 'richtext',
            'Froala Text Editor - Image Upload' => 'richtext_image',
            'Froala Text Editor - Image Upload + Pre-Made Elements' => 'richtext_elements',
            'Froala Text Editor - Media Manager Access' => 'richtext_media',
            'Help Alert Box' => 'help_alert',
            'Hidden Field' => 'hidden',
            'Location - Country List' => 'country',
            'Location - State List' => 'state',
            'Number - Integer Only' => 'number',
            'Password Field' => 'password',
            'Phone Number Field' => 'tel',
            'Phone Number + Country Code' => 'phone_country',
            'Price Field' => 'price',
            'Security - Google reCaptcha' => 'captcha',
            'Security - Honeypot Field' => 'honeypot',
            'Top Category List' => 'top_category',
            'Years' => 'years'
        ]
    ];
}

function formFieldStoredNames(): array
{
    return [
        'text' => 'Textbox',
        'email' => 'Email',
        'tel' => 'Phone',
        'textarea' => 'textarea',
        'html' => 'HTML',
        'submit' => 'Button',
        'honeypot' => 'HoneyPot',
        'captcha' => 'ReCaptcha',
        'yes_no' => 'YesNo',
        'password' => 'Password'
    ];
}

function formFieldInternalType(string $stored): string
{
    $key = strtolower(preg_replace('/[\s_-]+/', '', $stored));

    $aliases = [
        'textbox' => 'text',
        'text' => 'text',
        'email' => 'email',
        'emailaddress' => 'email',
        'phone' => 'tel',
        'phonenumber' => 'tel',
        'tel' => 'tel',
        'textarea' => 'textarea',
        'yesno' => 'yes_no',
        'button' => 'submit',
        'submit' => 'submit',
        'submitformbutton' => 'submit',
        'html' => 'html',
        'custom' => 'html',
        'section' => 'section',
        'sectiontitle' => 'section',
        'honeypot' => 'honeypot',
        'recaptcha' => 'captcha',
        'captcha' => 'captcha',
        'select' => 'select',
        'dropdown' => 'select',
        'dropdownlist' => 'select',
        'checkbox' => 'checkbox_group',
        'checkboxes' => 'checkbox_group',
        'checkboxgroup' => 'checkbox_group',
        'radio' => 'radio',
        'multiplechoiceradios' => 'radio',
        'price' => 'price',
        'country' => 'country',
        'countrylist' => 'country',
        'state' => 'state',
        'statelist' => 'state',
        'years' => 'years',
        'topcategory' => 'top_category',
        'date' => 'date',
        'file' => 'file',
        'number' => 'number',
        'integer' => 'number',
        'password' => 'password',
        'url' => 'url',
        'websiteurl' => 'url',
        'datetime' => 'datetime-local',
        'datetimelocal' => 'datetime-local',
        'hidden' => 'hidden',
        'helpalert' => 'help_alert',
        'phonecountry' => 'phone_country',
        'richtext' => 'richtext',
        'richtextimage' => 'richtext_image',
        'richtextelements' => 'richtext_elements',
        'richtextmedia' => 'richtext_media'
    ];

    return $aliases[$key] ?? $stored;
}

function formFieldStoredType(
    string $internal,
    string $originalStored = ''
): string {
    if (
        $originalStored !== ''
        && formFieldInternalType($originalStored) === $internal
    ) {
        return $originalStored;
    }

    $storedNames = formFieldStoredNames();

    return $storedNames[$internal] ?? $internal;
}

function normalizeFieldOptions($value): string
{
    if (is_array($value)) {
        $lines = $value;
    } else {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
    }

    $options = [];

    foreach ($lines as $line) {
        $option = trim((string) $line);

        if ($option !== '') {
            $options[] = $option;
        }
    }

    return implode("\n", $options);
}

function loadBuilderFields(PDO $pdo, string $formName): array
{
    $statement = $pdo->prepare(
        'SELECT *
         FROM form_fields
         WHERE form_name = ?
         ORDER BY field_order, field_id'
    );

    $statement->execute([$formName]);
    $fields = [];

    foreach ($statement->fetchAll() as $row) {
        $meta = json_decode($row['json_meta'] ?: '{}', true);

        if (!is_array($meta)) {
            $meta = [];
        }

        $fields[] = [
            'id' => 'f' . $row['field_id'],
            'type' => formFieldInternalType((string) $row['field_type']),
            'origType' => (string) $row['field_type'],
            'label' => (string) $row['field_text'],
            'name' => (string) $row['field_name'],
            'required' => (bool) $row['field_required'],
            'placeholder' => (string) $row['field_placeholder'],
            'help' => (string) $row['field_sdesc'],
            'description' => (string) $row['field_ldesc'],
            'options' => normalizeFieldOptions($row['field_options']),
            'defaultValue' => (string) $row['default_value'],
            'inputClass' => (string) $row['input_class'],
            'displayClass' => (string) $row['display_class'],
            'outerId' => (string) $row['display_div_id'],
            'section' => (string) $row['form_section'],
            'inputId' => (string) $row['input_div_id'],
            'icon' => (string) $row['field_icon'],
            'meta' => $meta,
            'settings' => [
                'inputView' => $row['field_input_view'] !== '0',
                'displayView' => $row['field_display_view'] !== '0',
                'leadPreviews' => $row['field_search_view'] === '1',
                'includeEmails' => $row['field_email_view'] !== '0',
                'tableView' => $row['field_grid_view'] !== '0',
                'adminOnly' => !empty($meta['adminOnly']),
                'altInput' => (string) ($meta['altInput'] ?? ''),
                'altDisplay' => (string) ($meta['altDisplay'] ?? ''),
                'altEmail' => (string) ($meta['altEmail'] ?? ''),
                'altTable' => (string) ($meta['altTable'] ?? '')
            ]
        ];
    }

    return $fields;
}

function loadBuilderLists(PDO $pdo): array
{
    $lists = [
        'countries' => [],
        'states' => [],
        'categories' => []
    ];

    if (tableExists($pdo, 'list_countries')) {
        $lists['countries'] = $pdo
            ->query(
                'SELECT country_name
                 FROM list_countries
                 WHERE active = 1
                 ORDER BY country_name'
            )
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    if (tableExists($pdo, 'list_states')) {
        $lists['states'] = $pdo
            ->query(
                "SELECT DISTINCT name
                 FROM list_states
                 WHERE name IS NOT NULL
                 AND name <> ''
                 ORDER BY name"
            )
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    if (tableExists($pdo, 'data_categories')) {
        $lists['categories'] = $pdo
            ->query(
                "SELECT data_name
                 FROM data_categories
                 WHERE data_active = 1
                 AND data_name <> ''
                 ORDER BY display_order, data_name"
            )
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    return $lists;
}

function validFormPhone(string $value): bool
{
    $value = trim($value);
    if (!preg_match('/^\+?[0-9().\s-]+$/u', $value)) return false;
    $digits = preg_replace('/[^0-9]/', '', $value);
    return strlen($digits) >= 5 && strlen($digits) <= 15;
}
