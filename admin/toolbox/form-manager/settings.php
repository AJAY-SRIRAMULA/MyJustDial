<?php

require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$q = $pdo->prepare("SELECT * FROM forms WHERE form_id = ?");
$q->execute([$id]);
$form = $q->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    flash('warning', 'Form not found or protected.');
    redirect('/admin/toolbox/form-manager/index.php');
}

$s = [];
$q = $pdo->prepare("
    SELECT value
    FROM users_meta
    WHERE `database` = 'forms'
        AND database_id = ?
        AND `key` = 'form_settings_json'
    ORDER BY meta_id DESC
    LIMIT 1
");
$q->execute([$id]);
$s = json_decode((string) $q->fetchColumn(), true) ?: [];

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$tables = array_filter(
    $tables,
    fn($t) => preg_match('/^[A-Za-z0-9_]+$/', (string) $t)
);

$formName = (string) ($form['form_name'] ?? '');

$initialDestinations = [
    'member_contact_details' => 'users_data',
    'member_contact_details_custom' => 'users_data',
    'profile_photo' => 'users_photo',
    'profile_photo_custom' => 'users_photo',
    'listing_details' => 'users_data',
    'listing_details_custom' => 'users_data',
    'about_me' => 'users_data',
    'about_me_custom' => 'users_data',
    'signup_free' => 'users_data',
    'signup_free_custom' => 'users_data',
    'signup_paid' => 'users_data',
    'signup_paid_custom' => 'users_data'
];

$table = (string) ($s['table'] ?? '');

if ($table === '') {
    $table = (string) ($form['form_table'] ?? '');
}

if (!isset($s['table']) && isset($initialDestinations[$formName])) {
    $table = $initialDestinations[$formName];
}

if (!isset($s['table']) && str_ends_with($formName, '_custom')) {
    $parentName = substr($formName, 0, -7);

    if (
        isset($initialDestinations[$parentName]) &&
        empty($form['form_table'])
    ) {
        $table = $initialDestinations[$parentName];
    }
}

if (
    $table === '' &&
    in_array(
        $formName,
        ['contact_form', 'contact_form_custom', 'nxt_wave_form_custom'],
        true
    )
) {
    $table = 'form_inquiries';
}

$cols = [];

if ($table && in_array($table, $tables, true)) {
    $q = $pdo->prepare("
        SELECT COLUMN_NAME, COLUMN_KEY
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
    ");
    $q->execute([$table]);
    $cols = $q->fetchAll(PDO::FETCH_ASSOC);
}

$pk = $s['primary_key'] ?? $form['table_index'];

foreach ($cols as $c) {
    if (!$pk && $c['COLUMN_KEY'] === 'PRI') {
        $pk = $c['COLUMN_NAME'];
        break;
    }
}

$templateValues = [];

try {
    $templateQuery = $pdo->query("
        SELECT DISTINCT email_template
        FROM forms
        WHERE email_template IS NOT NULL
            AND email_template <> ''

        UNION

        SELECT DISTINCT email_template_admin
        FROM forms
        WHERE email_template_admin IS NOT NULL
            AND email_template_admin <> ''
    ");

    $templateValues = $templateQuery->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
}

if (
    ($s['email_template'] ?? $form['email_template']) !== '' &&
    !in_array(
        ($s['email_template'] ?? $form['email_template']),
        $templateValues,
        true
    )
) {
    $templateValues[] = $s['email_template'] ?? $form['email_template'];
}

$pageTitle = 'Edit Form Settings';
$activeMenu = 'forms';

$extraHead = '<link rel="stylesheet" href="' . e($config['base_url']) . '/assets/css/form-settings.css">';

require __DIR__ . '/../../../includes/header.php';
?>

<div class="mjd-form-settings-page" role="dialog" aria-modal="true" aria-label="Edit Form Editor">
    <a class="settings-close" href="edit.php?id=<?=$id?>">× Close</a>
    <div class="settings-dialog">
        <div class="settings-header">
            <h2>Edit Form Editor</h2>

            <button class="settings-top-save" type="submit" form="mjd-settings-form">Save Changes</button>
        </div>

        <form
            id="mjd-settings-form" method="post"
            action="settings-save.php"
            class="settings-form"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?=e(csrfToken())?>"
            >

            <input
                type="hidden"
                name="form_id"
                value="<?=$id?>"
            >

            <div class="settings-scroll"><div class="mb-3">
                <label>Form Name</label>

                <input
                    class="form-control" name="form_title" required
                    value="<?=e($form['form_title'])?>"
                >
            </div>

            <div class="settings-tabs">
                <button
                    type="button"
                    data-tab="submission"
                    class="btn btn-outline-primary"
                >
                    Submission Settings
                </button>

                <button
                    type="button"
                    data-tab="email"
                    class="btn btn-outline-primary"
                >
                    Email Notifications
                </button>

                <button
                    type="button"
                    data-tab="developer"
                    class="btn btn-outline-primary"
                >
                    Developer Settings
                </button>
            </div>

            <div data-panel="submission">
                <div class="row">
                    <div class="col-md-6">
                        <label>What Happens After Form Submitted</label>

                        <select class="form-control" name="after_submit">
                            <option value="message">
                                Display Success Pop-Up Message
                            </option>

                            <option
                                value="redirect"
                                <?=($s['after_submit'] ?? '') === 'redirect' ? 'selected' : ''?>
                            >
                                Redirect to URL
                            </option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label>Success Message Text</label>

                        <input
                            class="form-control"
                            name="success_message"
                            value="<?=e($s['success_message'] ?? 'Your Message Has Been Sent')?>"
                        >
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <label>Redirect URL</label>

                        <input
                            class="form-control"
                            name="redirect_url"
                            value="<?=e($s['redirect_url'] ?? '')?>"
                        >
                    </div>

                    <div class="col-md-6">
                        <label>Target Selector</label>

                        <input
                            class="form-control"
                            name="target_selector"
                            value="<?=e($s['target_selector'] ?? '#main-content')?>"
                        >
                    </div>
                </div>
            </div>

            <div data-panel="email" hidden>
                <h4>User Notifications</h4>

                <div class="row">
                    <div class="col-md-6">
                        <label>Email User After Submission</label>

                        <select class="form-control" name="email_user">
                            <option value="0">Off</option>

                            <option
                                value="1"
                                <?=!empty($s['email_user'] ?? $form['form_email_on']) ? 'selected' : ''?>
                            >
                                On
                            </option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label>Email Template to Send</label>

                        <select class="form-control" name="email_template">
                            <?php foreach ($templateValues as $template): ?>
                                <option
                                    value="<?=e($template)?>"
                                    <?=($s['email_template'] ?? $form['email_template']) === $template ? 'selected' : ''?>
                                >
                                    <?=e($template)?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <h4 class="mt-4">Admin Notifications</h4>

                <div class="row">
                    <div class="col-md-6">
                        <label>Email Admin After Submission</label>

                        <select class="form-control" name="email_admin">
                            <option value="0">Off</option>

                            <option
                                value="1"
                                <?=!empty($s['email_admin'] ?? $form['email_admin']) ? 'selected' : ''?>
                            >
                                On
                            </option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label>Email Template to Send</label>
                        <select class="form-control" name="email_template_admin"><option value="">Select template</option><?php foreach (array_unique(array_merge($templateValues, [(string)($s['email_template_admin'] ?? $form['email_template_admin'] ?? '')])) as $template): if ($template === '') continue; ?><option value="<?=e($template)?>" <?=($s['email_template_admin'] ?? $form['email_template_admin']) === $template ? 'selected' : ''?>><?=e($template)?></option><?php endforeach ?></select>
                    </div></div><div class="row mt-3"><div class="col-md-6"><label>Enter Admin Email Addresses to Notify</label>

                        <input
                            class="form-control"
                            name="admin_email"
                            value="<?=e($s['admin_email'] ?? $form['form_email_recipient'])?>"
                        >
                    </div>
                </div>
            </div>

            <div data-panel="developer" hidden>
                <div class="row"><div class="col-md-6"><label>Form System Name</label><input class="form-control" readonly value="<?=e($form['form_name'])?>"><small class="settings-note">Existing system name is protected to preserve shortcode and field mappings.</small></div></div>
                <h4>Input Field Settings</h4>

                <div class="row">
                    <div class="col-md-6">
                        <label>Field Label Position</label>

                        <select class="form-control" name="label_position">
                            <option value="above">Labels Above Inputs</option>
                            <option value="left" <?=($s['label_position'] ?? '') === 'left' ? 'selected' : ''?>>Labels Beside Inputs</option>
                        </select>

                        <small class="settings-note">
                            Labels appear above each form input.
                        </small>
                    </div>

                    <div class="col-md-6">
                        <label>Use Field Label as Placeholder Text?</label>

                        <label class="settings-switch"><input type="checkbox" name="label_placeholder" value="1" <?=!empty($s['label_placeholder']) ? 'checked' : ''?>><span></span></label>

                        <small class="settings-note">
                            Uses each field label inside the input as placeholder text.
                        </small>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <label>&lt;input&gt; Field CSS Classes <span class="settings-optional">(optional)</span></label>

                        <input
                            class="form-control"
                            name="input_class" value="<?=e($s['input_class'] ?? 'form-control')?>"
                        >

                        <small class="settings-note">
                            Applies CSS classes to every input. Enter class names only.

                            <strong class="settings-warning">
                                DO NOT ENTER class=
                            </strong>
                        </small>
                    </div>
                </div>

                <h4 class="mt-4">Form Settings</h4>

                <div class="row">
                    <div class="col-md-6">
                        <label>&lt;form&gt; Assign Unique ID <span class="settings-optional">(optional)</span></label>

                        <input
                            class="form-control"
                            name="form_element_id"
                            value="<?=e($s['form_element_id'] ?? $form['form_element_id'])?>"
                        >

                        <small class="settings-note">
                            Enter an ID only;

                            <strong class="settings-warning">
                                DO NOT ENTER id=
                            </strong>.
                        </small>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <label>&lt;form&gt; CSS Classes <span class="settings-optional">(optional)</span></label>

                        <input
                            class="form-control"
                            name="form_class"
                            value="<?=e($s['form_class'] ?? $form['form_class'])?>"
                        >

                        <small class="settings-note">
                            Applies classes to the form container. Enter class names only.

                            <strong class="settings-warning">
                                DO NOT ENTER class=
                            </strong>
                        </small>
                    </div>

                    <div class="col-md-6">
                        <label>&lt;form&gt; Inline Styles <span class="settings-optional">(optional)</span></label>

                        <input
                            class="form-control"
                            name="form_style"
                            value="<?=e($s['form_style'] ?? $form['form_style'])?>"
                        >

                        <small class="settings-note">
                            Enter CSS declarations, e.g. color: red; font-size: 20px;.

                            <strong class="settings-warning">
                                DO NOT ENTER style=
                            </strong>
                        </small>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <label>
                            Save Action URL
                            <span class="settings-optional">(advanced)</span>
                        </label>

                        <input
                            class="form-control"
                            name="form_url"
                            value="<?=e($s['form_url'] ?? $form['form_url'])?>"
                        >

                        <small class="settings-note">
                            Advanced option: overrides the form submission action URL.
                        </small>
                    </div>

                    <div class="col-md-6">
                        <label>ID to Replace After Form Submitted</label>

                        <input
                            class="form-control"
                            name="form_action_div"
                            value="<?=e($s['form_action_div'] ?? $form['form_action_div'])?>"
                        >

                        <small class="settings-note">
                            Enter target ID that will be replaced when form is submitted.<strong class="settings-warning">Only works when Display Success Pop-Up Message is enabled. Example: #myform</strong>
                        </small>
                    </div>
                </div>

                <h4 class="mt-4">Database Settings</h4>

                <div class="row">
                    <div class="col-md-6">
                        <label>
                            What Database Table Should Submissions Post To?
                        </label>

                        <select
                            class="form-control"
                            id="settings-table"
                            name="table"
                        >
                            <?php foreach ($tables as $t): ?>
                                <option
                                    value="<?=e($t)?>"
                                    <?=$table === $t ? 'selected' : ''?>
                                >
                                    <?=e($t)?>
                                </option>
                            <?php endforeach ?>
                        </select><small class="settings-note"><strong class="settings-warning">ADVANCED SETTING:</strong> Only modify if familiar with database tables.</small>
                    </div>

                    <div class="col-md-6">
                        <label>Primary Key</label>

                        <select
                            class="form-control"
                            id="settings-primary-key"
                            name="primary_key"
                        >
                            <?php foreach ($cols as $c): if ($c['COLUMN_KEY'] !== 'PRI') continue; ?>
                                <option
                                    value="<?=e($c['COLUMN_NAME'])?>"
                                    <?=$pk === $c['COLUMN_NAME'] ? 'selected' : ''?>
                                >
                                    <?=e($c['COLUMN_NAME'])?>
                                    <?=$c['COLUMN_KEY'] === 'PRI' ? '(Primary)' : ''?>
                                </option>
                            <?php endforeach ?>
                        </select><small class="settings-note">Unique field identifier for this database table</small>
                    </div>

                    <div
                        id="pk-status"
                        class="small text-muted mt-2"
                        aria-live="polite"
                    ></div>
                </div>

                </div>
            <div class="settings-footer"><button class="btn btn-success" type="submit">
                    Save Changes
                </button></div>
            </form>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.mjd-form-settings-page [data-panel] .row > [class*=col-]').forEach(col => {
        const label = col.firstElementChild;
        if (!label || label.tagName !== 'LABEL') return;
        const body = document.createElement('div');
        body.className = 'settings-field-body';
        while (label.nextSibling) body.append(label.nextSibling);
        col.append(body);
    });
    const ts = document.querySelectorAll('[data-tab]');
    const ps = document.querySelectorAll('[data-panel]');

    ts.forEach(t => t.onclick = () => {
        ps.forEach(p => p.hidden = p.dataset.panel !== t.dataset.tab);
        ts.forEach(x => x.classList.remove('active'));
        t.classList.add('active');
    });

    if (ts.length) {
        ts[0].classList.add('active');
        ps.forEach(p => p.hidden = p.dataset.panel !== ts[0].dataset.tab);
    }
})();
</script>

