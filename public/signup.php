<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public_website.php';
require __DIR__ . '/../includes/public_forms.php';
require_once __DIR__ . '/../includes/form_destination.php';
require_once __DIR__ . '/../includes/razorpay_test.php';

$settings = getPublicWebsiteSettings($pdo);
$baseUrl = $config['base_url'];

$planNames = [
    1 => 'Free',
    2 => 'Standard',
    3 => 'Premium',
];

$planId = filter_input(INPUT_GET, 'plan_id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planId = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT);
}

if (!tableExists($pdo, 'subscription_types')) {
    redirect('/public/membership.php');
}

$planStatement = $pdo->prepare(
    "SELECT subscription_id, subscription_name, monthly_amount, yearly_amount
     FROM subscription_types
     WHERE subscription_id = ?
       AND subscription_type = 'member'
       AND sub_active = 1
     LIMIT 1"
);
$planStatement->execute([$planId ?: 0]);
$selectedPlan = $planStatement->fetch(PDO::FETCH_ASSOC);

if (!$selectedPlan) {
    redirect('/public/membership.php');
}

$selectedPlanName = $planNames[(int)$selectedPlan['subscription_id']]
    ?? preg_replace('/^Membership Plan\s+\d+$/i', 'Membership', trim($selectedPlan['subscription_name']));

$isFreeSignup = (int)$selectedPlan['subscription_id'] === 1;
$signupShortcode = $isFreeSignup ? 'signup_free' : 'signup_paid';
$signupForm = getPublicFormByShortcode($pdo, $signupShortcode);
$signupFields = $signupForm['fields'] ?? [];
$hasTermsWidget = false;
$hasConsentWidget = false;
$hasReferralWidget = false;
$customFields = [];

foreach ($signupFields as $field) {
    $fieldName = trim((string)($field['field_name'] ?? ''));
    $fieldType = strtolower(trim((string)($field['field_type'] ?? '')));

    if ($fieldType === 'custom' && ($field['field_input_view'] ?? '1') !== '0') {
        $hasTermsWidget = $hasTermsWidget || $fieldName === 'widget_bootstrap_theme_form_agree_terms_conditions';
        $hasConsentWidget = $hasConsentWidget || $fieldName === 'user_consent_gdpr';
        $hasReferralWidget = $hasReferralWidget || $fieldName === 'referral_input';
    }

    if (in_array(formFieldInternalType($fieldType), ['captcha','honeypot','section','help_alert','submit','html'], true)) continue;
    if ($fieldName === '' || ($field['field_input_view'] ?? '1') === '0') {
        continue;
    }

    if (in_array($fieldName, [
        'email', 'email_confirm', 'password', 'password_confirm',
        'widget_default_fields', 'widget_bootstrap_theme_form_agree_terms_conditions',
        'user_consent_gdpr', 'referral_input', 'savebutton'
    ], true)) {
        continue;
    }

    if (in_array($fieldType, ['custom', 'recaptcha', 'honeypot', 'button', 'html'], true)) {
        continue;
    }

    if (!$isFreeSignup && ($fieldName === 'billing_cycle' || in_array(formFieldInternalType($fieldType), ['section','help_alert','submit'], true))) continue;
    $customFields[] = $field;
}

function signupSafeHtml(string $html): string
{
    $allowedTags = '<a><b><blockquote><br><code><div><em><h1><h2><h3><h4><h5><h6><hr><i><li><ol><p><pre><s><small><span><strong><u><ul>';
    $html = strip_tags($html, $allowedTags);

    return preg_replace_callback('/<([a-z0-9]+)([^>]*)>/i', static function ($matches) {
        $tag = strtolower($matches[1]);
        $attributes = '';

        if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/i', $matches[2], $class)) {
            $attributes .= ' class="' . e($class[2]) . '"';
        }

        if (preg_match('/\bstyle\s*=\s*(["\'])(.*?)\1/i', $matches[2], $style)) {
            $safe = [];
            foreach (explode(';', $style[2]) as $declaration) {
                $parts = explode(':', $declaration, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $property = strtolower(trim($parts[0]));
                $value = trim($parts[1]);
                if (in_array($property, ['color', 'background-color', 'font-size', 'font-weight', 'font-style', 'text-align', 'text-decoration', 'border', 'border-color', 'border-width', 'border-style', 'border-radius', 'padding', 'margin', 'display'], true) && $value !== '' && !preg_match('/[<>\\{}]|url|expression|javascript|@import|behavior/i', $value)) {
                    $safe[] = $property . ': ' . $value;
                }
            }
            if ($safe) {
                $attributes .= ' style="' . e(implode('; ', $safe)) . '"';
            }
        }

        if ($tag === 'a' && preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $matches[2], $href)) {
            $url = trim(html_entity_decode($href[2], ENT_QUOTES, 'UTF-8'));
            if (preg_match('/^(https?:|mailto:|tel:|\/|#)/i', $url) && strpos($url, '//') !== 0) {
                $attributes .= ' href="' . e($url) . '"';
            }
            if (preg_match('/\btarget\s*=\s*(["\'])(.*?)\1/i', $matches[2], $target) && $target[2] === '_blank') {
                $attributes .= ' target="_blank" rel="noopener noreferrer"';
            }
        }

        return '<' . $tag . $attributes . '>';
    }, $html);
}

