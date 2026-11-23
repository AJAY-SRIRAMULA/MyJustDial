<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/member_forms.php';
$memberId = (int)($_GET['id'] ?? 0);
$statement = $pdo->prepare(
    'SELECT u.first_name, u.last_name, u.company, u.phone_number, u.city,
            u.state_ln, u.country_ln, u.website, u.about_me, u.quote,
            u.experience, u.credentials, u.awards, u.filename, u.working_days,
            s.subscription_name
     FROM users_data u
     LEFT JOIN subscription_types s ON s.subscription_id = u.subscription_id
     WHERE u.user_id = ? AND u.active = 2 LIMIT 1'
);
$statement->execute([$memberId]);
$member = $statement->fetch();
if (!$member) {
    http_response_code(404);
    exit('This public member listing is unavailable.');
}
$websiteUrl = rawurldecode((string)$member['website']);
if (!preg_match('~^https?://~i', $websiteUrl)) $websiteUrl = '';
$photo = $pdo->prepare("SELECT file, original FROM users_photo WHERE user_id = ? AND type = 'photo' ORDER BY photo_id DESC LIMIT 1");
$photo->execute([$memberId]);
$photoRecord = $photo->fetch(PDO::FETCH_ASSOC) ?: [];
$photoPath = memberPhotoUrl((string)($photoRecord['file'] ?? ''), (string)($photoRecord['original'] ?? ''));
$logo = $pdo->prepare("SELECT file, original FROM users_photo WHERE user_id = ? AND type = 'logo' ORDER BY photo_id DESC LIMIT 1");
$logo->execute([$memberId]);
$logoRecord = $logo->fetch(PDO::FETCH_ASSOC) ?: [];
$logoPath = memberPhotoUrl((string)($logoRecord['file'] ?? ''), (string)($logoRecord['original'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(trim($member['first_name'] . ' ' . $member['last_name'])) ?> | Brilliant Directories Demo</title>
    <link rel="stylesheet" href="<?= e($config['base_url']) ?>/public/assets/css/website.css">
    <link rel="stylesheet" href="<?= e($config['base_url']) ?>/public/assets/css/member-account.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="icon" type="image/svg+xml" href="<?=e($config['base_url'])?>/assets/images/favicons/public-favicon.svg">
</head>
<body class="member-account-body">
    <header class="member-site-header">
        <a class="member-brand" href="<?= e($config['base_url']) ?>/public/index.php"><span class="member-brand-icon"><i class="fa fa-user-o"></i></span><span><strong>Brilliant Directories Demo</strong><small>The All-In-One Business Search Directory Theme</small></span></a>
        <a class="member-welcome" href="<?= e($config['base_url']) ?>/public/login.php">Member Login</a>
    </header>
    <nav class="member-top-nav"><a href="<?= e($config['base_url']) ?>/public/index.php">Home</a><a href="<?= e($config['base_url']) ?>/public/search.php">Search Directory</a><a href="<?= e($config['base_url']) ?>/public/index.php#how-it-works">How It Works</a><a href="<?= e($config['base_url']) ?>/public/index.php#blog">Website Blog</a></nav>
    <main class="public-member-profile">
        <section class="public-member-hero">
            <?php if ($logoPath !== ''): ?><img class="public-member-logo" src="<?= e($logoPath) ?>" alt="Member logo"><?php endif; ?>
            <?php if ($photoPath !== ''): ?><img class="public-member-photo" src="<?= e($photoPath) ?>" alt="Profile photo"><?php else: ?><div class="public-member-photo public-member-placeholder">Profile</div><?php endif; ?>
            <div><h1><?= e(trim($member['first_name'] . ' ' . $member['last_name'])) ?></h1><p><?= e($member['company']) ?></p><p><?= e(implode(', ', array_filter([$member['city'], $member['state_ln'], $member['country_ln']]))) ?></p></div>
        </section>
        <?php if ($member['quote']): ?><blockquote><?= e($member['quote']) ?></blockquote><?php endif; ?>
        <?php if ($member['about_me']): ?><section class="public-member-section"><h2>About</h2><div><?= safeMemberRichText((string)$member['about_me']) ?></div></section><?php endif; ?>
        <section class="public-member-section"><h2>Contact Details</h2><?php if ($member['phone_number']): ?><p>Phone: <?= e($member['phone_number']) ?></p><?php endif; ?><?php if ($websiteUrl !== ''): ?><p>Website: <a href="<?= e($websiteUrl) ?>" rel="noopener" target="_blank"><?= e($websiteUrl) ?></a></p><?php endif; ?></section>
        <?php if (!empty($member['working_days'])): ?><section class="public-member-section"><h2>Hours of Operation</h2><p><?= nl2br(e($member['working_days'])) ?></p></section><?php endif; ?>
        <?php if ($member['credentials']): ?><section class="public-member-section"><h2>Credentials</h2><p><?= nl2br(e($member['credentials'])) ?></p></section><?php endif; ?>
        <?php if ($member['awards']): ?><section class="public-member-section"><h2>Honors &amp; Awards</h2><p><?= nl2br(e($member['awards'])) ?></p></section><?php endif; ?>
    </main>
</body>
</html>
