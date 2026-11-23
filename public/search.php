<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/member_forms.php';
require __DIR__ . '/../includes/public_website.php';

$settings = getPublicWebsiteSettings($pdo);

$baseUrl = $config['base_url'];
$categoryId = filter_input(INPUT_GET, 'subcategory', FILTER_VALIDATE_INT)
    ?: (filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: 0);
$location = trim((string)($_GET['location'] ?? ''));
$specialization = trim((string)($_GET['subcategory'] ?? ''));
$profileId = filter_input(INPUT_GET, 'profile', FILTER_VALIDATE_INT) ?: 0;
$articleId = filter_input(INPUT_GET, 'article', FILTER_VALIDATE_INT) ?: 0;
$results = [];
$profile = null;
$article = null;

if ($profileId > 0 && tableExists($pdo, 'users_data')) {
    $profileQuery = $pdo->prepare(
        "SELECT user_id, first_name, last_name, company, city, state_ln,
                address1, zip_code, about_me, search_description,
                phone_number, website,
                (SELECT p.file FROM users_photo p WHERE p.user_id = users_data.user_id AND p.type = 'photo' ORDER BY p.photo_id DESC LIMIT 1) AS profile_photo,
                (SELECT p.original FROM users_photo p WHERE p.user_id = users_data.user_id AND p.type = 'photo' ORDER BY p.photo_id DESC LIMIT 1) AS profile_photo_original
         FROM users_data
         WHERE user_id = ? AND active = 2
         LIMIT 1"
    );

    $profileQuery->execute([$profileId]);
    $profile = $profileQuery->fetch() ?: null;

} elseif ($articleId > 0 && tableExists($pdo, 'data_posts')) {
    $articleQuery = $pdo->prepare(
        "SELECT post_title, post_caption, post_content, post_image,
                post_filename, post_date
         FROM data_posts
         WHERE post_id = ? AND post_status = 1
         LIMIT 1"
    );

    $articleQuery->execute([$articleId]);
    $article = $articleQuery->fetch() ?: null;
    
} elseif (tableExists($pdo, 'users_data')) {
    $conditions = ['active = 2'];
    $parameters = [];

    if ($categoryId > 0) {
        $conditions[] = 'profession_id = ?';
        $parameters[] = $categoryId;
    }

    if ($specialization !== '') {
        $conditions[] = 'position = ?';
        $parameters[] = $specialization;
    }

    if ($location !== '') {
        $conditions[] = '(city LIKE ? OR zip_code LIKE ? OR state_ln LIKE ? OR company LIKE ?)';
        $term = '%' . $location . '%';
        array_push($parameters, $term, $term, $term, $term);
    }

    $sql = "SELECT u.user_id, u.first_name, u.last_name, u.company, u.city, u.state_ln,
                   u.search_description, u.about_me, u.phone_number,
                   (SELECT p.file FROM users_photo p WHERE p.user_id = u.user_id AND p.type = 'photo' ORDER BY p.photo_id DESC LIMIT 1) AS profile_photo,
                (SELECT p.original FROM users_photo p WHERE p.user_id = u.user_id AND p.type = 'photo' ORDER BY p.photo_id DESC LIMIT 1) AS profile_photo_original
            FROM users_data u
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY featured DESC, user_id DESC
            LIMIT 50";

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    $results = $statement->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $profile ? e($profile['company'] ?: $profile['first_name']) : ($article ? e($article['post_title']) : 'Directory Search') ?> | <?= e($settings['site_name']) ?></title>
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/public/assets/css/website.css">
    <style>
        :root {
            --primary: <?= e($settings['primary_color']) ?>;
            --nav: <?= e($settings['nav_color']) ?>;
        }
    </style>
<link rel="icon" type="image/svg+xml" href="<?=e($config['base_url'])?>/assets/images/favicons/public-favicon.svg">
</head>
<body>
    <div class="top-contact-bar">
        <div class="site-container top-contact-inner">
            <a class="brand" href="<?= e($baseUrl) ?>/public/index.php">
                <?php if ($settings['logo_url'] !== ''): ?>
                    <img src="<?= e($settings['logo_url']) ?>" alt="<?= e($settings['site_name']) ?>">
                <?php else: ?>
                    <span class="brand-mark">♧</span>
                <?php endif; ?>
                <span class="brand-copy">
                    <strong><?= e($settings['site_name']) ?></strong>
                    <small><?= e($settings['tagline']) ?></small>
                </span>
            </a>

            <div class="header-actions">
                <?php if ($settings['contact_phone'] !== ''): ?>
                    <a href="tel:<?= e($settings['contact_phone']) ?>"><?= e($settings['contact_phone']) ?></a>
                <?php endif; ?>
                <a href="<?= e($baseUrl) ?>/public/contact.php">Contact</a>
                <a href="<?= e($baseUrl) ?>/public/login.php">Login</a>
                <a class="join-button" href="<?= e($baseUrl) ?>/public/login.php">Members - Join Today</a>
            </div>
        </div>
    </div>

    <nav class="public-nav" aria-label="Main navigation">
        <div class="site-container nav-inner">
            <a href="<?= e($baseUrl) ?>/public/index.php">Home</a>
            <a href="<?= e($baseUrl) ?>/public/index.php#directory-search">Search Directory</a>
            <a href="<?= e($baseUrl) ?>/public/index.php#how-it-works">How It Works</a>
            <a href="<?= e($baseUrl) ?>/public/index.php#latest-articles">Website Blog</a>
        </div>
    </nav>

    <main class="results-page">
        <div class="site-container">
            <?php if ($profile): ?>
                <div class="section-heading">
                    <span class="section-kicker">BUSINESS PROFILE</span>
                    <h1><?= e($profile['company'] ?: trim($profile['first_name'] . ' ' . $profile['last_name'])) ?></h1>
                    <p><?= e($profile['city']) ?><?= $profile['state_ln'] ? ', ' . e($profile['state_ln']) : '' ?></p>
                </div>

                <article class="listing-card profile-detail">
                    <?php if ($profile['profile_photo'] !== ''): ?>
                        <img class="listing-image" src="<?= e(memberPhotoUrl((string)$profile['profile_photo'], (string)($profile['profile_photo_original'] ?? ''))) ?>" alt="">
                    <?php endif; ?>
                    <div class="listing-content">
                        <p><?= e($profile['search_description'] ?: $profile['about_me']) ?></p>
                        <?php if ($profile['address1'] !== '' || $profile['zip_code'] !== ''): ?>
                            <p><?= e($profile['address1']) ?> <?= e($profile['zip_code']) ?></p>
                        <?php endif; ?>
                        <?php if ($profile['phone_number'] !== ''): ?>
                            <p><a href="tel:<?= e($profile['phone_number']) ?>">Call <?= e($profile['phone_number']) ?></a></p>
                        <?php endif; ?>
                        <?php if ($profile['website'] !== ''): ?>
                            <p><a href="<?= e($profile['website']) ?>" target="_blank" rel="noopener">Visit Website</a></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php elseif ($article): ?>
                <article class="article-detail">
                    <div class="section-heading">
                        <span class="section-kicker">FROM OUR BLOG</span>
                        <h1><?= e($article['post_title']) ?></h1>
                        <p><?= e($article['post_date']) ?></p>
                    </div>
                    <?php $articleImage = $article['post_image'] ?: $article['post_filename']; ?>
                    <?php if ($articleImage !== ''): ?>
                        <img class="article-detail-image" src="<?= e($articleImage) ?>" alt="<?= e($article['post_title']) ?>">
                    <?php endif; ?>
                    <div class="article-detail-content">
                        <?= nl2br(e(strip_tags($article['post_content'] ?: $article['post_caption']))) ?>
                    </div>
                </article>
            <?php else: ?>
                <div class="section-heading">
                    <span class="section-kicker">DIRECTORY</span>
                    <h1>Search Results</h1>
                    <p><?= $location !== '' ? 'Showing matches for ' . e($location) : 'Browse available directory listings.' ?></p>
                </div>

                <div class="listing-grid">
                    <?php foreach ($results as $listing): ?>
                        <article class="listing-card">
                            <?php if ($listing['profile_photo'] !== ''): ?>
                                <img class="listing-image" src="<?= e(memberPhotoUrl((string)$listing['profile_photo'], (string)($listing['profile_photo_original'] ?? ''))) ?>" alt="">
                            <?php else: ?>
                                <div class="listing-placeholder">♧</div>
                            <?php endif; ?>

                            <div class="listing-content">
                                <h3><?= e($listing['company'] ?: trim($listing['first_name'] . ' ' . $listing['last_name'])) ?></h3>
                                <p class="listing-location"><?= e($listing['city']) ?><?= $listing['state_ln'] ? ', ' . e($listing['state_ln']) : '' ?></p>
                                <p><?= e($listing['search_description'] ?: $listing['about_me']) ?></p>
                                <div class="listing-actions">
                                    <?php if ($listing['phone_number'] !== ''): ?>
                                        <a href="tel:<?= e($listing['phone_number']) ?>">Call Business</a>
                                    <?php endif; ?>
                                    <a class="details-link" href="<?= e($baseUrl) ?>/public/member.php?id=<?= (int)$listing['user_id'] ?>">View Details →</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <?php if (!$results): ?>
                        <p class="empty-message">No matching listings were found.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="public-footer">
        <div class="site-container footer-bottom">
            <div>
                <span>&copy; <?= date('Y') ?> <?= e($settings['site_name']) ?>. All rights reserved.</span>
                <a href="<?= e($baseUrl) ?>/public/index.php">Back to Home</a>
            </div>
        </div>
    </footer>
</body>
</html>