function signupFieldLabel(array $field): string
{
    $label = trim(strip_tags((string)($field['field_text'] ?? '')));
    $tokens = [
        'enter_email_placeholder' => 'Enter Email',
        'confirm_email' => 'Confirm Email',
        'create_pw_label' => 'Create Password',
        'confirm_pw_label' => 'Confirm Password',
        'create_profile_label' => 'Create My Profile',
    ];

    if (preg_match('/^%%%(.+?)%%%$/', $label, $matches)) {
        return $tokens[$matches[1]] ?? ucwords(str_replace('_', ' ', $matches[1]));
    }

    return $label;
}

function renderSignupField(array $field): string
{
    $name = trim((string)($field['field_name'] ?? ''));
    if ($name === 'billing_cycle' && !$GLOBALS['isFreeSignup']) {
        $plan = $GLOBALS['selectedPlan'];
        $html = '<div class="account-field"><label for="billing-cycle">' . e(signupFieldLabel($field)) . ' (Razorpay test mode)</label><select name="billing_cycle" id="billing-cycle" required>';
        foreach (['monthly', 'yearly'] as $cycle) {
            if ((float)$plan[$cycle . '_amount'] <= 0) continue;
            $html .= '<option value="' . $cycle . '"' . (strtolower((string)($_POST['billing_cycle'] ?? $field['default_value'] ?? 'monthly')) === $cycle ? ' selected' : '') . '>' . ucfirst($cycle) . ' — ₹' . e(number_format((float)$plan[$cycle . '_amount'], 2)) . '</option>';
        }
        return $html . '</select></div>';
    }
    $type = formFieldInternalType((string)($field['field_type'] ?? 'text'));
    $label = signupFieldLabel($field);
    $required = !empty($field['field_required']);
    $placeholder = (string)($field['field_placeholder'] ?? '');
    $value = (string)($field['default_value'] ?? '');
    $isAccountField = in_array($name, ['email', 'email_confirm', 'password', 'password_confirm'], true);
    $id = $isAccountField ? $name : 'signup-field-' . (int)($field['field_id'] ?? 0);
    $inputName = $isAccountField ? e($name) : 'custom_fields[' . e($name) . ']';
    $requiredAttribute = $required ? ' required' : '';
    $requiredMark = $required ? '<span class="required-mark">*</span>' : '';
    $help = trim((string)($field['field_sdesc'] ?? ''));
    $inputClass = trim((string)($field['input_class'] ?? '')) ?: 'form-control';
    $options = publicFormFieldOptions($field);
    $html = '';

    if ($type === 'html') {
        require_once __DIR__ . '/../includes/widgets.php';
        $widgetContent = $label . ' ' . (string)($field['field_ldesc'] ?? '');
        if (preg_match('/\[widget=[a-zA-Z0-9_.-]+\]/', $widgetContent)) {
            return renderWidgetShortcodes($GLOBALS['pdo'], $widgetContent);
        }
        if (isTrustedComponentReference($label)) {
            return renderTrustedPhpComponent($label, [
                'pdo' => $GLOBALS['pdo'],
                'member' => null,
                'form_id' => 0,
                'field_id' => (int)($field['field_id'] ?? 0),
                'user_id' => 0,
                'context' => 'public-signup',
                'preview' => true,
                'csrf' => csrfToken(),
                'base_url' => $GLOBALS['config']['base_url']
            ]);
        }
        return '<div class="account-custom-html">' . signupSafeHtml((string)($field['field_ldesc'] ?? '')) . '</div>';
    }
    if ($type === 'section') {
        return '<h3 class="account-form-section">' . e($label) . '</h3>';
    }
    if ($type === 'help_alert') {
        return '<div class="account-form-help">' . e((string)($field['field_ldesc'] ?: $field['field_sdesc'] ?: $label)) . '</div>';
    }
    if ($type === 'hidden') {
        return '<input type="hidden" name="' . $inputName . '" value="' . e($value) . '">';
    }

    $html .= '<div class="account-field">';
    if ($type !== 'hidden') {
        $html .= '<label for="' . e($id) . '">' . $requiredMark . e($label ?: $name) . '</label>';
    }

    if ($type === 'textarea' || str_starts_with($type, 'richtext')) {
        $html .= '<textarea class="' . e($inputClass) . '" id="' . e($id) . '" name="' . $inputName . '" placeholder="' . e($placeholder) . '"' . $requiredAttribute . '>' . e($value) . '</textarea>';
    } elseif (in_array($type, ['select', 'country', 'state', 'top_category', 'years'], true)) {
        if (!$options) {
            $lists = loadBuilderLists($GLOBALS['pdo']);
            $options = $type === 'country' ? $lists['countries'] : ($type === 'state' ? $lists['states'] : ($type === 'top_category' ? $lists['categories'] : []));
            if ($type === 'years') {
                $options = array_map(static fn($offset) => (string)((int)date('Y') - $offset), range(0, 29));
            }
        }
        $html .= '<select class="' . e($inputClass) . '" id="' . e($id) . '" name="' . $inputName . '"' . $requiredAttribute . '><option value="">' . e($placeholder ?: 'Select ' . ($label ?: 'an option')) . '</option>';
        foreach ($options as $option) {
            $html .= '<option value="' . e($option) . '"' . ($value === $option ? ' selected' : '') . '>' . e($option) . '</option>';
        }
        $html .= '</select>';
    } elseif (in_array($type, ['radio', 'checkbox_group', 'yes_no'], true)) {
        $choices = $type === 'yes_no' ? ['Yes', 'No'] : $options;
        $html .= '<div class="account-choice-group">';
        foreach ($choices as $index => $option) {
            $choiceId = $id . '-' . $index;
            $choiceType = $type === 'checkbox_group' ? 'checkbox' : 'radio';
            $choiceName = $type === 'checkbox_group' ? $inputName . '[]' : $inputName;
            $html .= '<label for="' . e($choiceId) . '"><input id="' . e($choiceId) . '" type="' . $choiceType . '" name="' . $choiceName . '" value="' . e($option) . '"' . ($value === $option ? ' checked' : '') . ($required ? ' required' : '') . '> ' . e($option) . '</label>';
        }
        $html .= '</div>';
    } else {
        $inputTypes = ['text', 'email', 'tel', 'number', 'date', 'datetime-local', 'url', 'password', 'file', 'price', 'phone_country'];
        $inputType = in_array($type, $inputTypes, true) ? ($type === 'price' ? 'number' : ($type === 'phone_country' ? 'tel' : $type)) : 'text';
        if ($inputType === 'password') {
            $html .= '<div class="account-password-wrap">';
            $html .= '<input class="' . e($inputClass) . '" id="' . e($id) . '" name="' . $inputName . '" type="password" placeholder="' . e($placeholder) . '"' . $requiredAttribute . ' autocomplete="new-password">';
            $html .= '<button class="password-visibility-toggle" type="button" data-password-toggle="' . e($id) . '" aria-label="Show password" aria-pressed="false">';
            $html .= '<svg class="password-eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            $html .= '</button></div>';
        } else {
            $html .= '<input class="' . e($inputClass) . '" id="' . e($id) . '" name="' . $inputName . '" type="' . e($inputType) . '" placeholder="' . e($placeholder) . '"' . ($inputType === 'file' ? '' : ' value="' . e($value) . '"') . $requiredAttribute . '>';
        }
    }

    if ($help !== '') {
        $html .= '<small>' . e($help) . '</small>';
    }
    if (!empty($field['field_ldesc']) && $type !== 'html') {
        $html .= '<small>' . e(strip_tags((string)$field['field_ldesc'])) . '</small>';
    }
    return $html . '</div>';
}

