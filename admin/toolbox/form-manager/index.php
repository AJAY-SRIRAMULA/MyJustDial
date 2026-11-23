<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/member_forms.php';
ensureMemberForms($pdo);

$pageTitle = 'Form Manager';
$activeMenu = 'forms';

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';
$type = $_GET['type'] ?? 'all';
$sort = $_GET['sort'] ?? 'name';

$order = $sort === 'newest' ? 'form_id DESC' : 'form_title ASC';

$sql = "SELECT * FROM forms WHERE form_action_type <> 'default'";
$params = [];

if ($q !== '') {
    $sql .= " AND (form_title LIKE ? OR form_name LIKE ? OR short_code LIKE ?)";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}

$sql .= ' ORDER BY ' . $order;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$custom = $stmt->fetchAll();

$defaultQuery = $pdo->query(
    "SELECT *
     FROM forms
     WHERE form_action_type = 'default'
     ORDER BY form_title ASC, form_name ASC"
);
$defaultForms = $defaultQuery->fetchAll();

if ($status !== 'all' && tableExists($pdo, 'users_meta')) {
    $custom = array_values(array_filter($custom, function ($form) use ($pdo, $status) {
        $statusQuery = $pdo->prepare(
            "SELECT value FROM users_meta
             WHERE `database` = 'forms' AND database_id = ? AND `key` = 'form_status'
             LIMIT 1"
        );
        $statusQuery->execute([$form['form_id']]);
        $savedStatus = $statusQuery->fetchColumn();

        return ($savedStatus === false ? 'enabled' : $savedStatus) === $status;
    }));
}

if ($type === 'member') {
    $custom = array_values(array_filter($custom, function ($form) {
        return stripos($form['form_name'], 'signup') !== false
            || stripos($form['form_name'], 'member') !== false
            || stripos($form['form_name'], 'listing') !== false
            || $form['form_name'] === 'about';
    }));
}

if ($type === 'posts') {
    $custom = array_values(array_filter($custom, function ($form) {
        return stripos($form['form_name'], 'post') !== false
            || stripos($form['form_name'], 'article') !== false
            || stripos($form['form_name'], 'blog') !== false;
    }));
}

if ($type === 'general') {
    $custom = array_values(array_filter($custom, function ($form) {
        return stripos($form['form_name'], 'signup') === false
            && stripos($form['form_name'], 'member') === false
            && stripos($form['form_name'], 'listing') === false
            && $form['form_name'] !== 'about'
            && stripos($form['form_name'], 'post') === false
            && stripos($form['form_name'], 'article') === false
            && stripos($form['form_name'], 'blog') === false;
    }));
}

$formPreviews = [];

if ($custom) {
    $formNames = array_column($custom, 'form_name');
    $placeholders = implode(',', array_fill(0, count($formNames), '?'));

    $previewQuery = $pdo->prepare(
        "SELECT form_name, field_text, field_type, field_required
         FROM form_fields
         WHERE form_name IN ($placeholders)
         ORDER BY field_order, field_id"
    );
    $previewQuery->execute($formNames);

    foreach ($previewQuery->fetchAll() as $previewField) {
        $formPreviews[$previewField['form_name']][] = $previewField;
    }
}

