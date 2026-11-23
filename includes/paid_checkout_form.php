<?php

function ensurePaidCheckoutForm(PDO $pdo): void
{
    if (!tableExists($pdo, 'forms') || !tableExists($pdo, 'form_fields')) {
        return;
    }

    $existing = $pdo->prepare('SELECT form_id FROM forms WHERE form_name = ? LIMIT 1');
    $existing->execute(['signup_paid']);
    if ($existing->fetchColumn()) {
        return;
    }

    $fields = [
        ['Account Information', 'account_section', 'section', 0, '', ''],
        ['First Name', 'first_name', 'text', 1, '', 'Enter first name'],
        ['Last Name', 'last_name', 'text', 1, '', 'Enter last name'],
        ['Email Address', 'email', 'email', 1, '', 'Enter email'],
        ['Confirm Email', 'email_confirm', 'email', 1, '', 'Confirm email'],
        ['Phone Number', 'phone_number', 'tel', 1, '', 'Enter phone number'],
        ['Create Password', 'password', 'password', 1, '', 'Create password'],
        ['Confirm Password', 'password_confirm', 'password', 1, '', 'Confirm password'],
        ['Membership & Billing', 'billing_section', 'section', 0, '', ''],
        ['Billing Cycle', 'billing_cycle', 'select', 1, "Monthly\nYearly", 'Choose billing cycle'],
        ['Company / Business Name', 'company', 'text', 0, '', 'Business name (optional)'],
        ['Billing Address', 'address1', 'text', 1, '', 'Street address'],
        ['Billing City', 'city', 'text', 1, '', 'City'],
        ['Billing State', 'state_ln', 'text', 1, '', 'State'],
        ['Billing Country', 'country_ln', 'text', 1, '', 'Country'],
        ['Billing Postal Code', 'zip_code', 'text', 1, '', 'Postal code'],
        ['GST Number', 'gst_number', 'text', 0, '', 'GST number (optional)'],
        ['[widget=Bootstrap Theme - Form - Referral Input]', 'referral_input', 'Custom', 0, '', ''],
        ['[widget=Bootstrap Theme - Form - Agree Terms Conditions]', 'widget_bootstrap_theme_form_agree_terms_conditions', 'Custom', 1, '', ''],
        ['Bot Protection', '', 'HoneyPot', 0, '', ''],
        ['Create Paid Membership Account', 'savebutton', 'Button', 0, '', ''],
    ];

    $pdo->beginTransaction();
    try {
        $create = $pdo->prepare(
            "INSERT INTO forms (
                form_name, form_desc, form_url, form_target, form_action,
                form_database, form_table, table_index, form_email_recipient,
                form_title, form_email_on, email_template, email_admin,
                email_from, email_template_admin, form_class, form_style,
                form_element_id, short_code, form_layout, trigger_data_flow,
                data_flow_name, form_action_type, form_action_div
            ) VALUES (
                ?, ?, '', '', 'post', '', 'users_data', 'user_id', '',
                ?, 0, '', 0, '', '', 'form-control', '', '', ?,
                'bootstrapvertical', 'No', '', 'default', ''
            )"
        );
        $create->execute([
            'signup_paid',
            'Default paid membership registration form. Payment must be confirmed separately.',
            'Member - Sign Up - Paid',
            '[form=signup_paid]'
        ]);

        $insert = $pdo->prepare(
            'INSERT INTO form_fields (
                form_name, field_text, field_name, field_type, field_order,
                field_sdesc, field_ldesc, field_placeholder, field_required,
                field_options, input_class, display_class, default_value,
                field_display_view, field_input_view, field_grid_view,
                field_email_view, form_section, field_search_view,
                display_div_id, input_div_id, field_icon, json_meta
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($fields as $index => $field) {
            [$label, $name, $type, $required, $options, $placeholder] = $field;
            $insert->execute([
                'signup_paid', $label, $name, $type, $index + 1,
                '', '', $placeholder, $required, $options,
                'form-control', '', $name === 'billing_cycle' ? 'Yearly' : '',
                '1', '1', '1', '1', '', '0', '', '', '', '{}'
            ]);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
