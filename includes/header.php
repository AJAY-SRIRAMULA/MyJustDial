<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$activeMenu = $activeMenu ?? 'dashboard';
$flashMessage = getFlash();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | MyJustDial</title>
    <link rel="stylesheet" href="<?= e($config['base_url']) ?>/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <?= $extraHead ?? '' ?>
<link rel="icon" type="image/svg+xml" href="<?=e($config['base_url'])?>/assets/images/favicons/admin-favicon.svg">
</head>

<body>
    <div class="admin-shell">
        <?php require __DIR__ . '/sidebar.php'; ?>
        <main class="main-area">
            <header class="topbar">
                <div class="topbar-left"><button class="mobile-menu" id="mobileMenu" type="button">☰</button><span class="site-switch">◉ &nbsp;Switch Website</span><select class="top-select">
                        <option>All</option>
                    </select>
                    <div class="top-search-wrap"><input class="top-search" placeholder="Search Website..."><span>⌕</span></div>
                </div>
                <div class="topbar-right"><a class="quick-create" href="<?= e($config['base_url']) ?>/admin/toolbox/form-manager/create.php">✦ &nbsp;Quick Create <span class="header-caret" aria-hidden="true"></span></a><a class="visit-site" href="<?= e($config['base_url']) ?>/public/index.php" target="_blank" rel="noopener">Visit Website ↗</a>
                    <div class="account-menu"><button class="account-toggle" type="button" data-menu-toggle="account">◉ &nbsp;My Account <span class="header-caret" aria-hidden="true"></span></button>
                        <div class="small-dropdown" data-menu="account"><a href="<?= e($config['base_url']) ?>/admin/logout.php">Log Out</a></div>
                    </div>
                </div>
            </header>
            <div class="sale-strip"><strong> <svg width="20" height="13" viewBox="0 0 26 32" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M17.5 0L1 17.5H10L7.5 32L25 12.5H15.5L17.5 0Z" fill="url(#boltGradient)" stroke="#D97706" stroke-width="0.8" stroke-linejoin="round"/>
        <defs>
            <linearGradient id="boltGradient" x1="5" y1="2" x2="22" y2="29" gradientUnits="userSpaceOnUse">
                <stop stop-color="#FFF176"/>
                <stop offset="0.5" stop-color="#FFC107"/>
                <stop offset="1" stop-color="#FF8F00"/>
            </linearGradient>
        </defs>
    </svg> 24-Hour Flash Sale</strong>
                <div class="sale-right"><strong>[FLASH SALE] Lifetime Sites $249</strong><span>Ends Today · November 3, 2026</span><b>$249 BUY NOW ↗</b></div>
            </div>
            <div class="page-meta"><span>Build Version: 4.0</span><span>Website Time: <?= e(date('D, M d Y h:i A')) ?> UTC+05:30</span><a class="refresh-site" href="">⟳ &nbsp;Refresh Live Site</a></div>
            <div class="page-content">
                <?php if ($flashMessage): ?><div class="notice notice-<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?><button class="notice-close" type="button" data-dismiss-notice>×</button></div><?php endif; ?>
