<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/member_forms.php';
require __DIR__ . '/../includes/public_website.php';

require_once __DIR__.'/../includes/directory_forms.php';
ensureDirectoryForms($pdo);
ensureDirectoryWidgets($pdo);
$directoryNotice='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $code=$_POST['public_form_shortcode']??'';
 if($code==='newsletter_modal_signup'){$response=processPublicFormSubmission($pdo,$code);$directoryNotice=$response['message'];}
 if($code==='find_members' && hash_equals(csrfToken(),(string)($_POST['csrf_token']??''))){$_GET['q']=(string)($_POST['fields']['q']??'');$_GET['location']=(string)($_POST['fields']['location']??''); $_GET['page']=1; header('Location: ?'.http_build_query($_GET)); exit;}
}
$settings = getPublicWebsiteSettings($pdo);

$baseUrl = $config['base_url'];
$categoryId = (int)($_GET['category']??0);
$serviceId=(int)($_GET['service']??0);
$keyword=trim((string)($_GET['q']??''));
$categoryName='';
if($categoryId||$serviceId){$cq=$pdo->prepare($serviceId?'SELECT name FROM list_services WHERE service_id=?':'SELECT name FROM list_professions WHERE profession_id=?');$cq->execute([$serviceId?:$categoryId]);$categoryName=(string)$cq->fetchColumn();}
$location = trim((string)($_GET['location'] ?? ''));
$specialization = trim((string)($_GET['subcategory'] ?? ''));
$profileId = filter_input(INPUT_GET, 'profile', FILTER_VALIDATE_INT) ?: 0;
$articleId = filter_input(INPUT_GET, 'article', FILTER_VALIDATE_INT) ?: 0;
$results = [];
$sortOptions = ['recent'=>'u.featured DESC, u.user_id DESC','name_asc'=>'u.first_name ASC,u.last_name ASC,u.user_id ASC','name_desc'=>'u.first_name DESC,u.last_name DESC,u.user_id DESC'];
$sort = (string)($_GET['sort'] ?? 'recent');
$page = max(1,(int)($_GET['page'] ?? 1));
$total = 0;
$view = ($_GET['view'] ?? 'list') === 'grid' ? 'grid' : 'list';
function directoryLink(array $changes): string { return '?'.http_build_query(array_merge($_GET,$changes)); }
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

    if ($serviceId > 0) { $conditions[]='EXISTS (SELECT 1 FROM rel_services r JOIN list_services s ON s.service_id=r.service_id WHERE r.user_id=u.user_id AND (s.service_id=? OR s.master_id=?))'; array_push($parameters,$serviceId,$serviceId); }
    if ($keyword !== '') { $conditions[]='(u.first_name LIKE ? OR u.last_name LIKE ? OR u.company LIKE ? OR u.search_description LIKE ? OR EXISTS (SELECT 1 FROM list_professions p WHERE p.profession_id=u.profession_id AND (p.name LIKE ? OR p.keywords LIKE ?)))'; array_push($parameters,...array_fill(0,6,"%$keyword%")); }
    if ($specialization !== '') {
        $conditions[] = 'position = ?';
        $parameters[] = $specialization;
    }

    if ($location !== '') {
        $conditions[] = '(city LIKE ? OR zip_code LIKE ? OR state_ln LIKE ? OR company LIKE ?)';
        $term = '%' . $location . '%';
        array_push($parameters, $term, $term, $term, $term);
    }

    $count = $pdo->prepare("SELECT COUNT(*) FROM users_data u WHERE ".implode(" AND ",$conditions));
    $count->execute($parameters);
    $total = (int)$count->fetchColumn();
    $page = min($page,max(1,(int)ceil($total/12)));
    $offset = ($page-1)*12;
    $sql = "SELECT u.user_id, u.first_name, u.last_name, u.company, u.city, u.state_ln,
                   u.search_description, u.about_me, u.phone_number, u.zip_code, u.country_ln,
                   (SELECT p.file FROM users_photo p WHERE p.user_id = u.user_id AND p.type = 'photo' ORDER BY p.photo_id DESC LIMIT 1) AS profile_photo,
                (SELECT p.original FROM users_photo p WHERE p.user_id = u.user_id AND p.type = 'photo' ORDER BY p.photo_id DESC LIMIT 1) AS profile_photo_original
            FROM users_data u
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY " . ($sortOptions[$sort] ?? $sortOptions['recent']) . " LIMIT 12 OFFSET " . $offset;

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
    <link rel="stylesheet" href="<?= e($baseUrl) ?>/assets/css/category-directory.css?v=<?= filemtime(__DIR__.'/../assets/css/category-directory.css') ?>">
    <style>
        :root {
            --primary: <?= e($settings['primary_color']) ?>;
            --nav: <?= e($settings['nav_color']) ?>;
        }
    </style>
