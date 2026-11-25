<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk') {
    verifyCsrf();

    $bulkAction = (string)($_POST['bulk_action'] ?? '');
    $submittedIds = $_POST['ids'] ?? [];

    if (!is_array($submittedIds)) {
        $submittedIds = [];
    }

    $selectedIds = array_values(array_unique(array_filter(
        array_map('intval', $submittedIds),
        static function ($id) {
            return $id > 0;
        }
    )));

    if (!$selectedIds || !in_array($bulkAction, ['enable', 'disable', 'delete'], true)) {
        flash('warning', 'Select forms and a valid bulk action.');
        redirect('/admin/toolbox/form-manager/index.php');
    }

    try {
        $pdo->beginTransaction();

        foreach ($selectedIds as $selectedId) {
            $formQuery = $pdo->prepare(
                "SELECT form_name
                 FROM forms
                 WHERE form_id = ? AND form_action_type <> 'default'"
            );
            $formQuery->execute([$selectedId]);
            $formName = $formQuery->fetchColumn();

            if ($formName === false) {
                continue;
            }

            if ($bulkAction === 'delete') {
                $pdo->prepare('DELETE FROM form_fields WHERE form_name = ?')
                    ->execute([$formName]);

                $pdo->prepare('DELETE FROM forms WHERE form_id = ?')
                    ->execute([$selectedId]);

                if (tableExists($pdo, 'users_meta')) {
                    $pdo->prepare(
                        "DELETE FROM users_meta
                         WHERE `database` = 'forms' AND database_id = ?"
                    )->execute([$selectedId]);
                }

                continue;
            }

            if (tableExists($pdo, 'users_meta')) {
                $status = $bulkAction === 'enable' ? 'enabled' : 'disabled';

                $pdo->prepare(
                    "DELETE FROM users_meta
                     WHERE `database` = 'forms'
                     AND database_id = ?
                     AND `key` = 'form_status'"
                )->execute([$selectedId]);

                $pdo->prepare(
                    "INSERT INTO users_meta
                        (`database`, database_id, `key`, `value`, date_added)
                     VALUES ('forms', ?, 'form_status', ?, ?)"
                )->execute([$selectedId, $status, date('YmdHis')]);
            }
        }

        $pdo->commit();
        flash('success', 'The selected forms were updated.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        flash('danger', 'Unable to apply the bulk action: ' . $exception->getMessage());
    }

    redirect('/admin/toolbox/form-manager/index.php');
}

$token = $_GET['token'] ?? '';

if (!hash_equals(csrfToken(), $token)) {
    http_response_code(419);
    exit('Invalid action token. Return to Form Manager and try again.');
}

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($action === 'customize') {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM forms
         WHERE form_id = ? AND form_action_type = 'default'"
    );
} else {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM forms
         WHERE form_id = ? AND form_action_type <> 'default'"
    );
}

$stmt->execute([$id]);
$form = $stmt->fetch();

if (!$form) {
    flash('warning', 'The selected custom form was not found.');
    redirect('/admin/toolbox/form-manager/index.php');
}