<script>
(function () {
    const table = document.getElementById('settings-table');
    const pk = document.getElementById('settings-primary-key');
    const status = document.getElementById('pk-status');

    if (!table || !pk) return;

    table.addEventListener('change', async () => {
        status.textContent = 'Loading primary key…';
        pk.innerHTML = '';

        const fd = new FormData();
        const requestedTable = table.value;
        document.querySelectorAll('button[form="mjd-settings-form"], #mjd-settings-form button[type="submit"]').forEach(b => b.disabled = true);

        fd.append('csrf_token', <?=json_encode(csrfToken())?>);
        fd.append('table', table.value);

        try {
            const r = await fetch('table-primary-key.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });

            const d = await r.json();

            if (table.value !== requestedTable) return;
            if (!d.ok) {
                throw new Error(d.message || 'Unable to load primary key');
            }

            if (d.primary_keys.length) {
                d.primary_keys.forEach(k => {
                    const o = document.createElement('option');
                    o.value = k;
                    o.textContent = k;
                    pk.append(o);
                });

                status.textContent = 'Primary key detected.';
            } else {
                const o = document.createElement('option');
                o.value = '';
                o.textContent = 'No primary key';
                pk.append(o);

                status.textContent = d.message || 'No primary key detected.';
            }
            document.querySelectorAll('button[form="mjd-settings-form"], #mjd-settings-form button[type="submit"]').forEach(b => b.disabled = false);
        } catch (e) {
            status.textContent = e.message;
        }
    });
})();
</script>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>