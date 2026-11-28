<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public_website.php';
require __DIR__ . '/../includes/public_forms.php';
require __DIR__ . '/../includes/password_retrieval.php';
$settings = getPublicWebsiteSettings($pdo);
$baseUrl = $config['base_url'];
$logoUrl = $settings['logo_url'];
$phoneNumber = $settings['contact_phone'] ?: '1-800-555-5555';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$id = (int)($_POST['uid'] ?? $_GET['uid'] ?? 0);
$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$message = ''; $success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    if (!hash_equals(csrfToken(),(string)($_POST['csrf_token'] ?? ''))) $message = 'Refresh the page and try again.';
    elseif (strlen($password)<8 || strlen($password)>72 || $password !== ($_POST['confirm'] ?? '')) $message = 'Use 8–72 bytes and enter matching passwords.';
    else { try { $success = passwordResetComplete($pdo,$id,$token,$password); $message = $success ? 'Password updated. You can now log in.' : 'This reset link is invalid or expired.'; } catch (Throwable $e) { error_log('Password reset failed: '.$e->getMessage()); $message = 'Unable to reset password. Try again later.'; } }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Password Retrieval</title>
<link rel="stylesheet" href="<?= e($baseUrl) ?>/public/assets/css/website.css">
<link rel="stylesheet" href="<?= e($baseUrl) ?>/public/assets/css/login.css">
<link rel="stylesheet" href="<?= e($baseUrl) ?>/public/assets/css/contact.css">
<link rel="stylesheet" href="<?= e($baseUrl) ?>/assets/css/password-retrieval.css">
</head><body class="login-page membership-page password-retrieval-page">
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

<main class="login-content"><div class="login-card"><h2>Reset Password</h2>
<?php if ($message !== ''): ?><div class="retrieval-message" role="status"><?= e($message) ?></div><?php endif; ?>
<?php if (!$success): ?><form method="post" action="<?= e($baseUrl) ?>/public/password-reset.php">
<input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="uid" value="<?= e($id) ?>"><input type="hidden" name="token" value="<?= e($token) ?>">
<div class="login-field"><label for="new-password">New Password</label><input id="new-password" name="password" type="password" autocomplete="new-password" required minlength="8" maxlength="72"></div>
<div class="login-field"><label for="confirm-password">Confirm Password</label><input id="confirm-password" name="confirm" type="password" autocomplete="new-password" required minlength="8" maxlength="72"></div>
<button class="login-submit" type="submit">Reset Password</button></form><?php endif; ?>
<div class="login-signup"><a href="<?= e($baseUrl) ?>/public/login.php">Back to Login Page</a></div></div></main>
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
                <a href="<?= e($baseUrl) ?>/public/password-retrieval.php">Reset Password</a>
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