function renderFreeSignupForm(array $fields, string $baseUrl): string
{
    $html = '';
    $renderedAccountFields = [];
    $submitLabel = 'Create Account';

    foreach ($fields as $field) {
        $name = trim((string)($field['field_name'] ?? ''));
        $storedType = strtolower(trim((string)($field['field_type'] ?? '')));
        $type = formFieldInternalType((string)($field['field_type'] ?? ''));
        $content = trim((string)($field['field_text'] ?? ''));

        if (($field['field_input_view'] ?? '1') === '0' && $type !== 'html') {
            continue;
        }

        if ($storedType === 'custom') {
            if ($name === 'widget_default_fields') {
                continue;
            }
            if ($name === 'widget_bootstrap_theme_form_agree_terms_conditions') {
                $html .= '<div class="account-terms account-widget-terms"><label><input type="checkbox" name="terms_accepted" value="1" required> By creating an account, you agree to our <a href="' . e($baseUrl) . '/public/index.php#terms" target="_blank" rel="noopener">terms &amp; conditions</a>.</label></div>';
                continue;
            }
            if ($name === 'user_consent_gdpr') {
                $html .= '<div class="account-consent-widget"><label><input type="checkbox" name="user_consent_gdpr" value="1"> I agree to the processing of my personal information in accordance with the <a href="' . e($baseUrl) . '/public/index.php#privacy" target="_blank" rel="noopener">Privacy Policy</a>.</label></div>';
                continue;
            }
            if ($name === 'referral_input') {
                $html .= '<div class="account-field account-referral-widget"><label for="signup-referral-code">Referral Code</label><input id="signup-referral-code" name="referral_code" type="text" value="' . e($_POST['referral_code'] ?? '') . '" placeholder="Enter referral code (optional)"></div>';
                continue;
            }
            if (preg_match('/^\[widget=([^\]]+)\]$/', $content)) {
                $html .= renderWidgetShortcodes($GLOBALS['pdo'], $content);
                continue;
            }
            if ($content !== '' && str_contains($content, '<')) {
                $html .= signupSafeHtml($content);
            }
            continue;
        }

        if ($type === 'submit') {
            $submitLabel = $content !== '' ? signupFieldLabel($field) : 'Create Account';
            continue;
        }
        if ($type === 'honeypot') {
            $html .= '<div class="public-form-honeypot" aria-hidden="true"><input type="hidden" name="extra_honeypot_' . (int)($field['field_id'] ?? 0) . '" tabindex="-1" autocomplete="off"></div>';
            continue;
        }
        if ($type === 'captcha') {
            $html .= renderConfiguredCaptcha($GLOBALS['pdo']);
            continue;
        }
        if ($type === 'html' || $type === 'section' || $type === 'help_alert' || $type === 'hidden') {
            $html .= renderSignupField($field);
            continue;
        }
        if ($name === '') {
            continue;
        }
        if (in_array($name, ['email', 'email_confirm', 'password', 'password_confirm'], true)) {
            $renderedAccountFields[] = $name;
        }
        $html .= renderSignupField($field);
    }

    foreach (['email', 'email_confirm', 'password', 'password_confirm'] as $requiredName) {
        if (!in_array($requiredName, $renderedAccountFields, true)) {
            throw new RuntimeException('The signup form is missing the required ' . $requiredName . ' field.');
        }
    }

    $html .= '<button class="account-submit" type="submit">' . e($submitLabel) . '</button>';
    return '<div class="account-fields-grid">' . $html . '</div>';
}