require __DIR__ . '/../../../includes/header.php';
?>
<div class="form-manager-page">
    <div class="fm-page-heading">
        <div class="fm-info">
            <span class="info-icon">●</span>
            <div>
                <strong>Form Manager</strong>
                – Build and manage website forms. Add or remove input fields when editing individual forms.
            </div>
        </div>
        <a
            class="fm-new-button"
            href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/index.php?new=1"
        >
            New Form <b>＋</b>
        </a>
    </div>

    <form class="fm-toolbar" method="get">
        <div class="fm-bulk">
            <select name="bulk_action" id="bulkAction">
                <option value="">Bulk Actions</option>
                <option value="enable">Enable Selected</option>
                <option value="disable">Disable Selected</option>
                <option value="delete">Delete Selected</option>
            </select>
            <input
                type="hidden"
                id="bulkCsrfToken"
                value="<?= e(csrfToken()) ?>"
            >
            <button type="button" id="applyBulk">Apply</button>
        </div>

        <div class="fm-filters">
            <select name="status">
                <option value="all">Filter by Status</option>
                <option value="enabled" <?= $status === 'enabled' ? 'selected' : '' ?>>Enabled</option>
                <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>Disabled</option>
            </select>

            <select name="type">
                <option value="all">Filter by Type</option>
                <option value="member" <?= $type === 'member' ? 'selected' : '' ?>>Member Listings</option>
                <option value="posts" <?= $type === 'posts' ? 'selected' : '' ?>>Post Types</option>
                <option value="general" <?= $type === 'general' ? 'selected' : '' ?>>General</option>
            </select>

            <select name="sort">
                <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A - Z</option>
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
            </select>

            <div class="fm-search">
                <span>⌕</span>
                <input name="q" value="<?= e($q) ?>" placeholder="Search by Keyword">
            </div>
        </div>
    </form>

    <div class="fm-table-wrap">
        <table class="fm-table custom-table">
            <thead>
                <tr>
                    <th class="check-col"><input type="checkbox" id="selectAll"></th>
                    <th>Actions</th>
                    <th>Preview</th>
                    <th>Custom Forms (<?= count($custom) ?>)</th>
                    <th>Shortcode</th>
                    <th>Last Update</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$custom): ?>
                    <tr>
                        <td colspan="6" class="fm-empty">No custom forms found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($custom as $form): ?>
                        <?php
                        $statusQuery = $pdo->prepare(
                            "SELECT value
                             FROM users_meta
                             WHERE `database` = 'forms'
                             AND database_id = ?
                             AND `key` = 'form_status'
                             LIMIT 1"
                        );
                        $statusQuery->execute([$form['form_id']]);
                        $state = $statusQuery->fetchColumn();
                        $enabled = $state === false || $state === 'enabled';
                        $displayTitle = preg_replace('/\s*\(Custom\)$/i', '', (string)($form['form_title'] ?: $form['form_name']));
                        $code = $form['short_code'] ?: '[form=' . $form['form_name'] . ']';

                        if (tableExists($pdo, 'users_meta')) {
                            $publicAliasQuery = $pdo->prepare(
                                "SELECT `value`
                                 FROM users_meta
                                 WHERE `database` = 'forms'
                                 AND database_id = ?
                                 AND `key` IN (
                                     'public_shortcode_contact_form',
                                     'public_shortcode_member_login',
                                     'public_shortcode_signup_free'
                                 )
                                 LIMIT 1"
                            );
                            $publicAliasQuery->execute([(int)$form['form_id']]);
                            $publicAlias = (string)($publicAliasQuery->fetchColumn() ?: '');

                            if ($publicAlias !== '') {
                                $code = '[form=' . $publicAlias . ']';
                            }
                        }
                        ?>
                        <tr>
                            <td>
                                <input
                                    type="checkbox"
                                    class="form-row-check"
                                    value="<?= (int) $form['form_id'] ?>"
                                >
                            </td>
                            <td class="fm-actions-cell">
                                <a
                                    class="fm-edit-button"
                                    href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/edit.php?id=<?= (int) $form['form_id'] ?>"
                                >Edit</a>
<div class="fm-action-menu"><button type="button" class="fm-action-toggle" data-menu-toggle="form-<?= (int)$form['form_id'] ?>" aria-expanded="false">Actions <span aria-hidden="true"><svg width="7" height="12" viewBox="0 0 7 12" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M6.72153 4.40647C6.72153 4.40647 6.74271 4.42765 6.78506 4.46999C6.82741 4.51234 6.84858 4.59147 6.84858 4.70737C6.84858 4.82327 6.80623 4.92357 6.72153 5.00827L3.72591 8.0039C3.64121 8.0886 3.54091 8.13094 3.42501 8.13094C3.30911 8.13094 3.20881 8.0886 3.12411 8.0039L0.128481 5.00827C0.0437832 4.92357 0.00143433 4.82327 0.00143433 4.70737C0.00143433 4.59147 0.0437832 4.49117 0.128481 4.40647C0.213179 4.32177 0.313479 4.27942 0.429381 4.27942H6.42063C6.53654 4.27942 6.63684 4.32177 6.72153 4.40647Z" fill="#253342"/>
    </svg></span></button><div class="fm-action-dropdown" data-menu="form-<?= (int)$form['form_id'] ?>">
