<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verifyCsrf();

if (empty($_SESSION['admin_impersonation']['admin_id'])) {
    redirect('/public/login.php');
}
if (empty($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = (int) $_SESSION['admin_impersonation']['admin_id'];
    $_SESSION['admin_name'] = (string) ($_SESSION['admin_impersonation']['admin_name'] ?? '');
}
unset($_SESSION['member_id'], $_SESSION['member_name'], $_SESSION['member_email'], $_SESSION['is_impersonating'], $_SESSION['admin_impersonation']);
redirect('/admin/search-members.php');