$errors = [];
$success = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $captchaError = configuredCaptchaError($pdo, $signupFields);
    if ($captchaError !== '') $errors[] = $captchaError;

    $email = trim((string)($_POST['email'] ?? ''));
    $confirmEmail = trim((string)($_POST['email_confirm'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['password_confirm'] ?? '');
    $referralCode = $hasReferralWidget ? trim((string)($_POST['referral_code'] ?? '')) : '';
    $consentValue = $hasConsentWidget && isset($_POST['user_consent_gdpr']) ? '1' : '';

    if ($hasTermsWidget && empty($_POST['terms_accepted'])) {
        $errors[] = 'You must agree to the terms and conditions to create an account.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if ($email !== $confirmEmail) {
        $errors[] = 'Email addresses do not match.';
    }

    if ($password === '') {
        $errors[] = 'Create a password.';
    }

    if ($confirmPassword === '') {
        $errors[] = 'Confirm your password.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    $hasHoneypotValue = trim((string)($_POST['website_address'] ?? '')) !== '';

    foreach ($_POST as $postName => $postValue) {
        if (str_starts_with((string)$postName, 'extra_honeypot_') && trim((string)$postValue) !== '') {
            $hasHoneypotValue = true;
            break;
        }
    }

    if ($hasHoneypotValue) {
        $errors[] = 'The signup submission could not be verified.';
    }

    $emailCheck = $pdo->prepare('SELECT user_id FROM users_data WHERE email = ? LIMIT 1');
    $emailCheck->execute([$email]);

    if ($emailCheck->fetchColumn()) {
        $errors[] = 'An account with this email address already exists.';
    }

    $customValues = [];

    foreach ($customFields as $field) {
        $fieldName = (string)$field['field_name'];
        $fieldType = formFieldInternalType((string)$field['field_type']);
        $allowedOptions = publicFormFieldOptions($field);
        $submittedValue = $_POST['custom_fields'][$fieldName] ?? '';

        if ($fieldType === 'file') {
            $upload = $_FILES['custom_fields'] ?? null;
            $uploadError = $upload['error'][$fieldName] ?? UPLOAD_ERR_NO_FILE;
            $fieldValue = '';

            if ($uploadError === UPLOAD_ERR_OK) {
                $originalName = (string)($upload['name'][$fieldName] ?? '');
                $temporaryPath = (string)($upload['tmp_name'][$fieldName] ?? '');
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

                $fileSize = (int)($upload['size'][$fieldName] ?? 0);
                $mimeType = $temporaryPath !== '' && is_file($temporaryPath) ? mime_content_type($temporaryPath) : '';
                $allowedMimeTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];

                if (!in_array($extension, $allowedExtensions, true) || !in_array($mimeType, $allowedMimeTypes, true) || $fileSize > 5242880 || !is_uploaded_file($temporaryPath)) {
                    $errors[] = 'Upload a supported file for ' . signupFieldLabel($field) . '.';
                    continue;
                }

                $uploadDirectory = __DIR__ . '/uploads/signup';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                    $errors[] = 'The file upload could not be saved. Please try again.';
                    continue;
                }

                $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                if (!move_uploaded_file($temporaryPath, $uploadDirectory . '/' . $storedName)) {
                    $errors[] = 'The file upload could not be saved. Please try again.';
                    continue;
                }
                $fieldValue = 'uploads/signup/' . $storedName;
            } elseif ($uploadError !== UPLOAD_ERR_NO_FILE) {
                $errors[] = 'The file upload failed for ' . signupFieldLabel($field) . '.';
                continue;
            }
        } elseif (is_array($submittedValue)) {
            $submittedValue = array_values(array_filter(array_map('trim', $submittedValue)));
            if ($allowedOptions && array_diff($submittedValue, $allowedOptions)) {
                $errors[] = 'Select valid options for ' . signupFieldLabel($field) . '.';
                continue;
            }
            $fieldValue = implode(', ', $submittedValue);
        } elseif ($fieldType !== 'file') {
            $fieldValue = trim((string)$submittedValue);
            if (in_array($fieldType, ['select', 'radio', 'yes_no'], true) && $fieldValue !== '' && !in_array($fieldValue, $fieldType === 'yes_no' ? ['Yes', 'No'] : $allowedOptions, true)) {
                $errors[] = 'Select a valid option for ' . signupFieldLabel($field) . '.';
                continue;
            }
        }

        if ((int)$field['field_required'] === 1 && $fieldValue === '') {
            $errors[] = signupFieldLabel($field) . ' is required.';
            continue;
        }

        if ($fieldValue !== '' && $fieldType === 'email' && !filter_var($fieldValue, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address for ' . signupFieldLabel($field) . '.';
            continue;
        }

        if ($fieldValue !== '' && $fieldType === 'number' && !is_numeric($fieldValue)) {
            $errors[] = 'Enter a valid number for ' . signupFieldLabel($field) . '.';
            continue;
        }

        if ($fieldValue !== '' && in_array($fieldType, ['tel', 'phone_country'], true) && !validFormPhone($fieldValue)) {
            $errors[] = 'Enter a valid phone number for ' . signupFieldLabel($field) . '.';
            continue;
        }

        if ($fieldValue !== '') {
            $customValues[$fieldName] = $fieldValue;
        }
    }

    $widgetSubmittedFields = $_POST['widget_fields'] ?? [];
    if (is_array($widgetSubmittedFields)) {
        foreach ($widgetSubmittedFields as $fieldName => $fieldValue) {
            $fieldName = trim((string)$fieldName);
            if ($fieldName === '' || !preg_match('/^[a-zA-Z0-9_\-\[\].]{1,190}$/', $fieldName)) continue;
            if (is_array($fieldValue)) {
                $fieldValue = json_encode(array_values($fieldValue), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                if ($fieldValue === false) continue;
            } elseif (!is_scalar($fieldValue) && $fieldValue !== null) continue;
            $customValues[$fieldName] = trim((string)$fieldValue);
        }
    }

    $userDataColumnMap = [
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'company' => 'company',
        'phone' => 'phone_number',
        'phone_number' => 'phone_number',
        'address1' => 'address1',
        'address2' => 'address2',
        'city' => 'city',
        'zip_code' => 'zip_code',
        'state_code' => 'state_code',
        'state_ln' => 'state_ln',
        'country_code' => 'country_code',
        'country_ln' => 'country_ln',
        'website' => 'website',
        'twitter' => 'twitter',
        'youtube' => 'youtube',
        'facebook' => 'facebook',
        'linkedin' => 'linkedin',
        'blog' => 'blog',
        'quote' => 'quote',
        'experience' => 'experience',
        'affiliation' => 'affiliation',
        'awards' => 'awards',
        'about_me' => 'about_me',
        'position' => 'position',
        'instagram' => 'instagram',
        'pinterest' => 'pinterest',
        'cv' => 'cv',
        'work_experience' => 'work_experience',
        'rep_matters' => 'rep_matters',
        'gmap' => 'gmap',
        'search_description' => 'search_description',
        'ref_code' => 'ref_code',
        'user_consent' => 'user_consent',
    ];
    $userDataValues = [];
    $metaValues = [];

    foreach ($customValues as $fieldName => $fieldValue) {
        if (isset($userDataColumnMap[$fieldName])) {
            $userDataValues[$userDataColumnMap[$fieldName]] = $fieldValue;
        } else {
            $metaValues[$fieldName] = $fieldValue;
        }
    }

    if (!$errors) {
        try {
            $verifiedPayment = $isFreeSignup ? null : razorpayVerifySignup($selectedPlan, $email);
            $pdo->beginTransaction();

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(24));
            $cookie = bin2hex(random_bytes(24));
            $signupDate = (int)date('YmdHis');

            $insertUser = $pdo->prepare(
                "INSERT INTO users_data (
                    first_name, last_name, email, company, phone_number,
                    address1, address2, city, zip_code, state_code, state_ln,
                    country_code, country_ln, website, twitter, youtube, facebook,
                    linkedin, blog, quote, experience, affiliation, awards, about_me,
                    featured, subscription_id, filename, password, active, token,
                    ref_code, signup_date, cookie, last_login, position, instagram,
                    credentials, bitly, profession_id, facebook_id, google_id, verified,
                    pinterest, nationwide, cv, work_experience, rep_matters, gmap,
                    parent_id, no_geo, user_consent, search_description
                ) VALUES (
                    '', '', ?, '', '', '', '', '', '', '', '',
                    'IN', 'India', NULL, NULL, NULL, NULL, NULL, '', NULL, NULL,
                    NULL, NULL, NULL, 0, ?, '', ?, 2, ?,
                    ?, ?, ?, '', '', '', '', '0', 0, '', '', 0, '', 0, '', '', '',
                    '', 0, ?, '', ''
                )"
            );
            $insertUser->execute([
                $email,
                (int)$selectedPlan['subscription_id'],
                $passwordHash,
                $token,
                $referralCode !== '' ? $referralCode : 'Website Signup',
                $signupDate,
                $cookie,
                $consentValue,
            ]);

            $newUserId = (int)$pdo->lastInsertId();

            if ($newUserId <= 0) {
                throw new RuntimeException('Member account creation did not return a user ID.');
            }

            if ($userDataValues) {
                $updateParts = [];
                $updateValues = [];
                foreach ($userDataValues as $column => $fieldValue) {
                    $updateParts[] = '`' . $column . '` = ?';
                    $updateValues[] = $fieldValue;
                }
                $updateValues[] = $newUserId;
                $updateUser = $pdo->prepare(
                    'UPDATE users_data SET ' . implode(', ', $updateParts) . ' WHERE user_id = ?'
                );
                $updateUser->execute($updateValues);
            }

            $destination = configuredFormDestination($pdo, (string)($signupForm['form_name'] ?? $signupShortcode), 'users_data', 'user_id');
            if ($metaValues && !tableExists($pdo, 'users_meta')) {
                throw new RuntimeException('The users_meta table is unavailable.');
            }

            if ($metaValues && $destination[0] === 'users_data') {
                $saveMeta = $pdo->prepare(
                    "INSERT INTO users_meta (`database`, database_id, `key`, value, date_added)
                     VALUES ('users_data', ?, ?, ?, ?)"
                );

                foreach ($metaValues as $fieldName => $fieldValue) {
                    $saveMeta->execute([
                        $newUserId,
                        $fieldName,
                        $fieldValue,
                        (string)$signupDate,
                    ]);
                }
            }

            if ($destination[0] !== 'users_data') {
                if ($destination[1] === 'user_id') {
                    saveConfiguredMemberRecord($pdo, $destination, $newUserId, array_replace($customValues, $userDataValues, ['email' => $email, 'user_consent' => $consentValue]), $metaValues);
                } else {
                    saveIndependentMemberDestination($pdo, $destination, $newUserId, array_replace($customValues, ['email' => $email, 'user_consent' => $consentValue]));
                }
            }
            if ($verifiedPayment) {
                $paymentInsert = $pdo->prepare('INSERT INTO membership_test_payments (payment_id, order_id, user_id, subscription_id, amount_paise, billing_cycle) VALUES (?, ?, ?, ?, ?, ?)');
                $paymentInsert->execute([$verifiedPayment['payment'], $verifiedPayment['order'], $newUserId, (int)$selectedPlan['subscription_id'], $verifiedPayment['amount'], $verifiedPayment['cycle']]);
            }
            $pdo->commit();
            if ($verifiedPayment) unset($_SESSION['razorpay_signup_orders'][$verifiedPayment['order']]);
            $success = true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Signup destination [dynamic-v4]: ' . $exception->getMessage());
            $errors[] = 'Your account could not be created. Please try again.';
        }
    }
}

