<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public_website.php';

$settings = getPublicWebsiteSettings($pdo);
$baseUrl = $config['base_url'];
$phoneNumber = $settings['contact_phone'] !== ''
    ? $settings['contact_phone']
    : '1-800-555-5555';

$plans = [];

if (tableExists($pdo, 'subscription_types')) {
    $planQuery = $pdo->prepare(
        "SELECT
            subscription_id,
            subscription_name,
            monthly_amount,
            yearly_amount,
            profile_type,
            profile_photo,
            photo_limit,
            service_limit,
            website_link,
            social_link,
            seal_link,
            show_about,
            show_experience,
            show_education,
            show_background,
            show_affiliations,
            show_publications,
            show_awards,
            show_slogan,
            show_phone,
            searchable
         FROM subscription_types
         WHERE sub_active = 1
           AND subscription_type = 'member'
         ORDER BY monthly_amount DESC, yearly_amount DESC, subscription_id DESC"
    );
    $planQuery->execute();
    $plans = $planQuery->fetchAll(PDO::FETCH_ASSOC);
}

$planDisplayNames = [
    1 => 'Free',
    2 => 'Standard',
    3 => 'Premium',
];

$getPlanDisplayName = static function (array $plan) use ($planDisplayNames): string {
    $planId = (int)$plan['subscription_id'];

    if (isset($planDisplayNames[$planId])) {
        return $planDisplayNames[$planId];
    }

    $name = trim((string)$plan['subscription_name']);

    return preg_replace('/^Membership Plan\\s+\\d+$/i', 'Membership', $name);
};

$formatPrice = static function ($amount): string {
    return number_format((float)$amount, 0, '.', ',');
};

