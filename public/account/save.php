<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/member_forms.php';
require_once __DIR__ . '/../../includes/recaptcha.php';
require_once __DIR__ . '/../../includes/form_destination.php';
require_once __DIR__ . '/../../includes/custom_components.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verifyCsrf();

$isAdminSave = !empty($_SESSION['admin_id']) && isset($_POST['admin_member']);
$memberId = $isAdminSave ? (int)$_POST['admin_member'] : (int)($_SESSION['member_id'] ?? 0);
if ($memberId < 1) {
    http_response_code(403);
    exit('Please sign in to update your member profile.');
}

$section = (string)($_POST['section'] ?? '');
$formName = (string)($_POST['form_name'] ?? '');
$allowedForms = [
    'contact' => 'member_contact_details',
    'profile' => 'member_profile_photo',
    'listing' => 'member_listing_details',
    'about' => 'about'
];
if (!isset($allowedForms[$section]) || $allowedForms[$section] !== $formName) {
    http_response_code(400);
    exit('Invalid member form.');
}
$formName = resolveMemberFormName($pdo, $formName);

$memberQuery = $pdo->prepare('SELECT * FROM users_data WHERE user_id = ? LIMIT 1');
$memberQuery->execute([$memberId]);
$member = $memberQuery->fetch();
if (!$member) {
    http_response_code(404);
    exit('Member not found.');
}
if (!$isAdminSave && !isActiveMemberRecord($member['active'])) {
    http_response_code(403);
    exit('This member account is not active.');
}

$fieldQuery = $pdo->prepare('SELECT * FROM form_fields WHERE form_name = ? ORDER BY field_order, field_id');
$fieldQuery->execute([$formName]);
$configured = $fieldQuery->fetchAll();
$hasSpecialtyField = false;
foreach ($configured as $configuredField) {
    if ((string)$configuredField['field_name'] === 'specialty_categories') $hasSpecialtyField = true;
}
$submitted = $_POST['fields'] ?? [];
if (!is_array($submitted)) $submitted = [];

$columns = [
    'first_name','last_name','email','phone_number','company','position',
    'search_description','address1','address2','city','state_ln','state_code','country_ln','country_code',
    'zip_code','website','blog','facebook','twitter','linkedin','youtube',
    'instagram','pinterest','quote','experience','affiliation','credentials',
    'awards','about_me','working_days','work_experience','cv','listing_type','profession_id'
];
$updates = [];
$metaUpdates = [];
$errors = [];
$captchaError = configuredCaptchaError($pdo, $configured);
if ($captchaError !== '') $errors[] = $captchaError;
$defaultFieldNames = memberDefaultFieldNames($section);