<a href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/preview.php?id=<?= (int)$form['form_id'] ?>">▣ &nbsp;Preview</a><a href="#" class="copy-shortcode" data-code="<?= e($code) ?>">▢ &nbsp;Copy Shortcode</a>
<?php if (($form['form_action_type'] ?? '') !== 'login'): ?><a href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/actions.php?action=clone&id=<?= (int)$form['form_id'] ?>&token=<?= e(csrfToken()) ?>">▧ &nbsp;Clone Form</a><?php endif; ?>
<a href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/actions.php?action=toggle&id=<?= (int)$form['form_id'] ?>&token=<?= e(csrfToken()) ?>"><?= $enabled?'◉ &nbsp;Disable':'◉ &nbsp;Enable' ?></a>
<a class="danger-action" href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/actions.php?action=delete&id=<?= (int)$form['form_id'] ?>&token=<?= e(csrfToken()) ?>" onclick="return confirm('Delete this form and its fields?')">✖ &nbsp;Delete</a></div></div></td>
<td>
<a class="fm-preview-thumb" href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/preview.php?id=<?= (int)$form['form_id'] ?>" aria-label="Preview <?= e($displayTitle) ?>">
    <strong><?= e($displayTitle) ?></strong>
    <?php
    $thumbnailFields = array_slice($formPreviews[$form['form_name']] ?? [], 0, 3);
    foreach ($thumbnailFields as $previewField):
        $previewType = strtolower((string)$previewField['field_type']);
        $isSpecial = in_array($previewType, ['button', 'submit', 'section', 'html', 'custom'], true);
    ?>
        <span class="<?= $isSpecial ? 'fm-preview-special' : 'fm-preview-field' ?>">
            <?= e($previewField['field_text']) ?><?= (int)$previewField['field_required'] === 1 ? ' *' : '' ?>
        </span>
    <?php endforeach; ?>
    <?php if (!$thumbnailFields): ?><span class="fm-preview-empty">No fields</span><?php endif; ?>
</a>
</td><td><?= e($displayTitle) ?></td><td><code><?= e($code) ?></code></td><td class="fm-date"><?= e(date('m/d/Y',strtotime($form['revision_timestamp']))) ?></td></tr>
<?php endforeach; endif; ?></tbody></table></div>
<div class="fm-showing">Showing 1 to <?= count($custom) ?> of <?= count($custom) ?> entries</div>


