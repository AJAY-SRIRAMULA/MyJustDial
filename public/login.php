<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public_website.php';
require __DIR__ . '/../includes/public_forms.php';

$settings = getPublicWebsiteSettings($pdo);
$baseUrl = $config['base_url'];
$phoneNumber = $settings['contact_phone'] !== ''
    ? $settings['contact_phone']
    : '1-800-555-5555';

$email = '';
$error = '';
$loginShortcode = '[form=member_login]';
$loginForm = getPublicFormByShortcode($pdo, $loginShortcode);

if (isset($_GET['logout'])) {
    if (!empty($_SESSION['admin_impersonation']['admin_id'])) {
        if (empty($_SESSION['admin_id'])) {
            $_SESSION['admin_id'] = (int) $_SESSION['admin_impersonation']['admin_id'];
            $_SESSION['admin_name'] = (string) ($_SESSION['admin_impersonation']['admin_name'] ?? '');
        }
        unset($_SESSION['member_id'], $_SESSION['member_email'], $_SESSION['member_name'], $_SESSION['is_impersonating'], $_SESSION['admin_impersonation']);
        redirect('/admin/search-members.php');
    }
    unset($_SESSION['member_id'], $_SESSION['member_email'], $_SESSION['member_name'], $_SESSION['is_impersonating']);
    session_regenerate_id(true);
    redirect('/public/login.php');
}

if (!empty($_SESSION['member_id'])) {
    redirect('/public/account/home.php');
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string)($_POST['public_form_shortcode'] ?? '') === 'member_login'
) {
    $submittedFields = $_POST['fields'] ?? [];
    $email = is_array($submittedFields)
        ? trim((string)($submittedFields['email'] ?? ''))
        : '';

    $result = processPublicLoginSubmission($pdo, $loginShortcode);
    if ($result['ok']) {
        redirect('/public/account/home.php');
    }

    $error = $result['message'];
}

$logoUrl = $settings['logo_url'];
$websiteCssPath = __DIR__ . '/assets/css/website.css';
$websiteCssVersion = is_file($websiteCssPath) ? filemtime($websiteCssPath) : time();
$loginCssPath = __DIR__ . '/assets/css/login.css';
$loginCssVersion = is_file($loginCssPath) ? filemtime($loginCssPath) : time();
$bannerImage = $baseUrl . '/public/assets/images/hero-lake.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Member login for <?= e($settings['site_name']) ?>">
    <title>Member Login | <?= e($settings['site_name']) ?></title>
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/public/assets/css/website.css?v=<?= (int)$websiteCssVersion ?>">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/public/assets/css/login.css?v=<?= (int)$loginCssVersion ?>">
<link rel="icon" type="image/svg+xml" href="<?=e($config['base_url'])?>/assets/images/favicons/public-favicon.svg">
</head>
<body class="login-page membership-page">
    <header class="public-header">
        <div class="site-container header-inner">
            <a class="brand" href="<?= e($baseUrl) ?>/public/index.php">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= e($logoUrl) ?>" alt="<?= e($settings['site_name']) ?>">
                <?php else: ?>
                    <span class="brand-mark">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="8" r="4"></circle>
                            <path d="M4 21c0-4.2 3.4-7 8-7s8 2.8 8 7"></path>
                        </svg>
                    </span>
                <?php endif; ?>
                <span class="brand-copy">
                    <strong><?= e($settings['site_name']) ?></strong>
                    <small><?= e($settings['tagline']) ?></small>
                </span>
            </a>

            <div class="header-actions">
                <a class="header-phone" href="tel:<?= e($phoneNumber) ?>"><?= e($phoneNumber) ?></a>
                <a href="<?= e($baseUrl) ?>/public/contact.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg>
                    Contact
                </a>
                <a href="<?= e($baseUrl) ?>/public/login.php" aria-current="page">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5"></path><path d="M14 16l5-4-5-4M19 12H9"></path></svg>
                    Login
                </a>
                <a class="join-button" href="<?= e($baseUrl) ?>/public/membership.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="4"></circle></svg>
                    Members – Join Today
                </a>
            </div>
        </div>
    </header>

    <nav class="public-nav" aria-label="Main navigation">
        <div class="site-container nav-inner">
            <a href="<?= e($baseUrl) ?>/public/index.php">Home</a>
            <a href="<?= e($baseUrl) ?>/public/search.php">Search Directory</a>
            <a href="<?= e($baseUrl) ?>/public/index.php#how-it-works">How It Works</a>
            <a href="<?= e($baseUrl) ?>/public/index.php#website-blog">Website Blog</a>
        </div>
    </nav>

    <main class="login-main">
        <section class="login-hero" style="--login-banner: url('<?= e($bannerImage) ?>')">
            <div class="site-container">
                <p>Welcome Members</p>
                <h1>Log Into Your Account</h1>
            </div>
        </section>

        <section class="login-content">
            <div class="login-card">
                <h2><?= e($loginForm['form_title'] ?? 'Member Login') ?></h2>

                <?php if ($error !== ''): ?>
                    <div class="login-message" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <?= renderPublicLoginForm($pdo, $loginShortcode, csrfToken(), $email) ?>

                <div class="login-signup">
                    Don't Have an Account? <a href="<?= e($baseUrl) ?>/public/membership.php">Get Listed<br>Today</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="public-footer">
        <div class="site-container footer-main">
            <div class="footer-about">
                <strong>About</strong>
                <p>Finding members is easy with <?= e($settings['site_name']) ?>. Easily connect with members in your area. For members, our website is a powerful tool for attracting more customers.</p>
                <?php if ($settings['contact_email'] !== ''): ?>
                    <a class="footer-email" href="mailto:<?= e($settings['contact_email']) ?>" aria-label="Email us">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg>
                    </a>
                <?php else: ?>
                    <a class="footer-email" href="<?= e($baseUrl) ?>/public/contact.php" aria-label="Contact us">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg>
                    </a>
                <?php endif; ?>
            </div>
            <div class="footer-column">
                <strong>Website</strong>
                <a href="<?= e($baseUrl) ?>/public/index.php#how-it-works">How It Works</a>
                <a href="<?= e($baseUrl) ?>/public/contact.php">Contact Us</a>
            </div>
            <div class="footer-column">
                <strong>Search</strong>
                <a href="<?= e($baseUrl) ?>/public/search.php">Browse Directory</a>
                <a href="<?= e($baseUrl) ?>/public/search.php">Search by Category</a>
            </div>
            <div class="footer-column">
                <strong>Support</strong>
                <a href="<?= e($baseUrl) ?>/public/login.php">Member Login</a>
                <a href="<?= e($baseUrl) ?>/public/contact.php?topic=password-reset">Reset Password</a>
            </div>
            <a class="footer-join-button" href="<?= e($baseUrl) ?>/public/membership.php">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="4"></circle></svg>
                Members – Join Today
            </a>
        </div>
        <div class="footer-bottom">
            <div class="site-container">
                <span>&copy; <?= date('Y') ?> <?= e($settings['site_name']) ?>. All Rights Reserved.</span>
                <span><a href="#terms">Terms of Use</a><i>|</i><a href="#privacy">Privacy Policy</a></span>
            </div>
        </div>
    </footer>
</body>
</html>
