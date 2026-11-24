<?php

require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/toolbox/form-manager/index.php');
}

verifyCsrf();

$id = (int) ($_POST['form_id'] ?? 0);

$q = $pdo->prepare("SELECT * FROM forms WHERE form_id = ?");
$q->execute([$id]);
$f = $q->fetch(PDO::FETCH_ASSOC);

if (!$f) {
    flash('warning', 'Form not found.');
    redirect('/admin/toolbox/form-manager/index.php');
}

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$table = (string) ($_POST['table'] ?? '');

if (
    $table &&
    (
        !in_array($table, $tables, true) ||
        !preg_match('/^[A-Za-z0-9_]+$/', $table)
    )
) {
    flash('danger', 'The selected destination table is invalid.');
    redirect('/admin/toolbox/form-manager/settings.php?id=' . $id);
}

$columns = [];

if ($table) {
    $q = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
    ");
    $q->execute([$table]);
    $columns = $q->fetchAll(PDO::FETCH_COLUMN);
}

$pk = (string) ($_POST['primary_key'] ?? '');

$primaryColumns = [];

if ($table) {
    $q = $pdo->prepare('
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_KEY = "PRI"
    ');
    $q->execute([$table]);
    $primaryColumns = $q->fetchAll(PDO::FETCH_COLUMN);
}

if ($pk && !in_array($pk, $primaryColumns, true)) {
    flash('danger', 'The selected key is not a primary key for this table.');
    redirect('/admin/toolbox/form-manager/settings.php?id=' . $id);
}

$s = [
    'after_submit' => ($_POST['after_submit'] ?? 'message') === 'redirect' ? 'redirect' : 'message',
    'success_message' => trim((string) ($_POST['success_message'] ?? '')),
    'redirect_url' => trim((string) ($_POST['redirect_url'] ?? '')),
    'target_selector' => trim((string) ($_POST['target_selector'] ?? '#main-content')),
    'email_user' => !empty($_POST['email_user']) ? 1 : 0,
    'email_template' => trim((string) ($_POST['email_template'] ?? '')),
    'email_admin' => !empty($_POST['email_admin']) ? 1 : 0,
    'admin_email' => trim((string) ($_POST['admin_email'] ?? '')),
    'form_element_id' => trim((string) ($_POST['form_element_id'] ?? '')),
    'form_class' => trim((string) ($_POST['form_class'] ?? '')),
    'form_style' => trim((string) ($_POST['form_style'] ?? '')),
    'form_url' => trim((string) ($_POST['form_url'] ?? '')),
    'form_action_div' => trim((string) ($_POST['form_action_div'] ?? '')),
    'table' => $table,
    'primary_key' => $pk
];

try {
    $pdo->beginTransaction();

    $q = $pdo->prepare("
        UPDATE forms
        SET form_table = ?,
            table_index = ?,
            form_element_id = ?,
            form_class = ?,
            form_style = ?,
            form_url = ?,
            form_action_div = ?,
            form_email_on = ?,
            email_template = ?,
            email_admin = ?,
            form_email_recipient = ?
        WHERE form_id = ?
    ");

    $q->execute([
        $table,
        $pk,
        $s['form_element_id'],
        $s['form_class'],
        $s['form_style'],
        $s['form_url'],
        $s['form_action_div'],
        $s['email_user'],
        $s['email_template'],
        $s['email_admin'],
        $s['admin_email'],
        $id
    ]);

    $pdo->prepare("
        DELETE FROM users_meta
        WHERE `database` = 'forms'
            AND database_id = ?
            AND `key` = 'form_settings_json'
    ")->execute([$id]);

    $pdo->prepare("
        INSERT INTO users_meta (`database`, database_id, `key`, `value`, date_added)
        VALUES ('forms', ?, 'form_settings_json', ?, ?)
    ")->execute([
        $id,
        json_encode($s),
        date('YmdHis')
    ]);

    $pdo->commit();

    flash('success', 'Settings saved.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash('danger', 'Unable to save settings.');
}

redirect('/admin/toolbox/form-manager/settings.php?id=' . $id);