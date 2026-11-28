<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/member_forms.php';
require __DIR__ . '/../includes/public_website.php';
require_once __DIR__ . '/../includes/membership_runtime.php';
$memberId = (int)($_GET['id'] ?? 0);
$statement = $pdo->prepare(
    'SELECT u.profession_id, u.first_name, u.last_name, u.company, u.phone_number, u.city,
            u.state_ln, u.country_ln, u.position, u.zip_code, u.website, u.about_me, u.quote,
            u.experience, u.credentials, u.awards, u.filename, u.working_days,
            s.subscription_name, s.show_phone, s.website_link, s.searchable, s.nofollow_links
     FROM users_data u
     LEFT JOIN subscription_types s ON s.subscription_id = u.subscription_id
     WHERE u.user_id = ? AND u.active = 2 LIMIT 1'
);
$statement->execute([$memberId]);
$member = $statement->fetch();
if (!$member || ($member['searchable'] !== null && (int)$member['searchable'] === 0)) {
    http_response_code(404);
    exit('This public member listing is unavailable.');
}
if ($member['show_phone'] !== null && (int)$member['show_phone'] === 0) $member['phone_number'] = '';
if ($member['website_link'] !== null && (int)$member['website_link'] === 0) $member['website'] = '';
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

$settings = getPublicWebsiteSettings($pdo);
$baseUrl = $config['base_url'];
$name = trim($member['first_name'].' '.$member['last_name']);
$categoryQuery = $pdo->prepare('SELECT name FROM list_professions WHERE profession_id = ?');
$categoryQuery->execute([$member['profession_id']]);
$category = (string)$categoryQuery->fetchColumn();
$listing = $baseUrl.'/public/member.php?id='.$memberId;
$message = $listing.'&view=message';
$isReview = ($_GET['view'] ?? '') === 'review';
$isMessage = ($_GET['view'] ?? '') === 'message';
$review = $listing.'&view=review';
require_once __DIR__.'/../includes/directory_forms.php';
$shortcode = $isReview ? 'listing_write_review' : 'bootstrap_get_match';
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['public_form_shortcode']??'') === $shortcode) {
    $result = processPublicFormSubmission($pdo,$shortcode,$memberId);
}
function profileForm(PDO $pdo,string $shortcode,?array $result): string {
    $notice = $result ? '<p role="status" class="profile-result '.($result['ok']?'success':'error').'">'.e($result['message']).'</p>' : '';
    return $notice.renderPublicForm($pdo,$shortcode,csrfToken());
}
$avatar = $photoPath ?: ($logoPath ?: $baseUrl.'/assets/images/directory-avatar.svg');
$categoryUrl = $baseUrl.'/public/search.php?category='.(int)$member['profession_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($name) ?> | <?= e($settings['site_name']) ?></title>
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/public/assets/css/website.css">
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/assets/css/category-directory.css?v=<?= filemtime(__DIR__.'/../assets/css/category-directory.css') ?>">
    <style>
        :root {
            --primary: <?= e($settings['primary_color']) ?>;
            --nav: <?= e($settings['nav_color']) ?>;
        }
    </style>
<link rel="icon" type="image/svg+xml" href="<?=e($config['base_url'])?>/assets/images/favicons/public-favicon.svg">
<link rel="stylesheet" href="<?= e($baseUrl) ?>/assets/css/member-profile-reference.css">
</head>
<body class="directory-page profile-page">
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
                <a class="header-phone" href="tel:<?= e($settings['contact_phone']) ?>"><?= e($settings['contact_phone']) ?></a>
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
            <a href="<?= e($baseUrl) ?>/public/index.php#directory-search">Search Directory</a>
            <a href="<?= e($baseUrl) ?>/public/index.php#how-it-works">How It Works</a>
            <a href="<?= e($baseUrl) ?>/public/index.php#latest-articles">Website Blog</a>
        </div>
    </nav>

<main class="site-container profile-main">
<div class="profile-crumb"><a href="<?= e($baseUrl) ?>/public/index.php">Home</a> / <?= e($member['country_ln']) ?> / <a href="<?= e($categoryUrl) ?>"><?= e($category) ?></a> / <a href="<?= e($listing) ?>"><?= e($name) ?></a><?= $isReview ? ' / Write a Review' : ($isMessage ? ' / Send a Message' : '') ?></div>
<?php if ($isMessage || $isReview): ?>
<div class="profile-message-layout"><aside><section class="profile-box profile-back"><img class="profile-avatar" src="<?= e($avatar) ?>" alt=""><a class="profile-dark" href="<?= e($listing) ?>">Back to Listing</a></section><section class="profile-box profile-share"><h2>♧ Share This Page</h2><div><a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($listing)) ?>" target="_blank" rel="noopener">Facebook</a><a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e(rawurlencode($listing)) ?>" target="_blank" rel="noopener">LinkedIn</a><a href="https://twitter.com/intent/tweet?url=<?= e(rawurlencode($listing)) ?>" target="_blank" rel="noopener">X</a><button type="button" onclick="navigator.clipboard.writeText(location.href)">↗</button></div></section></aside><section class="profile-box profile-message-panel <?= $isReview ? 'profile-review-panel' : 'profile-lead-panel' ?>"><h2><?= $isReview ? 'Write a Review for ' : 'Contact ' ?><?= e($name) ?></h2><?= profileForm($pdo,$shortcode,$result) ?></section></div>
<?php else: ?>
<div class="profile-listing-layout"><section><div class="profile-hero"><img class="profile-avatar" src="<?= e($avatar) ?>" alt=""><div class="profile-identity"><h1><?= e($name) ?></h1><p><?= e($category) ?><br><?= e(implode(' at ',array_filter([$member['position'],$member['company']]))) ?><br><?= e(implode(', ',array_filter([$member['city'],$member['state_ln'],$member['zip_code'],$member['country_ln']]))) ?></p><div class="profile-actions"><a class="profile-dark profile-review-action" href="<?= e($review) ?>">✎ Write a Review</a><a class="profile-blue" href="<?= e($message) ?>">♧ Send Message</a><?php if ($member['phone_number']): ?><button type="button" class="profile-phone" data-phone="<?= e($member['phone_number']) ?>">☎ See Phone Number</button><?php endif; ?></div></div></div><div class="profile-connection"><strong>MAKE A CONNECTION</strong> <?= e($name) ?> is accepting messages: <a href="<?= e($message) ?>">Send Message</a></div>
        <?php if ($member['quote']): ?><blockquote><?= e($member['quote']) ?></blockquote><?php endif; ?>
        <?php if ($member['about_me']): ?><section class="public-member-section"><h2>About</h2><div><?= safeMemberRichText((string)$member['about_me']) ?></div></section><?php endif; ?>
        <section class="public-member-section"><h2>Contact Details</h2><?php if ($member['phone_number']): ?><p>Phone: <?= e($member['phone_number']) ?></p><?php endif; ?><?php if ($websiteUrl !== ''): ?><p>Website: <a href="<?= e($websiteUrl) ?>" rel="noopener" target="_blank"><?= e($websiteUrl) ?></a></p><?php endif; ?></section>
        <?php if (!empty($member['working_days'])): ?><section class="public-member-section"><h2>Hours of Operation</h2><p><?= nl2br(e($member['working_days'])) ?></p></section><?php endif; ?>
        <?php if ($member['credentials']): ?><section class="public-member-section"><h2>Credentials</h2><p><?= nl2br(e($member['credentials'])) ?></p></section><?php endif; ?>
        <?php if ($member['awards']): ?><section class="public-member-section"><h2>Honors &amp; Awards</h2><p><?= nl2br(e($member['awards'])) ?></p></section><?php endif; ?>
