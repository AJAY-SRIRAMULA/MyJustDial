<?php

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public_website.php';
require __DIR__ . '/../includes/public_forms.php';

$settings = getPublicWebsiteSettings($pdo);
$baseUrl = $config['base_url'];
$phoneNumber = $settings['contact_phone'] !== ''
    ? $settings['contact_phone']
    : '1-800-555-5555';
$contactShortcode = '[form=contact_form]';
$contactForm = getPublicFormByShortcode($pdo, $contactShortcode);
$submissionMessage = null;
$submissionSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedShortcode = (string)($_POST['public_form_shortcode'] ?? '');
    if ($postedShortcode === 'contact_form') {
        $result = processPublicFormSubmission($pdo, $contactShortcode);
        $submissionMessage = $result['message'];
        $submissionSuccess = $result['ok'];
    }
}

$bannerImage = $baseUrl . '/public/assets/images/contact-banner.jpg';
$websiteCssPath = __DIR__ . '/assets/css/website.css';
$websiteCssVersion = is_file($websiteCssPath) ? filemtime($websiteCssPath) : time();
$contactCssPath = __DIR__ . '/assets/css/contact.css';
$contactCssVersion = is_file($contactCssPath) ? filemtime($contactCssPath) : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contact <?= e($settings['site_name']) ?>">
    <title>Contact Us | <?= e($settings['site_name']) ?></title>
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/public/assets/css/website.css?v=<?= (int)$websiteCssVersion ?>">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/public/assets/css/contact.css?v=<?= (int)$contactCssVersion ?>">
<link rel="icon" type="image/svg+xml" href="<?=e($config['base_url'])?>/assets/images/favicons/public-favicon.svg">
</head>
<body class="contact-page">
    <header class="public-header">
        <div class="site-container header-inner">
            <a class="brand" href="<?= e($baseUrl) ?>/public/index.php">
                <?php if ($settings['logo_url'] !== ''): ?>
                    <img src="<?= e($settings['logo_url']) ?>" alt="<?= e($settings['site_name']) ?>">
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
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg> Contact
                </a>
                <a href="<?= e($baseUrl) ?>/public/login.php"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5"></path><path d="M14 16l5-4-5-4M19 12H9"></path></svg> Login</a>
                <a class="join-button" href="<?= e($baseUrl) ?>/public/membership.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="4"></circle></svg> Members – Join Today
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

    <main>
        <section class="contact-hero" style="--contact-banner: url('<?= e($bannerImage) ?>')">
            <div class="site-container">
                <p>HAVE A QUESTION?</p>
                <h1>Contact <?= e($settings['site_name']) ?></h1>
            </div>
        </section>

        <section class="contact-content">
            <div class="site-container contact-content-grid">
                <div class="contact-help">
                    <strong>How Can We Help You?</strong>
                    <ul>
                        <li><strong>Find a Dentist</strong> – Need help finding the right dentist or dental clinic?</li>
                        <li><strong>Dental Services</strong> – Have questions about our dental service categories?</li>
                        <li><strong>Appointments</strong> – Need assistance with booking or contacting a dentist?</li>
                        <li><strong>Dentist Registration</strong> – Are you a dentist or dental clinic interested in joining our directory?</li>
                        <li><strong>General Support</strong> – Have a question about using our platform? Send us a message.</li>
                    </ul>
                </div>

                <div class="contact-form-card">
                    <h2><?= e($contactForm['form_title'] ?? 'Contact Us') ?></h2>
                    <?php if ($submissionMessage !== null): ?>
                        <div class="contact-form-alert <?= $submissionSuccess ? 'contact-form-alert-success' : 'contact-form-alert-error' ?>" role="status">
                            <?= e($submissionMessage) ?>
                        </div>
                    <?php endif; ?>
                    <?= renderPublicForm($pdo, $contactShortcode, csrfToken()) ?>
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
                    <a class="footer-email" href="mailto:<?= e($settings['contact_email']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg></a>
                <?php else: ?>
                    <a class="footer-email" href="#contact"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg></a>
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
                <a href="<?= e($baseUrl) ?>/public/login.php">Reset Password</a>
            </div>
            <a class="footer-join-button" href="<?= e($baseUrl) ?>/public/membership.php"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="4"></circle></svg> Members – Join Today</a>
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
