<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/member_forms.php';


$pageTitle = 'Search Members';
$activeMenu = 'members';
$q = trim((string)($_GET['q'] ?? ''));
$email = trim((string)($_GET['email'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$plan = (int)($_GET['plan'] ?? 0);
$profession = (int)($_GET['profession'] ?? 0);
$service = (int)($_GET['service'] ?? 0);
$verified = (string)($_GET['verified'] ?? '');
$memberType = (string)($_GET['member_type'] ?? '');
$location = trim((string)($_GET['location'] ?? ''));
$joinedFrom = (string)($_GET['joined_from'] ?? '');
$joinedTo = (string)($_GET['joined_to'] ?? '');
$loginFrom = (string)($_GET['login_from'] ?? '');
$loginTo = (string)($_GET['login_to'] ?? '');
$submittedForm = (string)($_GET['submitted_form'] ?? '');
$profileImageFilter = (string)($_GET['profile_image'] ?? '');
$joinedVia = trim((string)($_GET['joined_via'] ?? ''));
$tag = trim((string)($_GET['tag'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'id_desc');
$duplicates = (string)($_GET['duplicates'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$conditions = [];
$params = [];

if ($q !== '') {
    $conditions[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.company LIKE ? OR u.filename LIKE ? OR CAST(u.user_id AS CHAR) = ? OR u.phone_number LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $q, $like);
}
if ($email !== '') {
    $conditions[] = 'u.email LIKE ?';
    $params[] = '%' . $email . '%';
}
if ($status === 'active' || $status === 'cancelled') {
    $conditions[] = 'u.active = ?';
    $params[] = $status === 'active' ? 2 : 0;
}
if ($plan > 0) {
    $conditions[] = 'u.subscription_id = ?';
    $params[] = $plan;
}
if ($profession > 0) {
    $conditions[] = 'u.profession_id = ?';
    $params[] = $profession;
}
if ($service > 0) {
    $conditions[] = 'EXISTS (SELECT 1 FROM rel_services rs WHERE rs.user_id = u.user_id AND rs.service_id = ?)';
    $params[] = $service;
}
if ($verified === 'yes' || $verified === 'no') {
    $conditions[] = 'u.verified = ?';
    $params[] = $verified === 'yes' ? 1 : 0;
}
if ($memberType !== '') {
    $conditions[] = 'u.listing_type = ?';
    $params[] = $memberType;
}
if ($location !== '') {
    $conditions[] = '(u.city LIKE ? OR u.state_ln LIKE ? OR u.country_ln LIKE ? OR u.zip_code LIKE ?)';
    $likeLocation = '%' . $location . '%';
    array_push($params, $likeLocation, $likeLocation, $likeLocation, $likeLocation);
}
if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $joinedFrom)) {
    $conditions[] = 'u.signup_date >= ?';
    $params[] = str_replace('-', '', $joinedFrom) . '000000';
}
if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $joinedTo)) {
    $conditions[] = 'u.signup_date <= ?';
    $params[] = str_replace('-', '', $joinedTo) . '235959';
}
if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $loginFrom)) {
    $conditions[] = 'u.last_login >= ?';
    $params[] = str_replace('-', '', $loginFrom) . '000000';
}
if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $loginTo)) {
    $conditions[] = 'u.last_login <= ?';
    $params[] = str_replace('-', '', $loginTo) . '235959';
}
if ($submittedForm === 'submitted' || $submittedForm === 'not_submitted') {
    $exists = $submittedForm === 'submitted' ? 'EXISTS' : 'NOT EXISTS';
    $conditions[] = "$exists (SELECT 1 FROM user_submitted_forms usf WHERE usf.user_id = u.user_id)";
}
if ($profileImageFilter === 'yes' || $profileImageFilter === 'no') {
    $exists = $profileImageFilter === 'yes' ? 'EXISTS' : 'NOT EXISTS';
    $conditions[] = "$exists (SELECT 1 FROM users_photo up WHERE up.user_id = u.user_id AND up.type = 'photo')";
}
if ($joinedVia !== '') {
    $conditions[] = 'u.ref_code LIKE ?';
    $params[] = '%' . $joinedVia . '%';
}
if ($tag !== '') {
    $conditions[] = "EXISTS (SELECT 1 FROM rel_tags rt WHERE rt.object_id = u.user_id AND rt.tag_type_id = (SELECT id FROM tag_types WHERE table_relation = 'users_data' LIMIT 1) AND rt.tag_id LIKE ?)";
    $params[] = '%' . $tag . '%';
}
if ($duplicates === 'yes') {
    $conditions[] = "u.email IS NOT NULL AND u.email <> '' AND EXISTS (SELECT 1 FROM users_data duplicate_user WHERE duplicate_user.email = u.email GROUP BY duplicate_user.email HAVING COUNT(*) > 1)";
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$count = $pdo->prepare("SELECT COUNT(*) FROM users_data u $where");
$count->execute($params);
$total = (int)$count->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$orderBy = [
    'id_desc' => 'u.user_id DESC',
    'id_asc' => 'u.user_id ASC',
    'joined_desc' => 'u.signup_date DESC',
    'joined_asc' => 'u.signup_date ASC'
][$sort] ?? 'u.user_id DESC';

$sql = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.company,
               u.phone_number, u.city, u.state_ln, u.country_ln, u.lat, u.lon,
               u.website, u.facebook, u.twitter, u.linkedin, u.youtube, u.instagram, u.pinterest,
               u.active, u.signup_date, u.last_login, u.subscription_id, u.listing_type,
               (SELECT p.file FROM users_photo p WHERE p.user_id = u.user_id AND p.type IN ('photo','logo') ORDER BY (p.type = 'photo') DESC, p.photo_id DESC LIMIT 1) AS profile_photo,
               (SELECT p.original FROM users_photo p WHERE p.user_id = u.user_id AND p.type IN ('photo','logo') ORDER BY (p.type = 'photo') DESC, p.photo_id DESC LIMIT 1) AS profile_original,
               s.subscription_name
        FROM users_data u
        LEFT JOIN subscription_types s ON s.subscription_id = u.subscription_id
        $where
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

$plans = $pdo->query('SELECT subscription_id, subscription_name FROM subscription_types ORDER BY subscription_name')->fetchAll();
$professions = tableExists($pdo, 'list_professions') ? $pdo->query('SELECT profession_id, name FROM list_professions ORDER BY sort_order, name')->fetchAll() : [];
$services = tableExists($pdo, 'list_services') ? $pdo->query('SELECT service_id, name FROM list_services ORDER BY sort_order, name')->fetchAll() : [];

require __DIR__ . '/../includes/header.php';
?>
<div class="members-page">
    <div class="members-heading">
        <div><h1>Search Members</h1><p>Search and manage registered members.</p></div>
        <span class="members-total"><?= number_format($total) ?> Members</span>
    </div>

    <div class="member-search-layout">
    <form class="member-search-panel" id="memberSearchForm" method="get">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search Name, ID, URL or Company">
        <select name="plan">
            <option value="0">Membership Plan</option>
            <?php foreach ($plans as $membership): ?>
                <option value="<?= (int)$membership['subscription_id'] ?>" <?= $plan === (int)$membership['subscription_id'] ? 'selected' : '' ?>><?= e($membership['subscription_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="profession">
            <option value="0">Select a Top-Level Category</option>
            <?php foreach ($professions as $item): ?><option value="<?= (int)$item['profession_id'] ?>" <?= $profession === (int)$item['profession_id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option><?php endforeach; ?>
        </select>
        <div class="member-date-range"><input type="date" name="joined_from" value="<?= e($joinedFrom) ?>" aria-label="Joined date from"><input type="date" name="joined_to" value="<?= e($joinedTo) ?>" aria-label="Joined date to"></div>
        <input type="text" name="email" value="<?= e($email) ?>" placeholder="Search email address">
        <select name="status"><option value="">Account Status</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option><option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled / Inactive</option></select>
        <select name="service"><option value="0">Select a Sub Category</option><?php foreach ($services as $item): ?><option value="<?= (int)$item['service_id'] ?>" <?= $service === (int)$item['service_id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option><?php endforeach; ?></select>
        <div class="member-date-range"><input type="date" name="login_from" value="<?= e($loginFrom) ?>" aria-label="Login date from"><input type="date" name="login_to" value="<?= e($loginTo) ?>" aria-label="Login date to"></div>
        <select name="verified"><option value="">Verified Status</option><option value="yes" <?= $verified === 'yes' ? 'selected' : '' ?>>Verified</option><option value="no" <?= $verified === 'no' ? 'selected' : '' ?>>Not Verified</option></select>
        <select name="member_type"><option value="">Member Type</option><option value="Individual" <?= $memberType === 'Individual' ? 'selected' : '' ?>>Individual</option><option value="Company" <?= $memberType === 'Company' ? 'selected' : '' ?>>Company</option></select>
        <input type="text" name="location" value="<?= e($location) ?>" placeholder="Search Location">
        <select name="submitted_form"><option value="">Forms Submitted / Not Submitted</option><option value="submitted" <?= $submittedForm === 'submitted' ? 'selected' : '' ?>>Forms Submitted</option><option value="not_submitted" <?= $submittedForm === 'not_submitted' ? 'selected' : '' ?>>Forms Not Submitted</option></select>
        <select name="profile_image"><option value="">Profile Images</option><option value="yes" <?= $profileImageFilter === 'yes' ? 'selected' : '' ?>>Has Profile Photo</option><option value="no" <?= $profileImageFilter === 'no' ? 'selected' : '' ?>>No Profile Photo</option></select>
        <input type="text" name="joined_via" value="<?= e($joinedVia) ?>" placeholder="Joined Via / Referral">
        <input type="text" name="tag" value="<?= e($tag) ?>" placeholder="Member Tags">
        <select name="duplicates"><option value="">Find Duplicates</option><option value="yes" <?= $duplicates === 'yes' ? 'selected' : '' ?>>Duplicate Emails</option></select>
        <button type="submit" class="member-search-button">Search Members</button>
        <a class="member-reset-button" href="<?= e($config['base_url']) ?>/admin/search-members.php">Reset</a>
    </form>
    <aside class="member-search-actions">
        <a class="member-add-new" href="<?= e($config['base_url']) ?>/public/signup.php"><i class="fa fa-user-plus"></i><span>ADD NEW MEMBER</span></a>
        <a class="member-static-action" href="#"><i class="fa fa-refresh"></i><span>Sync Members with Google Maps</span></a>
        <details class="member-list-actions">
            <summary><i class="fa fa-folder-open"></i><span>List Actions</span><i class="fa fa-caret-down"></i></summary>
            <div class="member-list-actions-menu">
                <a href="#"><i class="fa fa-upload"></i> Import Members via CSV File</a>
                <a href="#"><i class="fa fa-external-link"></i> Export List of Current Member Results</a>
                <a href="#"><i class="fa fa-list"></i> Create Smart List from Current Results</a>
            </div>
        </details>
        <a class="member-static-action member-bd-butler" href="#"><i class="fa fa-magic"></i><span>Ask BD Butler — Member Insights &amp; More</span></a>
    </aside>
    </div>

    <div class="members-results-meta">
        <span>Showing <?= $total ? $offset + 1 : 0 ?> – <?= min($offset + $perPage, $total) ?> of <?= number_format($total) ?> member results</span>
        <label class="member-sort-label">Sort
            <select name="sort" form="memberSearchForm" onchange="this.form.submit()">
                <option value="id_desc" <?= $sort === 'id_desc' ? 'selected' : '' ?>>ID Highest</option>
                <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>ID Lowest</option>
                <option value="joined_desc" <?= $sort === 'joined_desc' ? 'selected' : '' ?>>Joined Newest</option>
                <option value="joined_asc" <?= $sort === 'joined_asc' ? 'selected' : '' ?>>Joined Oldest</option>
            </select>
        </label>
    </div>

    <form class="member-bulk-toolbar" id="bulkMemberForm" method="post" action="<?= e($config['base_url']) ?>/admin/member-actions.php" onsubmit="return confirm('Apply this action to the selected members?');">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <select name="action">
            <option value="">Bulk Actions</option>
            <option value="bulk-deactivate">Deactivate Selected</option>
        </select>
        <button type="submit">Apply</button>
    </form>
    <div class="members-table-wrap">
        <table class="members-table">
            <thead>
                <tr>
                    <th class="member-check"><input type="checkbox" id="selectMembers" aria-label="Select all members" form="bulkMemberForm"></th>
                    <th>Status</th>
                    <th>Contact Information</th>
                    <th>Membership</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$members): ?>
                <tr><td colspan="5" class="member-empty">No members match your search.</td></tr>
            <?php endif; ?>
            <?php foreach ($members as $member): ?>
                <?php
                $memberName = trim($member['first_name'] . ' ' . $member['last_name']);
                $joinedDate = $member['signup_date'] ? DateTime::createFromFormat('YmdHis', (string)$member['signup_date']) : false;
                $lastLoginDate = $member['last_login'] ? DateTime::createFromFormat('YmdHis', (string)$member['last_login']) : false;
                $joined = $joinedDate ? $joinedDate->format('M j, Y, g:i a') : '—';
                $lastLogin = $lastLoginDate ? $lastLoginDate->format('M j, Y, g:i a') : '—';
                $profileImage = memberPhotoUrl((string)($member['profile_photo'] ?? ''), (string)($member['profile_original'] ?? ''));
                ?>
                <tr class="<?= (int)$member['active'] === 2 ? '' : 'member-inactive' ?>">
                    <td class="member-check"><input type="checkbox" name="member_ids[]" value="<?= (int)$member['user_id'] ?>" aria-label="Select <?= e($memberName) ?>" form="bulkMemberForm"></td>
                    <td class="member-status">
                        <?php if ($profileImage !== ''): ?>
                            <img src="<?= e($profileImage) ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><span class="member-avatar" style="display:none"><i class="fa fa-user"></i></span>
                        <?php else: ?>
                            <span class="member-avatar"><i class="fa fa-user"></i></span>
                        <?php endif; ?>
                        <span class="member-status-badge <?= (int)$member['active'] === 2 ? 'is-active' : ((int)$member['active'] === 0 ? 'is-cancelled' : 'is-pending') ?>"><?= (int)$member['active'] === 2 ? 'Active' : ((int)$member['active'] === 0 ? 'Cancelled' : 'Pending / Other') ?></span>
                    </td>
                    <td class="member-contact">
                        <div class="member-contact-main">
                            <div>
                                <a class="member-name" href="<?= e($config['base_url']) ?>/admin/member-actions.php?action=details&id=<?= (int)$member['user_id'] ?>"><i class="fa fa-file-text-o"></i> <?= e($memberName ?: 'Unnamed Member') ?></a>
                                <span class="member-kind"><i class="fa fa-user-circle-o"></i> <?= e(($member['listing_type'] ?? '') === 'Company' ? 'Company' : (($member['listing_type'] ?? '') === 'Individual' ? 'Individual' : '')) ?></span>
                            </div>
                            <?php if (!empty($member['email'])): ?><div><i class="fa fa-envelope-o"></i> <a class="member-contact-link" href="mailto:<?= e($member['email']) ?>"><?= e($member['email']) ?></a></div><?php endif; ?>
                            <?php if ($member['company'] || $member['phone_number']): ?><div><?php if ($member['company']): ?><i class="fa fa-building-o"></i> <?= e($member['company']) ?><?php endif; ?><?php if ($member['phone_number']): ?> <span class="member-phone"><i class="fa fa-phone"></i> <a class="member-contact-link" href="tel:<?= e($member['phone_number']) ?>"><?= e($member['phone_number']) ?></a></span><?php endif; ?></div><?php endif; ?>
                            <?php $memberSocials = [['website', 'fa-home', 'Website'], ['facebook', 'fa-facebook', 'Facebook'], ['twitter', 'fa-twitter', 'X'], ['linkedin', 'fa-linkedin', 'LinkedIn'], ['youtube', 'fa-youtube', 'YouTube'], ['instagram', 'fa-instagram', 'Instagram'], ['pinterest', 'fa-pinterest', 'Pinterest']]; ?>
                            <?php $hasSocials = false; foreach ($memberSocials as $social) { if (!empty($member[$social[0]])) { $hasSocials = true; break; } } ?>
                           <?php if ($hasSocials): ?>
    <div class="member-social-links">

        <?php foreach ($memberSocials as $social): ?>

            <?php if (!empty($member[$social[0]])): ?>

                <?php
                $socialUrl = trim((string)$member[$social[0]]);

                if (preg_match('~^https?%3A~i', $socialUrl)) {
                    $socialUrl = rawurldecode($socialUrl);
                }

                if (!preg_match('~^https?://~i', $socialUrl)) {
                    $socialUrl = 'https://' . $socialUrl;
                }
                ?>

                <?php if (filter_var($socialUrl, FILTER_VALIDATE_URL)): ?>

                    <a href="<?= e($socialUrl) ?>"
                       target="_blank"
                       rel="noopener"
                       title="<?= e($social[2]) ?>"
                       aria-label="<?= e($social[2]) ?>">

                        <i class="fa <?= e($social[1]) ?>"></i>

                    </a>

                <?php endif; ?>

            <?php endif; ?>

        <?php endforeach; ?>

    </div>
<?php endif; ?>
                            <div><i class="fa fa-map-marker"></i> <?= e(implode(', ', array_filter([$member['city'], $member['state_ln'], $member['country_ln']]))) ?></div>
                        </div>
                        <details class="member-actions">
                            <summary>Actions <i class="fa fa-caret-down"></i></summary>
                            <div class="member-actions-menu">
                                <a href="<?= e($config['base_url']) ?>/admin/member-actions.php?action=details&id=<?= (int)$member['user_id'] ?>">Account Details</a>
                                <a href="<?= e($config['base_url']) ?>/public/member.php?id=<?= (int)$member['user_id'] ?>" target="_blank" rel="noopener">View Live Profile</a>
                                <a href="<?= e($config['base_url']) ?>/public/account/contact.php?admin_member=<?= (int)$member['user_id'] ?>">Quick Edit</a>
                                <?php if ((int)$member['active'] === 2): ?>
                                    <form method="post" action="<?= e($config['base_url']) ?>/admin/login-as-member.php" target="_blank">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="member_id" value="<?= (int)$member['user_id'] ?>">
                                        <button type="submit">Login as Member</button>
                                    </form>
                                <?php else: ?>
                                    <span class="member-action-disabled">Activate this member before logging in</span>
                                <?php endif; ?>
                                <?php if ((int)$member['active'] !== 2): ?>
                                    <form method="post" action="<?= e($config['base_url']) ?>/admin/member-actions.php" onsubmit="return confirm('Activate this member account?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="member_id" value="<?= (int)$member['user_id'] ?>">
                                        <input type="hidden" name="action" value="activate-account">
                                        <button type="submit">Activate Member</button>
                                    </form>
                                <?php endif; ?>
                                <a href="mailto:<?= e($member['email']) ?>">Compose Email</a>
                                <a href="<?= e($config['base_url']) ?>/admin/member-actions.php?action=payment-history&id=<?= (int)$member['user_id'] ?>">Payment History</a>
                                <a href="<?= e($config['base_url']) ?>/admin/member-actions.php?action=credit-card&id=<?= (int)$member['user_id'] ?>" data-member-dialog="card" data-member-id="<?= (int)$member['user_id'] ?>">Update Credit Card</a>
                                <a href="<?= e($config['base_url']) ?>/admin/member-actions.php?action=change-password&id=<?= (int)$member['user_id'] ?>">Change Password</a>
                                <form method="post" action="<?= e($config['base_url']) ?>/admin/member-actions.php" onsubmit="return confirm('Deactivate this member account?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="member_id" value="<?= (int)$member['user_id'] ?>">
                                    <input type="hidden" name="action" value="delete-account">
                                    <button type="submit" class="member-delete-action">Delete Account</button>
                                </form>
                                <a href="<?= e($config['base_url']) ?>/admin/member-actions.php?action=clone&id=<?= (int)$member['user_id'] ?>" data-member-dialog="clone" data-member-id="<?= (int)$member['user_id'] ?>">Clone Account</a>
                            </div>
                        </details>
                    </td>
                    <td class="member-membership">
                        <small>Member ID: #<?= (int)$member['user_id'] ?></small>
                        <a href="<?= e($config['base_url']) ?>/admin/member-actions.php?action=details&id=<?= (int)$member['user_id'] ?>"><?= e($member['subscription_name'] ?: 'No Membership') ?> <i class="fa fa-external-link"></i></a>
                        <small>Join Date <?= e($joined) ?></small>
                        <small>Last Login <?= e($lastLogin) ?></small>
                    </td>
                    <td class="member-revenue">No Revenue</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="member-pagination" aria-label="Member pages">
            <?php for ($number = 1; $number <= $totalPages; $number++): ?>
                <a class="<?= $number === $page ? 'current' : '' ?>" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $number]))) ?>"><?= $number ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</div>
