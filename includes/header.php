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
                <div class="topbar-left">
                    <button class="mobile-menu" id="mobileMenu" type="button">☰</button>
                    <span class="site-switch"><i class="fa fa-arrow-circle-left"></i> Switch Website</span>
                    <div class="top-search-group">
                        <button class="top-select" type="button" data-menu-toggle="search-category">All <i class="fa fa-chevron-down"></i></button>
                        <div class="top-category-dropdown small-dropdown" data-menu="search-category">
                            <?php foreach (['All', 'Members', 'Single Photo Posts', 'Multi Photo Posts', 'Reviews', 'Leads', 'Form Inquiries', 'Membership Plans', 'Widgets', 'Sidebars', 'Menus', 'Post Settings', 'Forms'] as $searchCategory): ?>
                                <button type="button" class="top-category-option" data-category="<?= e($searchCategory) ?>"><?= e($searchCategory) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="top-search-wrap"><input class="top-search" placeholder="Search Website..." aria-label="Search Website"><button class="top-search-submit" type="button" aria-label="Search"><i class="fa fa-search"></i></button></div>
                </div>
                <div class="topbar-right">
                    <div class="account-menu bd-butler-menu">
                        <button class="quick-create" type="button" data-menu-toggle="bd-butler"><i class="fa fa-magic"></i> BD Butler <span class="header-caret" aria-hidden="true"></span></button>
                        <div class="small-dropdown bd-butler-dropdown" data-menu="bd-butler">
                            <a href="#" aria-disabled="true"><span class="bd-menu-icon purple"><i class="fa fa-magic"></i></span><span><strong>Ask BD Butler</strong><small>Chat, get answers, or edit with AI</small></span></a>
                            <a href="<?= e($config['base_url']) ?>/admin/search-members.php"><span class="bd-menu-icon blue"><i class="fa fa-user-plus"></i></span><span><strong>Add New Member</strong><small>Set up a new member account</small></span></a>
                            <a href="#" aria-disabled="true"><span class="bd-menu-icon pink"><i class="fa fa-paper-plane"></i></span><span><strong>Create Newsletter</strong><small>Send bulk email to members</small></span></a>
                            <a href="#" aria-disabled="true"><span class="bd-menu-icon green"><i class="fa fa-calculator"></i></span><span><strong>Create Invoice</strong><small>Bill a member for payment</small></span></a>
                            <a href="#" aria-disabled="true"><span class="bd-menu-icon purple"><i class="fa fa-pencil-square-o"></i></span><span><strong>Create Post</strong><small>Publish a new article or blog</small></span></a>
                            <a href="#" aria-disabled="true"><span class="bd-menu-icon teal"><i class="fa fa-file-image-o"></i></span><span><strong>Create Web Page</strong><small>Build page with SEO content</small></span></a>
                            <a href="#" aria-disabled="true"><span class="bd-menu-icon amber"><i class="fa fa-tag"></i></span><span><strong>Create Coupon Code</strong><small>Set up a discount code</small></span></a>
                        </div>
                    </div>
                    <a class="visit-site" href="<?= e($config['base_url']) ?>/public/index.php" target="_blank" rel="noopener">Visit Website <i class="fa fa-external-link"></i></a>
                    <div class="account-menu">
                        <button class="account-toggle" type="button" data-menu-toggle="account"><i class="fa fa-user-circle-o"></i> My Account <span class="header-caret" aria-hidden="true"></span></button>
                        <div class="small-dropdown account-dropdown" data-menu="account">
                            <div class="account-dropdown-header"><i class="fa fa-user-circle-o"></i><span><small>Signed in as</small><strong><?= e($_SESSION['admin_name'] ?? $_SESSION['admin_email'] ?? 'Admin') ?></strong></span></div>
                            <a href="<?= e($config['base_url']) ?>/admin/dashboard.php"><i class="fa fa-th-large"></i> View My Websites</a>
                            <a href="#" aria-disabled="true"><i class="fa fa-life-ring"></i> View My Tickets</a>
                            <a href="#" aria-disabled="true"><i class="fa fa-plus-circle"></i> Create New Ticket</a>
                            <hr>
                            <a href="#" aria-disabled="true"><i class="fa fa-gift"></i> Refer Friends &amp; Earn 50%</a>
                            <hr>
                            <a href="<?= e($config['base_url']) ?>/admin/logout.php"><i class="fa fa-sign-out"></i> Sign Out</a>
                        </div>
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