if ($action === 'delete') {
    $pdo->beginTransaction();

    $pdo->prepare('DELETE FROM form_fields WHERE form_name = ?')
        ->execute([$form['form_name']]);

    $pdo->prepare('DELETE FROM forms WHERE form_id = ?')
        ->execute([$id]);

    if (tableExists($pdo, 'users_meta')) {
        $pdo->prepare(
            "DELETE FROM users_meta
             WHERE `database` = 'forms' AND database_id = ?"
        )->execute([$id]);
    }

    $pdo->commit();
    flash('success', 'Form and its fields were deleted.');
} elseif ($action === 'toggle') {
    $meta = $pdo->prepare(
        "SELECT value
         FROM users_meta
         WHERE `database` = 'forms'
         AND database_id = ?
         AND `key` = 'form_status'
         LIMIT 1"
    );
    $meta->execute([$id]);

    $next = $meta->fetchColumn() === 'disabled' ? 'enabled' : 'disabled';

    $pdo->prepare(
        "DELETE FROM users_meta
         WHERE `database` = 'forms'
         AND database_id = ?
         AND `key` = 'form_status'"
    )->execute([$id]);

    $pdo->prepare(
        "INSERT INTO users_meta
            (`database`, database_id, `key`, `value`, date_added)
         VALUES ('forms', ?, 'form_status', ?, ?)"
    )->execute([$id, $next, date('YmdHis')]);

    flash('success', 'Form status changed to ' . $next . '.');
} elseif ($action === 'clone' || $action === 'customize') {
    $pdo->beginTransaction();

    $newName = $form['form_name'] . ($action === 'customize' ? '_custom' : '_copy');
    $suffix = 2;
    $check = $pdo->prepare('SELECT COUNT(*) FROM forms WHERE form_name = ?');

    while (true) {
        $check->execute([$newName]);

        if (!(int)$check->fetchColumn()) {
            break;
        }

        $newName = $form['form_name'] . ($action === 'customize' ? '_custom_' : '_copy_') . $suffix++;
    }

    $publicAlias = '';

    if ($action === 'customize') {
        $sourceName = strtolower((string)$form['form_name']);
        $sourceTitle = strtolower((string)$form['form_title']);

        if (
            preg_match('/^(?:contact_form|nxt_wave_form)(?:_custom(?:_\d+)?)?$/', $sourceName)
        ) {
            $publicAlias = 'contact_form';
        } elseif (
            str_contains($sourceName, 'login')
            || str_contains($sourceTitle, 'login')
        ) {
            $publicAlias = 'member_login';
        } elseif (
            str_contains($sourceName, 'signup_free')
            || str_contains($sourceTitle, 'membership')
            || str_contains($sourceTitle, 'signup')
        ) {
            $publicAlias = 'signup_free';
        }
    }

    $memberFormCanonical = '';
    if (tableExists($pdo, 'users_meta')) {
        $memberFormQuery = $pdo->prepare(
            "SELECT value
             FROM users_meta
             WHERE `database` = 'forms'
               AND database_id = ?
               AND `key` = 'member_form_seeded'
             ORDER BY meta_id DESC
             LIMIT 1"
        );
        $memberFormQuery->execute([$id]);
        $memberFormCanonical = (string)($memberFormQuery->fetchColumn() ?: '');
    }

    if ($memberFormCanonical === '' && in_array((string)$form['form_name'], [
        'member_contact_details',
        'member_profile_photo',
        'member_listing_details',
        'about'
    ], true)) {
        $memberFormCanonical = (string)$form['form_name'];
    }

    if ($memberFormCanonical === 'member_contact_details') {
        $publicAlias = '';
    }

    $customShortcode = $publicAlias !== ''
        ? '[form=' . $publicAlias . ']'
        : '[form=' . $newName . ']';

    $insert = $pdo->prepare(
        'INSERT INTO forms (
            form_name, form_desc, form_url, form_target, form_action,
            form_database, form_table, table_index, form_email_recipient,
            form_title, form_email_on, email_template, email_admin, email_from,
            email_template_admin, form_class, form_style, form_element_id,
            short_code, form_layout, trigger_data_flow, data_flow_name,
            form_action_type, form_action_div
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $customTitle = preg_replace('/\s*\(Custom\)$/i', '', (string)$form['form_title']);
    $insert->execute([
        $newName,
        $form['form_desc'],
        $form['form_url'],
        $form['form_target'],
        $form['form_action'],
        $form['form_database'],
        $form['form_table'],
        $form['table_index'],
        $form['form_email_recipient'],
        ($action === 'customize' ? $customTitle : $form['form_title'] . ' (Copy)'),
        (int)$form['form_email_on'],
        $form['email_template'],
        (int)$form['email_admin'],
        $form['email_from'],
        $form['email_template_admin'],
        $form['form_class'],
        $form['form_style'],
        $form['form_element_id'],
        $customShortcode,
        $form['form_layout'],
        $form['trigger_data_flow'],
        $form['data_flow_name'],
        'widget',
        $form['form_action_div'],
    ]);
    $newFormId = (int)$pdo->lastInsertId();

    if ($action === 'customize' && $memberFormCanonical !== '' && tableExists($pdo, 'users_meta')) {
        $pdo->prepare(
            "INSERT INTO users_meta
                (`database`, database_id, `key`, `value`, date_added)
             VALUES ('forms', ?, 'member_form_seeded', ?, ?)"
        )->execute([$newFormId, $memberFormCanonical, date('YmdHis')]);
    }

    if ($action === 'customize' && tableExists($pdo, 'users_meta')) {
        $pdo->prepare(
            "INSERT INTO users_meta
                (`database`, database_id, `key`, `value`, date_added)
             VALUES ('forms', ?, 'form_nickname', ?, ?)"
        )->execute([$newFormId, $customTitle, date('YmdHis')]);
    }

    $fields = $pdo->prepare(
        'SELECT *
         FROM form_fields
         WHERE form_name = ?
         ORDER BY field_order, field_id'
    );
    $fields->execute([$form['form_name']]);

    $copy = $pdo->prepare(
        'INSERT INTO form_fields (
            form_name, field_text, field_name, field_type, field_order,
            field_sdesc, field_ldesc, field_placeholder, field_required,
            field_options, input_class, display_class, default_value,
            field_display_view, field_input_view, field_grid_view,
            field_email_view, form_section, field_search_view, display_div_id,
            input_div_id, field_icon, json_meta
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($fields as $field) {
        $copy->execute([
            $newName,
            $field['field_text'],
            $field['field_name'],
            $field['field_type'],
            $field['field_order'],
            $field['field_sdesc'],
            $field['field_ldesc'],
            $field['field_placeholder'],
            $field['field_required'],
            $field['field_options'],
            $field['input_class'],
            $field['display_class'],
            $field['default_value'],
            $field['field_display_view'],
            $field['field_input_view'],
            $field['field_grid_view'],
            $field['field_email_view'],
            $field['form_section'],
            $field['field_search_view'],
            $field['display_div_id'],
            $field['input_div_id'],
            $field['field_icon'],
            $field['json_meta'],
        ]);
    }

    if ($action === 'customize' && tableExists($pdo, 'users_meta')) {
        if ($publicAlias !== '') {
            $metaKey = 'public_shortcode_' . $publicAlias;

            $pdo->prepare(
                "DELETE FROM users_meta
                 WHERE `database` = 'forms'
                 AND `key` = ?"
            )->execute([$metaKey]);

            $pdo->prepare(
                "INSERT INTO users_meta
                    (`database`, database_id, `key`, `value`, date_added)
                 VALUES ('forms', ?, ?, ?, ?)"
            )->execute([
                $newFormId,
                $metaKey,
                $publicAlias,
                date('YmdHis'),
            ]);
        }
    }

    $pdo->commit();
    flash('success', $action === 'customize' ? 'A custom copy was created and connected to its public form. You can now edit its fields.' : 'Form cloned successfully.');
    if ($action === 'customize') {
        redirect('/admin/toolbox/form-manager/edit.php?id=' . $newFormId);
    }
} else {
    flash('warning', 'Unknown form action.');
}

redirect('/admin/toolbox/form-manager/index.php');