<div class="floating-help"><span>☻</span><a href="#">HELP CENTER</a></div>
<dialog id="memberCloneDialog" class="member-reference-dialog member-clone-dialog">
    <form method="post" action="<?= e($config['base_url']) ?>/admin/member-actions.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="clone">
        <input type="hidden" name="member_id" id="cloneMemberId">
        <div class="clone-warning-icon">!</div>
        <h2>Clone Account</h2>
        <p>Are you sure you want to clone this member?</p>
        <div class="reference-dialog-buttons"><button type="button" data-close-dialog>No, cancel</button><button type="submit" class="clone-confirm">Yes, continue</button></div>
    </form>
</dialog>
<dialog id="memberCardDialog" class="member-reference-dialog member-card-dialog">
    <h2>Add Credit Card</h2>
    <div class="card-reference-panel">
        <label for="cardDemoName">Name on Card</label>
        <input id="cardDemoName" placeholder="Name on Card" autocomplete="off" disabled>
        <div class="card-test-notice"><button type="button" class="card-notice-close" aria-label="Dismiss test notice">×</button><h3><i class="fa fa-info-circle"></i> Payment Processing is in TEST MODE</h3><p><em>After connecting a custom domain, you can add your own payment gateway.</em></p>
            <div class="card-test-details"><strong>Use the following test card details:</strong><p><i class="fa fa-credit-card"></i> Card: 4242 4242 4242 4242</p><p><i class="fa fa-calendar-o"></i> Expiration: 12/35 &nbsp; <i class="fa fa-lock"></i> CVC: 123</p></div>
        </div>
        <label>Card Number</label><label>Expiration Date / Security Code</label>
        <input placeholder="Card number" disabled aria-label="Card number unavailable">
        <p class="card-reference-error">Enter a valid credit card to continue</p>
        <small>Preview only — no connected member card gateway.</small>
    </div>
    <div class="reference-dialog-buttons"><button type="button" data-close-dialog>Cancel</button><button type="button" class="card-confirm" disabled>Save credit card &amp; continue</button></div>
