<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/website-settings.php');
}

verifyCsrf();

$fields = [
    'site_name' => 150,
    'tagline' => 255,
    'logo_url' => 500,
    'hero_heading' => 255,
    'hero_description' => 2000,
    'hero_image' => 500,
    'primary_color' => 7,
    'nav_color' => 7,
    'contact_phone' => 50,
    'contact_email' => 255,
];

$values = [];
foreach ($fields as $field => $maxLength) {
    $value = trim((string)($_POST[$field] ?? ''));
    if (strlen($value) > $maxLength) {
        flash('danger', 'One or more fields exceed the allowed length.');
        redirect('/admin/website-settings.php');
    }
    $values[$field] = $value;
}

foreach (['primary_color', 'nav_color'] as $colorField) {
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $values[$colorField])) {
        flash('danger', 'Please select valid website colors.');
        redirect('/admin/website-settings.php');
    }
}

foreach (['logo_url', 'hero_image'] as $imageField) {
    if ($values[$imageField] !== '' && preg_match('/^\s*javascript:/i', $values[$imageField])) {
        flash('danger', 'Invalid image path.');
        redirect('/admin/website-settings.php');
    }
}

if ($values['site_name'] === '' || $values['hero_heading'] === '') {
    flash('danger', 'Website name and homepage heading are required.');
    redirect('/admin/website-settings.php');
}

if (!filter_var($values['contact_email'], FILTER_VALIDATE_EMAIL) && $values['contact_email'] !== '') {
    flash('danger', 'Enter a valid contact email address.');
    redirect('/admin/website-settings.php');
}

try {
    $pdo->beginTransaction();

    $find = $pdo->prepare(
        "SELECT setting_id
         FROM website_design_settings
         WHERE setting_name = ? AND layout_group = 'myjustdial_public_site'
         LIMIT 1
         FOR UPDATE"
    );

    $update = $pdo->prepare(
        "UPDATE website_design_settings
         SET setting_value = ?
         WHERE setting_id = ?"
    );

    $insert = $pdo->prepare(
        "INSERT INTO website_design_settings
            (setting_name, setting_value, layout_group)
         VALUES (?, ?, 'myjustdial_public_site')"
    );

    foreach ($values as $key => $value) {
        $find->execute([$key]);
        $settingId = $find->fetchColumn();

        if ($settingId !== false) {
            $update->execute([$value, $settingId]);
        } else {
            $insert->execute([$key, $value]);
        }
    }

    $pdo->commit();
    flash('success', 'Public website settings saved successfully.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('danger', 'Unable to save website settings. Please check the database table.');
}

redirect('/admin/website-settings.php');