$logoUrl = $settings['logo_url'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | <?= e($settings['site_name']) ?></title>
    <?php
    $websiteCssPath = __DIR__ . '/assets/css/website.css';
    $websiteCssVersion = is_file($websiteCssPath) ? filemtime($websiteCssPath) : time();
    ?>
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/public/assets/css/website.css?v=<?= (int)$websiteCssVersion ?>">
<link rel="icon" type="image/svg+xml" href="<?=e($config['base_url'])?>/assets/images/favicons/public-favicon.svg">
</head>
<body class="account-create-page">
    <header class="account-brand-header">
        <div class="site-container account-brand-inner">
            <a class="account-brand" href="<?= e($baseUrl) ?>/public/index.php">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= e($logoUrl) ?>" alt="<?= e($settings['site_name']) ?>">
                <?php else: ?>
                    <span class="account-brand-mark">
                        <svg viewBox="0 0 48 58" aria-hidden="true">
                            <circle cx="24" cy="17" r="11"></circle>
                            <path d="M8 53V42c0-10 7-17 16-17s16 7 16 17v11H8Z"></path>
                        </svg>
                    </span>
                <?php endif; ?>
                <span class="account-brand-copy">
                    <strong>MyJustDial Directory</strong>
                    <small>The All-in-One Business Search Directory Theme</small>
                </span>
            </a>
        </div>
    </header>

    <main class="account-create-main">
        <div class="account-create-heading">
            <span>Create Account</span>
            <h1><?= e($selectedPlanName) ?></h1>
        </div>

        <section class="account-create-card">
            <?php if (!$isFreeSignup): ?>
                <h2>Enter Email and Create Password</h2>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="account-message error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="account-message success" role="status">
                    <?= $isFreeSignup ? 'Your profile has been created.' : 'Test payment verified. Your paid profile has been created.' ?>
                </div>
            <?php else: ?>
                <form id="membership-signup-form" method="post" enctype="multipart/form-data" action="<?= e($baseUrl) ?>/public/signup.php?plan_id=<?= (int)$selectedPlan['subscription_id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="plan_id" value="<?= (int)$selectedPlan['subscription_id'] ?>">

                    <?php if (!$isFreeSignup): ?>
                        <?php if (!in_array('billing_cycle', array_column($signupFields, 'field_name'), true)): ?>
                            <?= renderSignupField(['field_name'=>'billing_cycle','field_text'=>'Billing Cycle','default_value'=>'monthly']) ?>
                        <?php endif; ?>
                        <?php foreach (['razorpay_order_id','razorpay_payment_id','razorpay_signature'] as $paymentField): ?>
                            <input type="hidden" name="<?= e($paymentField) ?>" value="<?= e($_POST[$paymentField] ?? '') ?>">
                        <?php endforeach; ?>
                        <p id="payment-status" role="status"></p>
                    <?php endif; ?>
                    <?php if ($signupForm): ?>
                        <?= renderFreeSignupForm($signupFields, $baseUrl) ?>
                    <?php else: ?>
                        <div class="account-fields-grid">
                            <div class="account-field">
                                <label for="email"><span><span class="required-mark">*</span>Enter Email</span></label>
                                <input id="email" name="email" type="email" value="<?= e($email) ?>" required autocomplete="email">
                            </div>
                            <div class="account-field">
                                <label for="email_confirm"><span><span class="required-mark">*</span>Confirm Email</span></label>
                                <input id="email_confirm" name="email_confirm" type="email" required autocomplete="email">
                            </div>
                            <div class="account-field">
                                <label for="password"><span><span class="required-mark">*</span>Create Password</span></label>
                                <div class="account-password-wrap">
                                    <input id="password" name="password" type="password" required autocomplete="new-password" aria-describedby="password-requirements">
                                    <button class="password-visibility-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">
                                        <svg class="password-eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="account-field">
                                <label for="password_confirm"><span><span class="required-mark">*</span>Confirm Password</span></label>
                                <div class="account-password-wrap">
                                    <input id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password">
                                    <button class="password-visibility-toggle" type="button" data-password-toggle="password_confirm" aria-label="Show password" aria-pressed="false">
                                        <svg class="password-eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p class="account-terms">
                            By creating an account, you agree to our
                            <a href="<?= e($baseUrl) ?>/public/index.php#terms">terms &amp; conditions</a>
                        </p>
                    <?php endif; ?>

                    <?php if (!$signupForm && !$isFreeSignup): ?>
                        <button class="account-submit" type="submit">Create My Profile</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <script>
        (function () {
            'use strict';

            var passwordInput = document.getElementById('password');
            var confirmInput = document.getElementById('password_confirm');
            var signupForm = document.getElementById('membership-signup-form');

            document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
                toggle.addEventListener('click', function () {
                    var input = document.getElementById(toggle.getAttribute('data-password-toggle'));

                    if (!input) {
                        return;
                    }

                    var showPassword = input.type === 'password';
                    input.type = showPassword ? 'text' : 'password';
                    toggle.setAttribute('aria-pressed', String(showPassword));
                    toggle.setAttribute(
                        'aria-label',
                        showPassword ? 'Hide password' : 'Show password'
                    );
                    toggle.innerHTML = showPassword
                        ? '<svg class="password-eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a16 16 0 0 1-3.1 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4-.8"></path></svg>'
                        : '<svg class="password-eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
                });
            });

            if (signupForm) {
                signupForm.addEventListener('submit', function (event) {
                    if (passwordInput && confirmInput && passwordInput.value !== confirmInput.value) {
                        event.preventDefault();
                        confirmInput.setCustomValidity('Passwords do not match.');
                        confirmInput.reportValidity();
                    } else if (confirmInput) {
                        confirmInput.setCustomValidity('');
                    }
                });

                if (confirmInput) {
                    confirmInput.addEventListener('input', function () {
                        confirmInput.setCustomValidity('');
                    });
                }
            }
        }());
    </script>
    <?php if (!$isFreeSignup && !$success): ?>
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script src="<?= e($baseUrl) ?>/assets/js/razorpay-signup.js"></script>
    <?php endif; ?>
</body>
</html>