<link rel="icon" type="image/svg+xml" href="<?=e($config['base_url'])?>/assets/images/favicons/public-favicon.svg">
</head>
<body class="<?= !$profile && !$article ? 'directory-page' : '' ?>">
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
                <div class="category-directory">
                    <aside><?= renderDirectoryWidgets($pdo) ?></aside>
                    <div class="directory-main">
                        <div class="directory-tools">
                            <span>Showing <?= $total ? ($page-1)*12+1 : 0 ?> - <?= min($page*12,$total) ?> of <?= $total ?> Results</span>
                            <select aria-label="Sort results" onchange="location.href=this.value">
                                <option value="<?= e(directoryLink(['sort'=>'recent','page'=>1])) ?>" <?= $sort==='recent'?'selected':'' ?>>Sort Results</option>
                                <?php foreach (['name_asc'=>'Name A-Z','name_desc'=>'Name Z-A'] as $key=>$label): ?>
                                    <option value="<?= e(directoryLink(['sort'=>$key,'page'=>1])) ?>" <?= $sort===$key?'selected':'' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <a class="view-toggle <?= $view==='list'?'selected':'' ?>" aria-label="List view" aria-pressed="<?= $view==='list'?'true':'false' ?>" href="<?= e(directoryLink(['view'=>'list'])) ?>"><svg viewBox="0 0 24 24"><path d="M8 5h13M8 12h13M8 19h13M3 5h1M3 12h1M3 19h1"/></svg></a>
                            <a class="view-toggle <?= $view==='grid'?'selected':'' ?>" aria-label="Grid view" aria-pressed="<?= $view==='grid'?'true':'false' ?>" href="<?= e(directoryLink(['view'=>'grid'])) ?>"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></a>
                        </div>
                        <h1><?= e($categoryName ? $categoryName.' Directory' : 'Search Results') ?></h1>
                        <div class="directory-results <?= $view==='list'?'directory-list':'directory-grid' ?>">
                            <?php foreach ($results as $listing):
                                $id = (int)$listing['user_id'];
                                $name = trim($listing['first_name'].' '.$listing['last_name']) ?: $listing['company'];
                                $address = implode(', ',array_filter([$listing['city'],$listing['state_ln'],$listing['zip_code'],$listing['country_ln']]));
                                $avatar = $baseUrl.'/assets/images/directory-avatar.svg';
                                $photo = memberPhotoUrl((string)$listing['profile_photo'],(string)$listing['profile_photo_original']);
                                $profileUrl = $baseUrl.'/public/member.php?id='.$id;
                            ?>
                                <article class="directory-member">
                                    <a class="member-avatar" href="<?= e($profileUrl) ?>"><img src="<?= e($photo ?: $avatar) ?>" data-fallback="<?= e($avatar) ?>" onerror="this.onerror=null;this.src=this.dataset.fallback" alt=""></a>
                                    <div class="member-info">
                                        <button class="member-save" type="button" data-member="<?= $id ?>" aria-pressed="false">▮ SAVE</button>
                                        <h3><a href="<?= e($profileUrl) ?>"><?= e($name) ?></a></h3>
                                        <?php if ($listing['company'] && $listing['company']!==$name): ?><span class="member-company"><?= e($listing['company']) ?></span><?php endif; ?>
                                        <?php if ($address): ?><p class="member-location"><svg viewBox="0 0 24 24"><path d="M12 22S5 13 5 8a7 7 0 1 1 14 0c0 5-7 14-7 14Z"/><circle cx="12" cy="8" r="2.5" fill="white"/></svg><?= e($address) ?></p><?php endif; ?>
                                    </div>
                                    <div class="member-buttons">
                                        <a href="<?= e($profileUrl) ?>">View Listing</a>
                                        <a class="message-button" href="<?= e($baseUrl) ?>/public/member.php?id=<?= $id ?>&amp;view=message">Send Message</a>
                                        <?php if ($listing['phone_number']): ?><button class="phone-button" type="button" data-phone="<?= e($listing['phone_number']) ?>">☎ See Phone Number</button><?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                            <?php if (!$results): ?><p>No matching listings were found.</p><?php endif; ?>
                        </div>
                        <nav class="directory-pagination" aria-label="Results pages">
                            <?php if ($total>12): for ($i=1;$i<=ceil($total/12);$i++): ?><a href="<?= e(directoryLink(['page'=>$i])) ?>" <?= $i===$page?'aria-current="page"':'' ?>><?= $i ?></a><?php endfor; endif; ?>
                        </nav>
                        <section class="directory-promo <?= $view==='list'?'promo-list':'' ?>" aria-label="Directory promotions">
                            <?php
                            $promotions = [
                                ['Become a Featured Vendor', 'Upgrade to Featured', 'membership.php', 'star'],
                                ['Want More Wedding Leads?', 'View Membership Plans', 'membership.php', 'diamond'],
                                ['Promote Your Business', 'Advertise With Us', 'membership.php', 'megaphone'],
                                ['Planning Your Wedding?', 'Explore More Categories', 'index.php#directory-search', 'heart']
                            ];
                            $promoIcons = [
                                'star' => '<path fill="#efa94c" d="m12 1 3.4 7 7.6 1.1-5.5 5.4 1.3 7.6-6.8-3.6-6.8 3.6 1.3-7.6L1 9.1 8.6 8z"/>',
                                'diamond' => '<path fill="none" stroke="#23517e" stroke-width="1.6" stroke-linejoin="round" d="M5 3h14l4 6-11 13L1 9zM1 9h22M8 3 5 9l7 13 7-13-3-6"/>',
                                'megaphone' => '<path fill="none" stroke="#d84e4b" stroke-width="2" stroke-linejoin="round" d="M9 7h3l9-5v16l-9-5H9z"/><path fill="#d84e4b" d="M3 7h7v6H8l2 8H6l-3-8H2a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2z"/>',
                                'heart' => '<path fill="#d84e4b" d="M12 22 2 12C-5 3 6-3 12 5c6-8 17-2 10 7z"/>'
                            ];
                            ?>
                            <?php foreach ($promotions as $index => $promotion): ?>
                                <div class="promo-slide" <?= $index !== 0 ? 'hidden' : '' ?>>
                                    <div class="promo-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= $promoIcons[$promotion[3]] ?></svg></div>
                                    <div class="promo-copy"><h2><?= e($promotion[0]) ?></h2></div>
                                    <a href="<?= e($baseUrl.'/public/'.$promotion[2]) ?>"><?= e($promotion[1]) ?></a>
                                </div>
                            <?php endforeach; ?>
                            <div class="promo-dots" role="group" aria-label="Choose promotion">
                                <?php foreach ($promotions as $index => $promotion): ?>
                                    <button type="button" aria-label="<?= e($promotion[0]) ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>" data-slide="<?= $index ?>"></button>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </div>
                </div>
                <script>
                    document.querySelectorAll('.directory-promo').forEach(carousel => {
                        const slides = Array.from(carousel.querySelectorAll('.promo-slide'));
                        const dots = Array.from(carousel.querySelectorAll('.promo-dots button'));
                        let active = 0;
                        let timer;
                        function show(index) {
                            active = index;
                            slides.forEach((slide, i) => { slide.hidden = i !== active; });
                            dots.forEach((dot, i) => dot.setAttribute('aria-pressed', String(i === active)));
                        }
                        function stop() { clearInterval(timer); }
                        function start() {
                            stop();
                            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                                timer = setInterval(() => show((active + 1) % slides.length), 5000);
                            }
                        }
                        dots.forEach((dot, i) => dot.addEventListener('click', () => { show(i); stop(); }));
                        carousel.addEventListener('mouseenter', stop);
                        carousel.addEventListener('mouseleave', start);
                        carousel.addEventListener('focusin', stop);
                        carousel.addEventListener('focusout', event => { if (!carousel.contains(event.relatedTarget)) start(); });
                        start();
                    });
                    document.querySelectorAll('.member-save').forEach(button => {
                        const key = 'directory-saved-' + button.dataset.member;
                        function update(saved) { button.setAttribute('aria-pressed', String(saved)); button.textContent = saved ? '▮ SAVED' : '▮ SAVE'; }
                        update(localStorage.getItem(key) === '1');
                        button.addEventListener('click', () => { const saved = button.getAttribute('aria-pressed') !== 'true'; localStorage.setItem(key, saved ? '1' : '0'); update(saved); });
                    });
                    document.querySelectorAll('.phone-button').forEach(button => button.addEventListener('click', () => { button.textContent = button.dataset.phone; }));
                </script>
                <?php if($directoryNotice):?><p role="status"><?=e($directoryNotice)?></p><?php endif;?>
            <?php endif; ?>
        </div>
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
</body>
</html>