$getPlanFeatures = static function (array $plan): array {
    $features = [];

    if ((int)$plan['profile_photo'] === 1) {
        $features[] = 'Profile Photo';
    }

    if ((int)$plan['website_link'] === 1) {
        $features[] = 'Website Link';
    }

    if ((int)$plan['social_link'] === 1) {
        $features[] = 'Social Media Links';
    }

    if ((int)$plan['seal_link'] === 1) {
        $features[] = 'Profile Seal';
    }

    if ((int)$plan['show_about'] === 1) {
        $features[] = 'About Information';
    }

    if ((int)$plan['show_experience'] === 1) {
        $features[] = 'Experience Details';
    }

    if ((int)$plan['show_education'] === 1) {
        $features[] = 'Education Details';
    }

    if ((int)$plan['show_background'] === 1) {
        $features[] = 'Background Information';
    }

    if ((int)$plan['show_affiliations'] === 1) {
        $features[] = 'Affiliations';
    }

    if ((int)$plan['show_publications'] === 1) {
        $features[] = 'Publications';
    }

    if ((int)$plan['show_awards'] === 1) {
        $features[] = 'Awards';
    }

    if ((int)$plan['show_slogan'] === 1) {
        $features[] = 'Profile Slogan';
    }

    if ((int)$plan['show_phone'] === 1) {
        $features[] = 'Phone Display';
    }

    if ((int)$plan['searchable'] === 1) {
        $features[] = 'Directory Search Visibility';
    }

    $serviceLimit = trim((string)$plan['service_limit']);

    if ($serviceLimit !== '' && ctype_digit($serviceLimit) && (int)$serviceLimit > 0) {
        $features[] = 'Up to ' . (int)$serviceLimit . ' Service Listings';
    }

    if ((int)$plan['photo_limit'] > 0) {
        $features[] = 'Up to ' . number_format((int)$plan['photo_limit']) . ' Photos';
    }

    return $features;
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Explore the available membership plans.">
    <title>Membership Plans | <?= e($settings['site_name']) ?></title>
    <?php
    $websiteCssPath = __DIR__ . '/assets/css/website.css';
    $websiteCssVersion = is_file($websiteCssPath) ? filemtime($websiteCssPath) : time();
    ?>
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/public/assets/css/website.css?v=<?= (int)$websiteCssVersion ?>">
    <style>
        :root {
            --primary: <?= e($settings['primary_color']) ?>;
            --nav: <?= e($settings['nav_color']) ?>;
        }
    </style>
<link rel="icon" type="image/svg+xml" href="<?=e($config['base_url'])?>/assets/images/favicons/public-favicon.svg">
</head>
<body class="membership-page">
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
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                        <path d="m4 7 8 6 8-6"></path>
                    </svg>
                    Contact
                </a>
                <a href="<?= e($baseUrl) ?>/public/login.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5"></path>
                        <path d="M14 16l5-4-5-4M19 12H9"></path>
                    </svg>
                    Login
                </a>
                <a class="join-button" href="<?= e($baseUrl) ?>/public/membership.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"></circle>
                        <circle cx="12" cy="12" r="4"></circle>
                    </svg>
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

    <main class="membership-main">
        <div class="membership-banner" aria-hidden="true"></div>
        <section class="site-container membership-plan-list" id="plans">


            <?php if ($plans): ?>
                <?php foreach ($plans as $plan): ?>
                    <?php $features = $getPlanFeatures($plan); ?>
                    <article class="membership-plan-row">
                        <div class="membership-price-panel">
                            <h1><?= e($getPlanDisplayName($plan)) ?></h1>
                            <p>₹<?= e($formatPrice($plan['monthly_amount'])) ?>/month</p>
                            <p>₹<?= e($formatPrice($plan['yearly_amount'])) ?>/year</p>
                            <a class="membership-select-button" href="<?= e($baseUrl) ?>/public/signup.php?plan_id=<?= (int)$plan['subscription_id'] ?>">
                                SELECT PLAN
                            </a>
                        </div>

                        <div class="membership-plan-details">
                            <p class="membership-plan-description">
                                Membership benefits available with this plan are listed below.
                            </p>

                            <?php if ($features): ?>
                                <ul class="membership-feature-list">
                                    <?php foreach ($features as $feature): ?>
                                        <li><?= e($feature) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="membership-no-features">No additional profile features are configured for this plan.</p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="membership-empty-state">
                    No active membership plans are currently available.
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="public-footer">
        <div class="site-container footer-main">
            <div class="footer-about">
                <strong>About</strong>
                <p>
                    Finding members is easy with <?= e($settings['site_name']) ?>.
                    Easily connect with members in your area. For members, our website
                    is a powerful tool for attracting more customers.
                </p>
                <?php if ($settings['contact_email'] !== ''): ?>
                    <a class="footer-email" href="mailto:<?= e($settings['contact_email']) ?>" aria-label="Email us">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                            <path d="m4 7 8 6 8-6"></path>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>

            <div class="footer-column">
                <strong>Quick Links</strong>
                <a href="<?= e($baseUrl) ?>/public/index.php">Home</a>
                <a href="<?= e($baseUrl) ?>/public/search.php">Search Directory</a>
                <a href="<?= e($baseUrl) ?>/public/index.php#how-it-works">How It Works</a>
                <a href="<?= e($baseUrl) ?>/public/index.php#website-blog">Website Blog</a>
            </div>

            <div class="footer-column">
                <strong>For Members</strong>
                <a href="<?= e($baseUrl) ?>/public/login.php">Member Login</a>
                <a href="<?= e($baseUrl) ?>/public/login.php">Reset Password</a>
                <a href="<?= e($baseUrl) ?>/public/membership.php">Membership Plans</a>
            </div>

            <div class="footer-column footer-contact-column">
                <strong>Contact Us</strong>
                <?php if ($settings['contact_phone'] !== ''): ?>
                    <a href="tel:<?= e($settings['contact_phone']) ?>"><?= e($settings['contact_phone']) ?></a>
                <?php endif; ?>
                <?php if ($settings['contact_email'] !== ''): ?>
                    <a href="mailto:<?= e($settings['contact_email']) ?>"><?= e($settings['contact_email']) ?></a>
                <?php endif; ?>
            </div>

            <a class="footer-join-button" href="<?= e($baseUrl) ?>/public/membership.php">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <circle cx="12" cy="12" r="4"></circle>
                </svg>
                Members – Join Today
            </a>
        </div>

        <div class="footer-bottom">
            <div class="site-container">
                <span>&copy; <?= date('Y') ?> <?= e($settings['site_name']) ?>. All Rights Reserved.</span>
                <span>
                    <a href="#terms">Terms of Use</a>
                    <i>|</i>
                    <a href="#privacy">Privacy Policy</a>
                </span>
            </div>
        </div>
    </footer>
</body>
</html>
