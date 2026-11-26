<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$memberId = (int)($_POST['member_id'] ?? $_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk-deactivate') {
    verifyCsrf();
    $memberIds = $_POST['member_ids'] ?? [];
    if (!is_array($memberIds)) $memberIds = [];
    $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds), static fn($id) => $id > 0)));
    if (!$memberIds) {
        flash('warning', 'Select at least one member.');
        redirect('/admin/search-members.php');
    }
    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $bulkUpdate = $pdo->prepare("UPDATE users_data SET active = 0 WHERE user_id IN ($placeholders)");
    $bulkUpdate->execute($memberIds);
    flash('success', $bulkUpdate->rowCount() . ' member account(s) deactivated.');
    redirect('/admin/search-members.php');
}


$memberQuery = $pdo->prepare('SELECT user_id, first_name, last_name, email, active FROM users_data WHERE user_id = ? LIMIT 1');
$memberQuery->execute([$memberId]);
$member = $memberQuery->fetch();

if (!$member) {
    http_response_code(404);
    exit('Member not found.');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if ($action === 'clone') {
        try {
            $pdo->beginTransaction();
            $source = $pdo->prepare('SELECT * FROM users_data WHERE user_id = ? FOR UPDATE');
            $source->execute([$memberId]);
            $row = $source->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Member not found');
            unset($row['user_id'], $row['modtime']);
            $suffix = bin2hex(random_bytes(5));
            $emailParts = explode('@', (string)$row['email'], 2);
            $row['email'] = 'cloned_' . substr($emailParts[0], 0, 70) . '_' . $suffix . '_@example.invalid';
            $row['filename'] = 'cloned-' . $memberId . '-' . $suffix;
            $row['password'] = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            foreach (['token','cookie','facebook_id','google_id','ref_code','bitly','last_login','user_consent','google_blocked_dates'] as $key) {
                if (array_key_exists($key, $row)) $row[$key] = '';
            }
            foreach (['verified','parent_id','google_calendar_connected'] as $key) {
                if (array_key_exists($key, $row)) $row[$key] = 0;
            }
            $row['signup_date'] = date('YmdHis');
            $columns = array_map(static fn($key) => '`' . str_replace('`', '``', $key) . '`', array_keys($row));
            $insert = $pdo->prepare('INSERT INTO users_data (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')');
            $insert->execute(array_values($row));
            $pdo->commit();
            flash('success', 'Member account cloned. Set a new email and password before member login.');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Member clone failed: ' . $error->getMessage());
            flash('danger', 'Unable to clone this member. No account was created.');
        }
        redirect('/admin/search-members.php?sort=id_desc');
    }
    if ($action === 'delete-account') {
        $update = $pdo->prepare('UPDATE users_data SET active = 0 WHERE user_id = ?');
        $update->execute([$memberId]);
        flash('success', 'Member account deactivated.');
        redirect('/admin/search-members.php');
    }
    if ($action === 'activate-account') {
        $update = $pdo->prepare('UPDATE users_data SET active = 2 WHERE user_id = ?');
        $update->execute([$memberId]);
        flash('success', 'Member account activated.');
        redirect('/admin/search-members.php');
    }
}

if ($action === 'details') {
    $pageTitle = 'Member Account Details';
    $activeMenu = 'members';
    require __DIR__ . '/../includes/header.php';
    ?>
    <section class="member-detail-card">
        <h1>Account Details</h1>
        <p><strong>Member ID:</strong> #<?= (int)$member['user_id'] ?></p>
        <p><strong>Name:</strong> <?= e(trim($member['first_name'] . ' ' . $member['last_name'])) ?></p>
        <p><strong>Email:</strong> <?= e($member['email']) ?></p>
        <a class="member-search-button inline-button" href="<?= e($config['base_url']) ?>/admin/search-members.php">Back to Search Members</a>
    </section>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

if ($action === 'change-password' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $pageTitle = 'Change Member Password';
    $activeMenu = 'members';
    require __DIR__ . '/../includes/header.php';
    ?>
    <section class="member-detail-card">
        <h1>Change Password – <?= e($member['first_name'] . ' ' . $member['last_name']) ?></h1>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="save-password">
            <input type="hidden" name="member_id" value="<?= $memberId ?>">
            <label>New password<input type="password" name="new_password" minlength="10" required></label>
            <button class="member-search-button" type="submit">Save Password</button>
        </form>
    </section>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

if ($action === 'save-password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['new_password'] ?? '');
    if (strlen($password) < 10) {
        flash('danger', 'Use a password with at least 10 characters.');
    } else {
        $update = $pdo->prepare('UPDATE users_data SET password = ? WHERE user_id = ?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $memberId]);
        flash('success', 'Member password updated.');
    }
    redirect('/admin/search-members.php');
}

if (in_array($action, ['credit-card', 'payment-history', 'clone'], true)) {
    $messages = [
        'credit-card' => 'Credit-card updates are unavailable because this project has no connected payment gateway or tokenized payment method.',
        'payment-history' => 'Payment history is not available for this member in the current project database integration.',
        'clone' => 'Cloning is not enabled because the project does not define safe rules for duplicating member credentials, subscriptions and ownership data.'
    ];
    flash('warning', $messages[$action]);
    redirect('/admin/search-members.php');
}

flash('warning', 'That member action is not available.');
redirect('/admin/search-members.php');