<section class="fm-default-section">
    <div class="fm-default-heading">
        <h3>Default Forms <span>(<?= count($defaultForms) ?>)</span></h3>
    </div>

    <div class="fm-default-table-wrap">
        <table class="fm-table default-table">
            <thead>
                <tr>
                    <th class="fm-default-actions-heading">Actions </th>
                    <th>Form Name</th>
                    <th>Shortcode</th>
                    <th class="fm-default-date-heading">Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$defaultForms): ?>
                    <tr>
                        <td colspan="4" class="fm-empty">No default forms are available in the database.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($defaultForms as $defaultForm):
                        $defaultCode = $defaultForm['short_code']
                            ?: '[form=' . $defaultForm['form_name'] . ']';
                        $defaultDate = !empty($defaultForm['revision_timestamp'])
                            ? date('m/d/Y', strtotime($defaultForm['revision_timestamp']))
                            : '—';
                    ?>
                        <tr>
                            <td class="fm-default-actions-cell">
                                <div class="fm-action-menu">
                                    <button
                                        type="button"
                                        class="fm-action-toggle fm-default-action-toggle"
                                        data-menu-toggle="default-<?= (int)$defaultForm['form_id'] ?>"
                                        aria-expanded="false"
                                    >
                                        Actions <span aria-hidden="true"><svg width="7" height="12" viewBox="0 0 7 12" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M6.72153 4.40647C6.72153 4.40647 6.74271 4.42765 6.78506 4.46999C6.82741 4.51234 6.84858 4.59147 6.84858 4.70737C6.84858 4.82327 6.80623 4.92357 6.72153 5.00827L3.72591 8.0039C3.64121 8.0886 3.54091 8.13094 3.42501 8.13094C3.30911 8.13094 3.20881 8.0886 3.12411 8.0039L0.128481 5.00827C0.0437832 4.92357 0.00143433 4.82327 0.00143433 4.70737C0.00143433 4.59147 0.0437832 4.49117 0.128481 4.40647C0.213179 4.32177 0.313479 4.27942 0.429381 4.27942H6.42063C6.53654 4.27942 6.63684 4.32177 6.72153 4.40647Z" fill="#253342"/>
    </svg></span>
                                    </button>
                                    <div
                                        class="fm-action-dropdown fm-default-action-dropdown"
                                        data-menu="default-<?= (int)$defaultForm['form_id'] ?>"
                                    >
                                        <a
                                            href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/preview.php?id=<?= (int)$defaultForm['form_id'] ?>"
                                        >
                                            <span class="fm-menu-icon">▣</span> Preview Form
                                        </a>
                                        <a
                                            href="#"
                                            class="copy-shortcode"
                                            data-code="<?= e($defaultCode) ?>"
                                        >
                                            <span class="fm-menu-icon">▢</span> Copy Shortcode
                                        </a>
                                        <a
                                            class="fm-customize-action"
                                            href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/actions.php?action=customize&id=<?= (int)$defaultForm['form_id'] ?>&token=<?= e(csrfToken()) ?>"
                                            onclick="return confirm('Create an editable custom copy of this default form?')"
                                        >
                                            <span class="fm-menu-icon">✦</span> Customize
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fm-default-name">
                                    <span class="default-badge">DEFAULT</span>
                                    <span><?= e($defaultForm['form_title'] ?: $defaultForm['form_name']) ?></span>
                                </div>
                                <small class="fm-default-variable"><?= e($defaultForm['form_name']) ?></small>
                            </td>
                            <td>
                                <code class="fm-default-shortcode"><?= e($defaultCode) ?></code>
                            </td>
                            <td class="fm-date">
                                <?= e($defaultDate) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="fm-default-footer">
        Showing 1 to <?= count($defaultForms) ?> of <?= count($defaultForms) ?> entries
    </div>
</section>
</div>

<?php if (isset($_GET['new'])): ?>
<div class="create-modal-backdrop">
 <section class="create-modal" role="dialog" aria-modal="true" aria-labelledby="createFormTitle">
  <div class="create-modal-title"><span id="createFormTitle">Create New Form</span><a class="create-modal-close" href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/index.php" aria-label="Close dialog">&times;</a></div>
  <form method="post" action="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/create.php" class="create-modal-body" id="newFormStart">
   <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
   <div class="create-tip"><b>●</b> On the next page you can create new fields for your form.</div>
   <label for="formNickname">* Form Nickname</label>
   <input type="text" name="form_nickname" id="formNickname" autocomplete="off" required placeholder="Enter form nickname">
   <small id="formNicknameHelp">Form nickname is for internal use only and will not be seen by website visitors.</small>
   <label for="formVariable">* Form Variable Name</label>
   <input type="text" id="formVariable" readonly placeholder="form_variable_name">
   <small>Name used by the system. Variable name can include lowercase letters and underscores.</small>
   <div class="create-modal-footer"><button type="submit" class="save-continue">Save &amp; Continue</button></div>
  </form>
 </section>
</div>
<script>
(function () {
    var nicknameInput = document.getElementById('formNickname');
    var variableInput = document.getElementById('formVariable');
    var nicknameHelp = document.getElementById('formNicknameHelp');
    var formManagerUrl = <?= json_encode($config['base_url'] . '/admin/toolbox/form-manager/index.php') ?>;

    function createVariableName(value) {
        return value
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .replace(/_+/g, '_');
    }

    nicknameInput.addEventListener('input', function () {
        variableInput.value = createVariableName(nicknameInput.value);
    });

    document.getElementById('newFormStart').addEventListener('submit', function (event) {
        if (!variableInput.value) {
            event.preventDefault();
            nicknameInput.focus();
            alert('Enter a nickname containing at least one letter or number.');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            window.location.href = formManagerUrl;
        }
    });

    document.querySelector('.create-modal-backdrop').addEventListener('click', function (event) {
        if (event.target === this) {
            window.location.href = formManagerUrl;
        }
    });

    nicknameInput.focus();
}());
</script>
<?php endif; ?>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>
