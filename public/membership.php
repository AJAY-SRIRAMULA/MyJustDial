<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public_website.php';

$settings = getPublicWebsiteSettings($pdo);
$baseUrl = $config['base_url'];
$phoneNumber = $settings['contact_phone'] !== ''
    ? $settings['contact_phone']
    : '1-800-555-5555';

require_once __DIR__ . '/../includes/membership_plans.php';
$plans = [];
if (tableExists($pdo, 'subscription_types')) {
    $plans = $pdo->query("SELECT * FROM subscription_types WHERE sub_active = 1 AND subscription_type = 'member' AND profile_type IN ('free','paid') ORDER BY monthly_amount, yearly_amount, subscription_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($plans as &$plan) {
        $plan['meta'] = mpMeta($pdo, (int)$plan['subscription_id']);
    }
    unset($plan);
    $plans = array_values(array_filter($plans, static fn($plan) => ($plan['meta']['disallow_signups'] ?? '0') !== '1'));
}
$benefits = ['Directory search visibility'=>'searchable','Profile photo'=>'profile_photo','Maximum sub-level categories'=>'service_limit','Search priority (0 is highest)'=>'search_priority','Website link'=>'website_link','Social media links'=>'social_link'];
$formatPrice = static fn($amount) => number_format((float)$amount, 2, '.', ',');

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
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/assets/css/join-comparison.css?v=<?= (int)filemtime(__DIR__ . '/../assets/css/join-comparison.css') ?>">
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

    <main class="day10-join">
        <section class="day10-hero">
            <span>MEMBERSHIP PLANS</span>
            <h1>Your Business. Your Next Level.</h1>
            <p>Build your online presence and reach more customers.<br>Compare our membership options and choose the right plan for your business.</p>
        </section>
        <section class="site-container day10-comparison" id="plans">
            <?php if ($plans): ?>
                <div class="day10-scroll" tabindex="0" aria-label="Membership comparison">
                    <table class="day10-table">
                        <thead><tr><th scope="col" class="day10-label"><h2>Find your perfect plan</h2><p>Compare configured membership benefits</p></th>
                        <?php foreach ($plans as $i => $plan): ?>
                            <th scope="col"><div class="day10-plan day10-tone-<?= $i % 3 ?>">
                                <span class="day10-symbol" aria-hidden="true"><?= ['◇','✦','♛'][$i % 3] ?></span>
                                <h2><?= e($plan['subscription_name']) ?></h2>
                                <?php if ($plan['profile_type'] === 'free'): ?><strong>Free</strong><?php else: ?>
                                    <?php if (($plan['meta']['hide_monthly_amount'] ?? '0') !== '1'): ?><strong>₹<?= e($formatPrice($plan['monthly_amount'])) ?><small> / month</small></strong><?php endif; ?>
                                    <?php if (($plan['meta']['hide_yearly_amount'] ?? '0') !== '1'): ?><p>₹<?= e($formatPrice($plan['yearly_amount'])) ?> / year</p><?php endif; ?>
                                <?php endif; ?>
                            </div></th>
                        <?php endforeach; ?></tr></thead>
                        <tbody><?php foreach ($benefits as $label => $key): ?><tr><th scope="row"><?= e($label) ?></th>
                            <?php foreach ($plans as $plan): ?><td>
                                <?php if (in_array($key, ['service_limit','search_priority'], true)): ?><?= e((string)$plan[$key]) ?>
                                <?php elseif ((int)$plan[$key] === 1): ?><span class="day10-yes" aria-label="Included">✓</span>
                                <?php else: ?><span class="day10-no" aria-label="Not included">—</span><?php endif; ?>
                            </td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
                        <tfoot><tr><th scope="row">Ready to join?</th><?php foreach ($plans as $i => $plan): ?><td><a class="day10-button day10-tone-<?= $i % 3 ?>" href="<?= e($baseUrl) ?>/public/signup.php?plan_id=<?= (int)$plan['subscription_id'] ?>">Join Now<span class="day10-sr"> – <?= e($plan['subscription_name']) ?></span></a></td><?php endforeach; ?></tr></tfoot>
                    </table>
                </div>
                <p class="day10-note">Already a member? <a href="<?= e($baseUrl) ?>/public/login.php">Sign in to your account</a>.</p>
            <?php else: ?><p class="day10-note">No membership plans are currently available for registration.</p><?php endif; ?>
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
