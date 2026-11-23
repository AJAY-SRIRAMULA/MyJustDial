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