</section><aside><section class="profile-box profile-lead-panel"><h2>Contact <?= e($name) ?></h2><?= profileForm($pdo,$shortcode,$result) ?></section><section class="profile-box profile-share"><h2>♧ Share This Page</h2><div><a href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($listing)) ?>" target="_blank" rel="noopener">Facebook</a><a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e(rawurlencode($listing)) ?>" target="_blank" rel="noopener">LinkedIn</a><a href="https://twitter.com/intent/tweet?url=<?= e(rawurlencode($listing)) ?>" target="_blank" rel="noopener">X</a><button type="button" onclick="navigator.clipboard.writeText(location.href)">↗</button></div></section><section class="profile-box"><h2>Related Searches</h2><p><a href="<?= e($categoryUrl) ?>">All <?= e($category) ?></a></p><p><a href="<?= e($categoryUrl.'&location='.rawurlencode($member['country_ln'])) ?>"><?= e($category.' in '.$member['country_ln']) ?></a></p></section></aside></div>
<?php endif; ?>
</main>
    <footer class="public-footer">
        <div class="site-container footer-main">
            <div class="footer-about">
                <strong>About</strong>
                <p>Finding members is easy with <?= e($settings['site_name']) ?>. Easily connect with members in your area. For members, our website is a powerful tool for attracting more customers.</p>
                <?php if ($settings['contact_email'] !== ''): ?>
                    <a class="footer-email" href="mailto:<?= e($settings['contact_email']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg></a>
                <?php else: ?>
                    <a class="footer-email" href="<?= e($baseUrl) ?>/public/contact.php"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg></a>
                <?php endif; ?>
            </div>

            <div class="footer-column">
                <strong>Website</strong>
                <a href="<?= e($baseUrl) ?>/public/index.php#how-it-works">How It Works</a>
                <a href="<?= e($baseUrl) ?>/public/contact.php">Contact Us</a>
            </div>

            <div class="footer-column">
                <strong>Search</strong>
                <a href="<?= e($baseUrl) ?>/public/index.php#directory-search">Browse Directory</a>
                <a href="<?= e($baseUrl) ?>/public/index.php#directory-search">Search by Category</a>
            </div>

            <div class="footer-column">
                <strong>Support</strong>
                <a href="<?= e($baseUrl) ?>/public/login.php">Member Login</a>
                <a href="<?= e($baseUrl) ?>/public/contact.php?topic=password-reset">Reset Password</a>
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
<script>
document.querySelectorAll('.profile-phone').forEach(b=>b.addEventListener('click',()=>{b.textContent=b.dataset.phone}));
document.querySelectorAll('.profile-review-panel textarea').forEach(t=>{const c=document.createElement('div');c.className='review-counter';t.after(c);const update=()=>{c.textContent='Words : '+(t.value.trim()?t.value.trim().split(/\s+/).length:0)+'     Characters : '+t.value.length};t.addEventListener('input',update);update()});
document.querySelectorAll('.profile-review-panel form').forEach(form=>{const rows=[...form.querySelectorAll('.profile-rating-row')]; if(!rows.length)return; const header=document.createElement('div');header.className='profile-rating-header';header.innerHTML='<strong>Rate Your Experience (5 is Best)</strong><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span>';rows[0].before(header); rows.forEach(row=>{const inputs=[...row.querySelectorAll('input[type=radio]')];const paint=()=>{const selected=Number(inputs.find(i=>i.checked)?.value||0);inputs.forEach(i=>i.parentElement.classList.toggle('rated',Number(i.value)<=selected));};inputs.forEach(i=>i.addEventListener('change',paint));paint();});});
</script></body>
</html>
