<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verifyCsrf();

$memberId = (int)($_POST['member_id'] ?? 0);
$statement = $pdo->prepare(
    'SELECT user_id, first_name, last_name, email, active
     FROM users_data WHERE user_id = ? LIMIT 1'
);
$statement->execute([$memberId]);
$member = $statement->fetch();

if (!$member || !isActiveMemberRecord($member['active'])) {
    http_response_code(403);
    exit('The selected member is not active. Activate the member from the admin member list before logging in.');
}

$_SESSION['admin_impersonation'] = [
    'admin_id' => (int) $_SESSION['admin_id'],
    'admin_name' => (string) ($_SESSION['admin_name'] ?? ''),
    'started_at' => time()
];
$_SESSION['member_id'] = (int) $member['user_id'];
$_SESSION['member_name'] = trim((string) $member['first_name'] . ' ' . (string) $member['last_name']);
$_SESSION['member_email'] = (string) $member['email'];
$_SESSION['is_impersonating'] = true;

redirect('/public/account/home.php');
