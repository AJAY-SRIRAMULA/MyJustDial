<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/member_forms.php';
require __DIR__ . '/../includes/public_website.php';

$settings = getPublicWebsiteSettings($pdo);
$baseUrl = $config['base_url'];
require_once __DIR__ . '/../includes/directory_search.php';
require_once __DIR__ . '/../includes/membership_runtime.php';
$members = [];

if (tableExists($pdo, 'users_data')) {
    $memberQuery = $pdo->query(
        "SELECT u.user_id, u.first_name, u.last_name, u.company, u.city, u.state_ln,
                (SELECT p.file FROM users_photo p WHERE p.user_id = u.user_id AND p.type = 'photo' ORDER BY p.photo_id DESC LIMIT 1) AS profile_photo,
                (SELECT p.original FROM users_photo p WHERE p.user_id = u.user_id AND p.type = 'photo' ORDER BY p.photo_id DESC LIMIT 1) AS profile_photo_original
         FROM users_data u
         WHERE active = 2 AND " . membershipVisibilitySql() . "
         ORDER BY featured DESC, user_id DESC
         LIMIT 3"
    );
    $members = $memberQuery->fetchAll();
}

$heroImage = $settings['hero_image'] !== ''
    ? $settings['hero_image']
    : $baseUrl . '/public/assets/images/hero-lake.jpg';

$heroHeading = $settings['hero_heading'] !== ''
    ? $settings['hero_heading']
    : "Your Idea. Your Directory.\nStart Directories FOR ANY\nIndustry, Group or Location";

$heroDescription = $settings['hero_description'] !== ''
    ? $settings['hero_description']
    : 'See How Directory Search Works';

$phoneNumber = $settings['contact_phone'] !== ''
    ? $settings['contact_phone']
    : '1-800-555-5555';