foreach ($configured as $field) {
    $name = (string)$field['field_name'];
    $type = formFieldInternalType((string)$field['field_type']);
    if ((string)($field['field_input_view'] ?? '1') === '0') continue;
    if ($type === 'html' && $name === 'widget_bootstrap_theme_form_agree_terms_conditions' && empty($submitted['terms_accepted'])) $errors[] = 'Accept the terms and conditions to continue.';
    if ($name === '' || in_array($type, ['submit','html','section','captcha','honeypot','help_alert'], true)) continue;
    if ($type === 'file') {
        if ((int)$field['field_required'] && empty($_FILES['uploads']['name'][$name])) $errors[] = 'Choose a file for ' . $name . '.';
        continue;
    }
    $value = $submitted[$name] ?? '';
    if ($name === 'specialty_categories' && in_array($name, $defaultFieldNames, true)) continue;
    if (is_array($value)) $value = implode("\n", array_map('trim', $value));
    $value = trim((string)$value);
    if (in_array($type, ['richtext','richtext_image','richtext_elements','richtext_media'], true)) $value = safeMemberRichText($value);

    if ((int)$field['field_required'] && $value === '') $errors[] = 'Complete the required field: ' . $name;
    if ($name === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($type === 'url' && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) $errors[] = 'Enter a valid website URL.';
    if (in_array($type, ['tel','phone_country'], true) && $value !== '' && !validFormPhone($value)) $errors[] = 'Enter a valid phone number for ' . $name . '.';
    if ($type === 'number' && $value !== '' && !is_numeric($value)) $errors[] = 'Enter a valid number for ' . $name . '.';

    if (in_array($type, ['select','radio','checkbox_group','country','state','top_category','years'], true) && $value !== '') {
        if ($name === 'listing_type' && in_array($name, $defaultFieldNames, true)) {
            $allowedOptions = ['Individual', 'Company'];
        } elseif ($name === 'profession_id' && in_array($name, $defaultFieldNames, true)) {
            $allowedOptions = $pdo->query('SELECT profession_id FROM list_professions')->fetchAll(PDO::FETCH_COLUMN);
            $allowedOptions = array_map('strval', $allowedOptions);
        } else {
            $allowedOptions = preg_split('/\\r\\n|\\r|\\n/', (string)($field['field_options'] ?? ''));
        }
        $submittedOptions = $type === 'checkbox_group' ? preg_split('/\\r\\n|\\r|\\n/', $value) : [$value];
        foreach ($submittedOptions as $submittedOption) {
            if (!in_array($submittedOption, $allowedOptions, true)) $errors[] = 'Choose a valid option for ' . $name . '.';
        }
    }

    $isDefaultField = in_array($name, $defaultFieldNames, true);

    if ($isDefaultField && in_array($name, $columns, true)) {
        if (in_array($name, ['website','blog','facebook','twitter','linkedin','youtube','instagram','pinterest'], true) && $value !== '') {
            $value = rawurlencode($value);
        }
        $updates[$name] = $value;
    } else {
        $metaUpdates[$name] = $value;
    }
}

if (!empty($updates['country_ln']) && tableExists($pdo, 'list_countries')) {
    $countryCode = $pdo->prepare('SELECT country_code FROM list_countries WHERE country_name = ? LIMIT 1');
    $countryCode->execute([$updates['country_ln']]);
    $resolvedCountryCode = $countryCode->fetchColumn();
    $updates['country_code'] = $resolvedCountryCode !== false ? (string)$resolvedCountryCode : '';
}
if (!empty($updates['state_ln']) && tableExists($pdo, 'list_states')) {
    if (!empty($updates['country_code'])) {
        $stateCode = $pdo->prepare('SELECT code FROM list_states WHERE name = ? AND country_id = ? LIMIT 1');
        $stateCode->execute([$updates['state_ln'], $updates['country_code']]);
    } else {
        $stateCode = $pdo->prepare('SELECT code FROM list_states WHERE name = ? LIMIT 1');
        $stateCode->execute([$updates['state_ln']]);
    }
    $resolvedStateCode = $stateCode->fetchColumn();
    $updates['state_code'] = $resolvedStateCode !== false ? (string)$resolvedStateCode : '';
}

if (
    isset($updates['email'])
    && $updates['email'] !== ''
    && strcasecmp(trim((string)$updates['email']), trim((string)($member['email'] ?? ''))) !== 0
) {
    $duplicateEmail = $pdo->prepare(
        'SELECT user_id FROM users_data WHERE LOWER(email) = LOWER(?) AND user_id <> ? LIMIT 1'
    );
    $duplicateEmail->execute([trim($updates['email']), $memberId]);
    if ($duplicateEmail->fetchColumn()) {
        $errors[] = 'That email address is already in use by another member.';
    }
}


// Save values submitted by controls rendered inside configured Custom HTML fields.
$customHtmlFieldIds = [];
foreach ($configured as $configuredField) {
    if (formFieldInternalType((string)$configuredField['field_type']) === 'html'
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

    $metaUpdates['component_' . $componentFieldId . '_' . $valueKey] = $storedValue;
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

$uploadDir = __DIR__ . '/../../uploads/member_files/';
$imageDir = __DIR__ . '/../../images/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) $errors[] = 'Upload directory could not be created.';
if (!is_dir($imageDir) && !mkdir($imageDir, 0755, true) && !is_dir($imageDir)) $errors[] = 'Image directory could not be created.';
$uploadRules = [];
foreach ($configured as $configuredField) {
    if (formFieldInternalType((string)$configuredField['field_type']) !== 'file') continue;
    $fieldName = (string)$configuredField['field_name'];
    if ($fieldName === '') continue;
    $fieldMeta = json_decode((string)($configuredField['json_meta'] ?? '{}'), true);
    if (!is_array($fieldMeta)) $fieldMeta = [];
    $isDefaultUpload = in_array($fieldName, $defaultFieldNames, true);
    if ($isDefaultUpload && ($fieldName === 'profile_photo' || $fieldName === 'profile_logo')) {
        $uploadRules[$fieldName] = ['type' => $fieldName === 'profile_photo' ? 'photo' : 'logo', 'extensions' => ['jpg','jpeg','png','gif','webp'], 'mimes' => ['image/jpeg','image/png','image/gif','image/webp'], 'max' => 5 * 1024 * 1024];
    } elseif ($isDefaultUpload && $fieldName === 'cv') {
        $uploadRules[$fieldName] = ['type' => 'cv', 'extensions' => ['pdf','jpg','jpeg','png','webp'], 'mimes' => ['application/pdf','image/jpeg','image/png','image/webp'], 'max' => 10 * 1024 * 1024];
    } else {
        $uploadRules[$fieldName] = ['type' => 'custom', 'extensions' => ['pdf','jpg','jpeg','png','gif','webp'], 'mimes' => ['application/pdf','image/jpeg','image/png','image/gif','image/webp'], 'max' => 10 * 1024 * 1024];
    }
    $configuredMax = (int)($fieldMeta['maxFileSize'] ?? $fieldMeta['max_file_size'] ?? 0);
    if ($configuredMax > 0) $uploadRules[$fieldName]['max'] = min($configuredMax, 20 * 1024 * 1024);
}
$savedFiles = [];
foreach ($uploadRules as $fieldName => $rules) {
    $isDefaultUpload = in_array($fieldName, $defaultFieldNames, true);
    if (($isDefaultUpload && $fieldName === 'cv' && $section !== 'listing') || ($isDefaultUpload && in_array($fieldName, ['profile_photo','profile_logo'], true) && $section !== 'profile')) continue;
    if (empty($_FILES['uploads']['name'][$fieldName])) continue;
    $file = [
        'name'=>$_FILES['uploads']['name'][$fieldName],
        'tmp_name'=>$_FILES['uploads']['tmp_name'][$fieldName],
        'error'=>$_FILES['uploads']['error'][$fieldName],
        'size'=>$_FILES['uploads']['size'][$fieldName]
    ];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > $rules['max'] || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'The selected file is invalid or exceeds the permitted size.';
        continue;
    }
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($extension, $rules['extensions'], true) || !in_array($mime, $rules['mimes'], true)) {
        $errors[] = 'The uploaded file type is not allowed.';
        continue;
    }
    $filename = bin2hex(random_bytes(18)) . '.' . $extension;
    $destinationDir = in_array($rules['type'], ['photo', 'logo'], true) ? $imageDir : $uploadDir;
    $temporaryName = 'upload-' . bin2hex(random_bytes(12)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $destinationDir . $temporaryName)) {
        $errors[] = 'The file could not be saved.';
        continue;
    }
    $savedFiles[$fieldName] = [
        'path' => '',
        'type' => $rules['type'],
        'local' => $destinationDir . $temporaryName,
        'temporary' => $destinationDir . $temporaryName,
        'extension' => $extension,
        'is_profile_image' => in_array($fieldName, ['profile_photo', 'profile_logo'], true)
    ];
}