</dialog>
<script>
document.querySelectorAll('[data-member-dialog]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault();
    const clone = link.dataset.memberDialog === 'clone';
    if (clone) document.getElementById('cloneMemberId').value = link.dataset.memberId;
    link.closest('details').open = false;
    document.getElementById(clone ? 'memberCloneDialog' : 'memberCardDialog').showModal();
}));
document.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
document.querySelector('.card-notice-close').addEventListener('click', event => event.currentTarget.parentElement.hidden = true);
document.getElementById('selectMembers')?.addEventListener('change', function () {
    document.querySelectorAll('.members-table tbody input[type="checkbox"]').forEach(function (checkbox) {
        checkbox.checked = document.getElementById('selectMembers').checked;
    });
});
const memberMenus = Array.from(document.querySelectorAll('.member-actions'));
function openMemberMenu(menu) {
    memberMenus.forEach(other => { if (other !== menu) other.open = false; });
    menu.open = true;
    const trigger = menu.querySelector('summary').getBoundingClientRect();
    const panel = menu.querySelector('.member-actions-menu');
    const width = panel.offsetWidth;
    const height = panel.offsetHeight;
    const fitsLeft = trigger.left >= width + 8;
    const fitsRight = window.innerWidth - trigger.right >= width + 8;
    const left = fitsLeft ? trigger.left - width : fitsRight ? trigger.right : Math.max(8, Math.min(trigger.left, window.innerWidth - width - 8));
    const top = fitsLeft || fitsRight ? trigger.top : (trigger.bottom + height <= window.innerHeight - 8 ? trigger.bottom : trigger.top - height);
    panel.style.left = left + 'px';
    panel.style.top = Math.max(8, Math.min(top, window.innerHeight - height - 8)) + 'px';
}
memberMenus.forEach(menu => {
    const summary = menu.querySelector('summary');
    let pinned = false;
    menu.addEventListener('mouseenter', () => {
        if (matchMedia('(hover: hover) and (pointer: fine)').matches) openMemberMenu(menu);
    });
    menu.addEventListener('mouseleave', () => { if (!pinned) menu.open = false; });
    summary.addEventListener('click', event => {
        event.preventDefault();
        if (pinned && menu.open) { menu.open = false; pinned = false; }
        else { openMemberMenu(menu); pinned = true; }
    });
    menu.addEventListener('toggle', () => { if (!menu.open) pinned = false; });
});
document.addEventListener('click', event => {
    memberMenus.forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') memberMenus.forEach(menu => { menu.open = false; });
});
window.addEventListener('resize', () => memberMenus.forEach(menu => { menu.open = false; }));
window.addEventListener('scroll', event => {
    if (!event.target.closest?.('.member-actions-menu')) memberMenus.forEach(menu => { menu.open = false; });
}, true);
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
