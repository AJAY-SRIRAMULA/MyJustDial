<?php

if (empty($_SESSION['admin_id']) && !empty($_SESSION['admin_impersonation']['admin_id'])) {
    $_SESSION['admin_id'] = (int) $_SESSION['admin_impersonation']['admin_id'];
    $_SESSION['admin_name'] = (string) ($_SESSION['admin_impersonation']['admin_name'] ?? '');
}

if (empty($_SESSION['admin_id'])) {
    redirect('/admin/login.php');
}