if ($errors) {
    foreach ($savedFiles as $saved) if (is_file($saved['local'])) unlink($saved['local']);
    flash('danger', implode(' ', array_unique($errors)));
    redirect('/public/account/' . $section . '.php' . ($isAdminSave ? '?admin_member=' . $memberId : ''));
}

try {
    $pdo->beginTransaction();
    $destination = configuredFormDestination($pdo, $formName, 'users_data', 'user_id');
    $alternateDestination = $destination[0] !== 'users_data';

    foreach ($savedFiles as $fieldName => $saved) {
        if (in_array($fieldName, $defaultFieldNames, true) && $fieldName === 'cv') {
            $cvFilename = bin2hex(random_bytes(18)) . '.' . $saved['extension'];
            $cvFinalPath = $uploadDir . $cvFilename;
            if (!rename($saved['temporary'], $cvFinalPath)) {
                throw new RuntimeException('The CV or brochure could not be stored.');
            }
            $savedFiles[$fieldName]['final'] = $cvFinalPath;
            $savedFiles[$fieldName]['filename'] = $cvFilename;
            $updates['cv'] = rtrim((string)$config['base_url'], '/') . '/uploads/member_files/' . $cvFilename;
            continue;
        }
        if ($saved['type'] === 'custom') {
            $customFilename = bin2hex(random_bytes(18)) . '.' . $saved['extension'];
            $customFinalPath = $uploadDir . $customFilename;
            if (!rename($saved['temporary'], $customFinalPath)) {
                throw new RuntimeException('The uploaded file could not be stored.');
            }
            $savedFiles[$fieldName]['final'] = $customFinalPath;
            $savedFiles[$fieldName]['filename'] = $customFilename;
            $savedFiles[$fieldName]['path'] = rtrim((string)$config['base_url'], '/') . '/uploads/member_files/' . $customFilename;
            $metaUpdates[$fieldName] = $savedFiles[$fieldName]['path'];
            continue;
        }
        $find = $pdo->prepare('SELECT photo_id, file FROM users_photo WHERE user_id = ? AND type = ? ORDER BY photo_id DESC LIMIT 1');
        $find->execute([$memberId, $saved['type']]);
        $existingPhoto = $find->fetch(PDO::FETCH_ASSOC);
        if ($existingPhoto) {
            $photoId = (int)$existingPhoto['photo_id'];
            $oldFile = basename((string)$existingPhoto['file']);
        } else {
            $insertPhoto = $pdo->prepare('INSERT INTO users_photo (user_id, file, original, type, date_added, resized, error, compliant) VALUES (?, ?, ?, ?, ?, ?, ?, 0)');
            $insertPhoto->execute([$memberId, '', '', $saved['type'], date('YmdHis'), '', '']);
            $photoId = (int)$pdo->lastInsertId();
            $oldFile = '';
        }
        $filename = $saved['type'] === 'photo'
            ? 'pimage-' . $photoId . '-' . bin2hex(random_bytes(8)) . '.' . $saved['extension']
            : 'member-logo-' . bin2hex(random_bytes(12)) . '.' . $saved['extension'];
        $finalPath = $imageDir . $filename;
        $backupPath = '';
        if (is_file($finalPath)) {
            $backupPath = $finalPath . '.backup-' . bin2hex(random_bytes(5));
            if (!copy($finalPath, $backupPath)) throw new RuntimeException('Could not preserve the existing image.');
        }
        if (!rename($saved['temporary'], $finalPath)) throw new RuntimeException('Could not move the uploaded image into the Brilliant Directories image directory.');
        $savedFiles[$fieldName]['final'] = $finalPath;
        $savedFiles[$fieldName]['backup'] = $backupPath;
        $savedFiles[$fieldName]['old_file'] = $oldFile;
        $savedFiles[$fieldName]['filename'] = $filename;
        $updatePhoto = $pdo->prepare('UPDATE users_photo SET file = ?, original = ?, date_added = ?, resized = ?, error = ?, compliant = 0 WHERE photo_id = ?');
        $updatePhoto->execute([$filename, '/images/' . $filename, date('YmdHis'), '', '', $photoId]);
    }

    if ($updates && !$alternateDestination) {
        $sets = [];
        $values = [];
        foreach ($updates as $column => $value) {
            if (!in_array($column, $columns, true) && $column !== 'filename') continue;
            $sets[] = "`$column` = ?";
            $values[] = $value;
        }
        if ($sets) {
            $values[] = $memberId;
            $update = $pdo->prepare('UPDATE users_data SET ' . implode(', ', $sets) . ' WHERE user_id = ?');
            $update->execute($values);
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
        $metaUpdates[$fieldName] = trim((string)$fieldValue);
    }
}
if ($alternateDestination) {
    saveConfiguredMemberRecord($pdo, $destination, $memberId, $updates, $metaUpdates);
} else {
    foreach ($metaUpdates as $key => $value) saveMemberMeta($pdo, $memberId, $key, $value);
}
    if ($section === 'listing' && $hasSpecialtyField && tableExists($pdo, 'rel_services')) {
        $serviceIds = $submitted['specialty_categories'] ?? [];
        if (!is_array($serviceIds)) $serviceIds = [];
        $serviceIds = array_values(array_unique(array_filter(array_map('intval', $serviceIds), static fn($id) => $id > 0)));
        if ($serviceIds) {
            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            $validServices = $pdo->prepare("SELECT service_id FROM list_services WHERE service_id IN ($placeholders) AND profession_id = ?");
            $validServices->execute(array_merge($serviceIds, [(int)($updates['profession_id'] ?? $member['profession_id'])]));
            $serviceIds = array_map('intval', $validServices->fetchAll(PDO::FETCH_COLUMN));
        }
        $removeServices = $pdo->prepare('DELETE FROM rel_services WHERE user_id = ? AND specialty = 1');
        $removeServices->execute([$memberId]);
        $insertService = $pdo->prepare('INSERT INTO rel_services (user_id, service_id, date, avg_price, num_completed, specialty) VALUES (?, ?, ?, 0, ?, 1)');
        foreach ($serviceIds as $serviceId) {
            $insertService->execute([$memberId, $serviceId, date('YmdHis'), '0']);
        }
    }

    if (tableExists($pdo, 'user_submitted_forms')) {
        $track = $pdo->prepare(
            'INSERT INTO user_submitted_forms (user_id, form_name, submitted_at)
             VALUES (?, ?, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE submitted_at = CURRENT_TIMESTAMP'
        );
        $track->execute([$memberId, $formName]);
    }
    $pdo->commit();
    foreach ($savedFiles as $saved) {
        if (!empty($saved['backup']) && is_file($saved['backup'])) unlink($saved['backup']);
        if (!empty($saved['old_file']) && !empty($saved['filename']) && $saved['old_file'] !== $saved['filename']) {
            $oldPath = $imageDir . basename($saved['old_file']);
            $references = $pdo->prepare('SELECT COUNT(*) FROM users_photo WHERE file = ?');
            $references->execute([$saved['old_file']]);
            if (!(int)$references->fetchColumn() && is_file($oldPath)) unlink($oldPath);
        }
    }
    flash('success', 'Your ' . $section . ' information has been saved.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($savedFiles as $saved) {
        if (!empty($saved['backup']) && is_file($saved['backup'])) {
            if (!empty($saved['final']) && is_file($saved['final'])) unlink($saved['final']);
            rename($saved['backup'], $saved['final']);
        } elseif (!empty($saved['final']) && is_file($saved['final'])) {
            unlink($saved['final']);
        }
        if (!empty($saved['temporary']) && is_file($saved['temporary'])) unlink($saved['temporary']);
    }
    error_log('Member form save: ' . $exception->getMessage());
    flash('danger', 'Your changes could not be saved. Please try again.');
}
redirect('/public/account/' . $section . '.php' . ($isAdminSave ? '?admin_member=' . $memberId : ''));