$memberProfileUrl = static function (array $member) use ($baseUrl): string {
    return $baseUrl . '/public/member.php?id=' . (int)$member['user_id'];
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($heroDescription) ?>">
    <title><?= e($settings['site_name']) ?></title>
    <?php
    $websiteCssPath = __DIR__ . '/assets/css/website.css';
    $websiteCssVersion = is_file($websiteCssPath) ? filemtime($websiteCssPath) : time();
?>
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/public/assets/css/website.css?v=<?= (int)$websiteCssVersion ?>">
    <style>
        :root {
            --primary: <?= e($settings['primary_color']) ?>;
            --nav: <?= e($settings['nav_color']) ?>;
            --hero-image: url("<?= e($heroImage) ?>");
        }
    </style>
<link rel="icon" type="image/svg+xml" href="<?=e($config['base_url'])?>/assets/images/favicons/public-favicon.svg">
</head>
<body class="homepage">
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
            <a class="active" href="<?= e($baseUrl) ?>/public/index.php">Home</a>
            <a href="search.php">Search Directory</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#website-blog">Website Blog</a>
        </div>
    </nav>

    <main>
        <section class="hero">
            <div class="site-container hero-inner">
                <div class="hero-copy">
                    <h1><?= nl2br(e($heroHeading)) ?></h1>
                    <p class="hero-description"><?= e($heroDescription) ?></p>
                </div>

                <form class="search-form" id="directory-search" action="<?= e($baseUrl) ?>/public/search.php" method="get">
                    <label>
                        <span>What do you need?</span>
                        <input type="search" name="q" placeholder="Name or Keyword">
                    </label>
                    <label>
                        <span>Category:</span>
                        <?= directoryCategorySelect($pdo, []) ?>
                    </label>

                    <label class="location-field">
                        <span>Search by location:</span>
                        <span class="location-input">
                            <svg class="location-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 3 10 14M21 3l-7 18-4-7-7-4z"></path></svg>
                            <input type="text" name="location" placeholder="City or Post Code" autocomplete="postal-code">
                        </span>
                    </label>

                    <button type="submit">Search Now</button>
                </form>
            </div>
        </section>

        <section class="feature-strip" id="how-it-works">
            <div class="site-container feature-grid">
                <a class="feature-item" href="#directory-search">
                    <span class="feature-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg></span>
                    <strong>Search Members</strong>
                    <small>Browse To Find Members</small>
                </a>
                <a class="feature-item" href="#contact">
                    <span class="feature-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5h16v10H11l-5 3v-3H4z"></path><path d="M8 9h8M8 12h5"></path></svg></span>
                    <strong>Contact Members</strong>
                    <small>Connect with Members You Like</small>
                </a>
                <a class="feature-item" href="#newest-members">
                    <span class="feature-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"></rect><path d="m7 12 3.2 3.2L18 7.5"></path></svg></span>
                    <strong>Review Members</strong>
                    <small>Share Your Experience Online</small>
                </a>
            </div>
        </section>

        <section class="homepage-content">
            <div class="site-container">
                <section class="member-section" id="newest-members">
                    <h2 class="homepage-section-title">Our Newest Members</h2>
                    <div class="member-grid">
                        <?php if (!$members): ?><p class="member-empty-state">No member listings are available yet.</p><?php endif; ?>
                        <?php foreach ($members as $member): ?>
                            <article class="member-card">
                                <div class="member-avatar">
                                    <?php if (!empty($member['profile_photo'])): ?>
                                        <img src="<?= e(memberPhotoUrl((string)$member['profile_photo'], (string)($member['profile_photo_original'] ?? ''))) ?>" alt="<?= e(trim($member['first_name'] . ' ' . $member['last_name'])) ?>">
                                    <?php else: ?>
                                        <svg class="avatar-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4.2 3.4-7 8-7s8 2.8 8 7"></path></svg>
                                    <?php endif; ?>
                                </div>
                                <div class="member-info">
                                    <h3><?= e($member['company'] ?: trim($member['first_name'] . ' ' . $member['last_name'])) ?></h3>
                                    <small>Located in</small>
                                    <p><?= e($member['city']) ?><?= $member['state_ln'] !== '' ? ', ' . e($member['state_ln']) : '' ?></p>
                                    <a href="<?= e($memberProfileUrl($member)) ?>">View Listing</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="promotion-card">
                    <div class="promotion-copy">
                        <h2>Your Smile Deserves Expert Care</h2>
                        <p>Find trusted dentists who put your comfort, health, and smile first. Whether you need a routine check-up, cosmetic treatment, or specialized dental care, connect with experienced professionals who are committed to providing personalized treatment in a comfortable environment.</p>
                    </div>
                    <img src="<?= e($baseUrl) ?>/public/assets/images/promo-dental-canyon.jpg" alt="Warm sandstone canyon landscape">
                </section>

                <section class="member-section second-member-section">
                    <h2 class="homepage-section-title">Our Newest Members</h2>
                    <div class="member-grid">
                        <?php if (!$members): ?><p class="member-empty-state">No member listings are available yet.</p><?php endif; ?>
                        <?php foreach ($members as $member): ?>
                            <article class="member-card">
                                <div class="member-avatar">
                                    <?php if (!empty($member['profile_photo'])): ?>
                                        <img src="<?= e(memberPhotoUrl((string)$member['profile_photo'], (string)($member['profile_photo_original'] ?? ''))) ?>" alt="<?= e(trim($member['first_name'] . ' ' . $member['last_name'])) ?>">
                                    <?php else: ?>
                                        <svg class="avatar-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4.2 3.4-7 8-7s8 2.8 8 7"></path></svg>
                                    <?php endif; ?>
                                </div>
                                <div class="member-info">
                                    <h3><?= e($member['company'] ?: trim($member['first_name'] . ' ' . $member['last_name'])) ?></h3>
                                    <small>Located in</small>
                                    <p><?= e($member['city']) ?><?= $member['state_ln'] !== '' ? ', ' . e($member['state_ln']) : '' ?></p>
                                    <a href="<?= e($memberProfileUrl($member)) ?>">View Listing</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="homepage-cta-grid" id="join-today">
                    <article class="homepage-cta-card">
                        <h2>Members – Join Today</h2>
                        <p>Finding professionals is easy with My New Website. Search our website to instantly connect with professionals. For professionals, our website works as a powerful tool for attracting more clients.</p>
                        <a href="<?= e($baseUrl) ?>/public/membership.php">Get Listed Today</a>
                    </article>
                    <article class="homepage-cta-card" id="contact">
                        <h2>About <?= e($settings['site_name']) ?></h2>
                        <p>Finding members is easy with <?= e($settings['site_name']) ?>. Easily connect with members in your area. For members, our website is a powerful tool for attracting more customers.</p>
                        <a href="#contact">Get Listed Today</a>
                    </article>
                </section>
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
                <a href="#how-it-works">How It Works</a>
                <a href="<?= e($baseUrl) ?>/public/contact.php">Contact Us</a>
            </div>

            <div class="footer-column">
                <strong>Search</strong>
                <a href="#directory-search">Browse Directory</a>
                <a href="#directory-search">Search by Category</a>
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
