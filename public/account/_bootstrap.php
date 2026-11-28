<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/member_forms.php';
require_once __DIR__ . '/../../includes/form_fields.php';
require_once __DIR__ . '/../../includes/recaptcha.php';
require_once __DIR__ . '/../../includes/custom_components.php';

require_once __DIR__ . '/../../includes/membership_runtime.php';
$isAdminPreview = !empty($_SESSION['admin_id']) && isset($_GET['admin_member']);
if (empty($_SESSION['member_id']) && !$isAdminPreview) {
    redirect('/public/login.php');
}
$memberId = $isAdminPreview ? (int)$_GET['admin_member'] : (int)$_SESSION['member_id'];
$memberQuery = $pdo->prepare(
    'SELECT u.*, s.subscription_name
     FROM users_data u
     LEFT JOIN subscription_types s ON s.subscription_id = u.subscription_id
     WHERE u.user_id = ? LIMIT 1'
);
$memberQuery->execute([$memberId]);
$member = $memberQuery->fetch();

if (!$member || (!$isAdminPreview && ((int)$member['user_id'] !== (int)$_SESSION['member_id'] || !isActiveMemberRecord($member['active'])))) {
    unset($_SESSION['member_id'], $_SESSION['member_email'], $_SESSION['member_name'], $_SESSION['is_impersonating']);
    if (!empty($_SESSION['admin_impersonation']['admin_id'])) {
        $_SESSION['admin_id'] = (int) $_SESSION['admin_impersonation']['admin_id'];
        $_SESSION['admin_name'] = (string) ($_SESSION['admin_impersonation']['admin_name'] ?? '');
        unset($_SESSION['admin_impersonation']);
        session_regenerate_id(true);
        redirect('/admin/search-members.php');
    }
    redirect('/public/login.php');
}

$section = $section ?? 'home';
$sectionTitles = [
    'home' => 'My Dashboard',
    'contact' => 'Contact Details',
    'profile' => 'Profile Photo',
    'listing' => 'Listing Details',
    'about' => 'About Me'
];
$pageTitle = $sectionTitles[$section] ?? 'My Dashboard';
$memberName = trim($member['first_name'] . ' ' . $member['last_name']);
$profileImage = memberFieldValue($pdo, $member, 'profile_photo');
$member['profile_photo_url'] = $profileImage;
$member['profile_logo_url'] = memberFieldValue($pdo, $member, 'profile_logo');
$progress = 75;

function memberAccountHeader(array $member, string $section, array $config, bool $isAdminPreview): void
{
    $base = $config['base_url'];
    $links = [
        'home' => $base . '/public/account/home.php',
        'contact' => $base . '/public/account/contact.php',
        'profile' => $base . '/public/account/profile.php',
        'listing' => $base . '/public/account/listing.php',
        'about' => $base . '/public/account/about.php'
    ];
    $memberName = trim((string)$member['first_name'] . ' ' . (string)$member['last_name']);
    $publicListingUrl = $base . '/public/member.php?id=' . (int)$member['user_id'];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($section === 'home' ? 'My Dashboard' : ucwords($section)) ?> | Brilliant Directories Demo</title>
        <link rel="stylesheet" href="<?= e($base) ?>/public/assets/css/website.css">
        <link rel="stylesheet" href="<?= e($base) ?>/public/assets/css/member-account.css">
        <link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/48.5.1/ckeditor5.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </head>
    <body class="member-account-body">
    <?php $accountFlash = getFlash(); ?>
    <?php if (!empty($_SESSION['is_impersonating'])): ?>
        <div class="impersonation-bar">You are viewing this website as a member.
            <form method="post" action="<?= e($base) ?>/admin/return-to-admin.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <button type="submit">Return to Admin Panel</button>
            </form>
        </div>
    <?php elseif ($isAdminPreview): ?>
        <div class="impersonation-bar">Administrator preview of this member account. <a href="<?= e($base) ?>/admin/search-members.php">Return to Search Members</a></div>
    <?php endif; ?>
    <header class="member-site-header">
        <a class="member-brand" href="<?= e($base) ?>/public/index.php"><span class="member-brand-icon"><i class="fa fa-user-o"></i></span><span><strong>Brilliant Directories Demo</strong><small>The All-In-One Business Search Directory Theme</small></span></a>
        <details class="member-welcome-menu">
            <summary><span class="member-welcome-avatar" data-account-logo><?php if (!empty($member['profile_logo_url'])): ?><img src="<?= e($member['profile_logo_url']) ?>" alt=""><?php else: ?><i class="fa fa-user"></i><?php endif; ?></span> Welcome, <?= e((string)$member['first_name']) ?> <i class="fa fa-angle-down"></i></summary>
            <div class="member-welcome-dropdown">
                <?php if (!empty($member['profile_logo_url'])): ?><img data-account-dropdown-logo class="member-dropdown-avatar" src="<?= e($member['profile_logo_url']) ?>" alt="Profile logo"><?php else: ?><span data-account-dropdown-logo class="member-dropdown-avatar member-avatar-placeholder"><i class="fa fa-user"></i></span><?php endif; ?>
                <strong><?= e($memberName) ?></strong>
                <small>Status: <b>Active</b></small>
                <small>Plan: <b><?= e((string)($member['subscription_name'] ?? 'Membership')) ?></b></small>
                <div><a href="<?= e($publicListingUrl) ?>">View Listing</a><a href="<?= e($links['home']) ?>">Dashboard</a></div>
                <a class="member-dropdown-logout" href="<?= e($base) ?>/public/login.php?logout=1">Logout</a>
            </div>
        </details>
    </header>
    <nav class="member-top-nav"><a href="<?= e($base) ?>/public/index.php">Home</a><a href="<?= e($base) ?>/public/search.php">Search Directory</a><a href="<?= e($base) ?>/public/index.php#how-it-works">How It Works</a><a href="<?= e($base) ?>/public/index.php#blog">Website Blog</a></nav>
    <div class="member-account-layout">
        <aside class="member-account-sidebar">
            <div class="member-profile-card">
                <strong><?= e($memberName) ?></strong>
                <?php if (!empty($member['profile_photo_url'])): ?><img data-account-photo class="member-profile-avatar" src="<?= e($member['profile_photo_url']) ?>" alt="Profile photo"><?php else: ?><span data-account-photo class="member-profile-avatar"><i class="fa fa-user"></i></span><?php endif; ?>
                <a class="view-public-listing" href="<?= e($publicListingUrl) ?>">View Public Listing <i class="fa fa-external-link"></i></a>
                <b>Member ID #<?= (int)$member['user_id'] ?></b>
                <small>Plan: <?= e((string)($member['subscription_name'] ?? 'Membership')) ?></small>
                <a class="change-plan" href="<?= e($base) ?>/public/membership.php">Change Membership Plan</a>
            </div>
            <a href="<?= e($links['home']) ?>" class="<?= $section === 'home' ? 'selected' : '' ?>"><i class="fa fa-home"></i> My Dashboard</a>
            <details class="member-sidebar-group" open><summary><i class="fa fa-id-card"></i> Manage Listing <i class="fa fa-caret-down member-sidebar-caret"></i></summary>
                <a href="<?= e($links['contact']) ?>" class="<?= $section === 'contact' ? 'selected' : '' ?>">– Contact Details</a>
                <a href="<?= e($links['profile']) ?>" class="<?= $section === 'profile' ? 'selected' : '' ?>">– Profile Photo</a>
                <a href="<?= e($links['listing']) ?>" class="<?= $section === 'listing' ? 'selected' : '' ?>">– Listing Details</a>
                <a href="<?= e($links['about']) ?>" class="<?= $section === 'about' ? 'selected' : '' ?>">– About Me</a>
            </details>
            <a href="<?= e($base) ?>/public/search.php"><i class="fa fa-check-square-o"></i> Manage Leads</a>
            <a href="<?= e($base) ?>/public/search.php"><i class="fa fa-star-o"></i> Manage Reviews</a>
            <details class="member-sidebar-group"><summary><i class="fa fa-bookmark"></i> My Favorites <i class="fa fa-caret-down member-sidebar-caret"></i></summary><a href="<?= e($base) ?>/public/search.php">Browse Directory</a></details>
            <a href="<?= e($base) ?>/public/index.php#blog"><i class="fa fa-pencil"></i> Community Articles <span class="member-sidebar-count">0</span></a>
            <details class="member-sidebar-group"><summary><i class="fa fa-cog"></i> Account <i class="fa fa-caret-down member-sidebar-caret"></i></summary><a href="<?= e($links['contact']) ?>">Account Details</a><a href="<?= e($base) ?>/public/login.php?logout=1">Logout</a></details>
        </aside>
        <main class="member-account-main">
            <?php if ($accountFlash): ?><div class="member-account-alert <?= e($accountFlash['type']) ?>"><?= e($accountFlash['message']) ?></div><?php endif; ?>
            <div class="member-section-tabs">
                <a class="<?= $section === 'contact' ? 'active' : 'complete' ?>" href="<?= e($links['contact']) ?>"><i class="fa fa-check-circle-o"></i> Contact Details</a>
                <a class="<?= $section === 'profile' ? 'active' : 'pending' ?>" href="<?= e($links['profile']) ?>"><i class="fa fa-picture-o"></i> Profile Photo</a>
                <a class="<?= $section === 'listing' ? 'active' : 'complete' ?>" href="<?= e($links['listing']) ?>"><i class="fa fa-check-circle-o"></i> Listing Details</a>
                <a class="<?= $section === 'about' ? 'active' : 'pending' ?>" href="<?= e($links['about']) ?>"><i class="fa fa-exclamation-circle"></i> About Me</a>
            </div>
    <?php
}

function memberAccountFooter(array $config): void
{
    ?>
        </main>
    </div>
    <footer class="member-account-footer">
        <div><strong>About</strong><p>Finding members is easy with Brilliant Directories Demo. Easily connect with members in your area. For members, our website is a powerful tool for attracting more customers.</p><a class="member-footer-email" href="#contact"><i class="fa fa-envelope-o"></i></a></div>
        <div><strong>Website</strong><a href="<?= e($config['base_url']) ?>/public/index.php#how-it-works">How It Works</a><a href="<?= e($config['base_url']) ?>/public/index.php#contact">Contact Us</a></div>
        <div><strong>Search</strong><a href="<?= e($config['base_url']) ?>/public/search.php">Browse Directory</a><a href="<?= e($config['base_url']) ?>/public/search.php">Search by Category</a></div>
        <div><strong>Support</strong><a href="<?= e($config['base_url']) ?>/public/login.php">Member Login</a><a href="<?= e($config['base_url']) ?>/public/login.php">Reset Password</a></div>
        <a class="member-footer-join" href="<?= e($config['base_url']) ?>/public/membership.php"><i class="fa fa-plus-circle"></i> Members – Join Today</a>
        <small>© <?= date('Y') ?> Brilliant Directories Demo &nbsp; All Rights Reserved &nbsp; Terms of Use &nbsp; Privacy Policy</small>
    </footer>
    </body>
    </html>
    <?php
}

function renderMemberConfiguredForm(PDO $pdo, array $member, string $formName, string $section, string $csrf): void
{
    $canonicalFormName = $formName;
    $formName = membershipAccountForm($pdo, $member, $section, $formName);
    $formIdQuery = $pdo->prepare('SELECT form_id FROM forms WHERE form_name = ? LIMIT 1');
    $formIdQuery->execute([$formName]);
    $memberFormId = (int)$formIdQuery->fetchColumn();
    $form = $pdo->prepare('SELECT form_title FROM forms WHERE form_name = ? LIMIT 1');
    $form->execute([$formName]);
    $title = (string)($form->fetchColumn() ?: $section);
    $fields = $pdo->prepare('SELECT * FROM form_fields WHERE form_name = ? ORDER BY field_order, field_id');
    $fields->execute([$formName]);
    $configuredFields = $fields->fetchAll();
    require_once __DIR__ . '/../../includes/form_destination.php';
    $destinationRecord = null;
    $destinationMeta = [];
    try {
        $configuredDestination = configuredFormDestination($pdo, $formName, 'users_data', 'user_id');
        [$destinationTable, $destinationKey, $destinationColumns] = $configuredDestination;
        if ($destinationTable !== 'users_data') {
            if ($destinationKey === 'user_id') {
                $recordQuery = $pdo->prepare("SELECT * FROM `$destinationTable` WHERE user_id = ? LIMIT 2");
                $recordQuery->execute([(int)$member['user_id']]);
            } else {
                validateIndependentDestination($pdo, $configuredDestination);
                $recordId = independentMemberDestinationId($pdo, $configuredDestination, (int)$member['user_id']);
                $recordQuery = $pdo->prepare("SELECT * FROM `$destinationTable` WHERE `$destinationKey` = ? LIMIT 1");
                $recordQuery->execute([$recordId]);
            }
            $records = $recordQuery->fetchAll(PDO::FETCH_ASSOC);
            if (count($records) === 1) {
                $destinationRecord = $records[0];
                $metaQuery = $pdo->prepare('SELECT `key`, value FROM users_meta WHERE `database` = ? AND database_id = ? ORDER BY meta_id');
                $metaQuery->execute([$destinationTable, $destinationRecord[$destinationKey]]);
                $destinationMeta = $metaQuery->fetchAll(PDO::FETCH_KEY_PAIR);
            }
        }
    } catch (Throwable $error) {
        error_log('Member form destination: ' . $error->getMessage());
    }
    $aboutHeadingFieldId = 0;
    if ($section === 'about') {
        foreach ($configuredFields as $configuredField) {
            if ((string)($configuredField['field_input_view'] ?? '1') !== '0' && in_array(formFieldInternalType((string)$configuredField['field_type']), ['richtext','richtext_image','richtext_elements','richtext_media'], true)) {
                $aboutHeadingFieldId = (int)$configuredField['field_id'];
                $configuredLabel = trim(strip_tags((string)$configuredField['field_text']));
                if ($configuredLabel !== '') $title = $configuredLabel;
                break;
            }
        }
    }
    ?>
    <form class="member-dynamic-form" method="post" action="<?= e($GLOBALS['config']['base_url']) ?>/public/account/save.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="form_name" value="<?= e($canonicalFormName) ?>">
        <input type="hidden" name="section" value="<?= e($section) ?>">
        <?php if (!empty($GLOBALS['isAdminPreview'])): ?><input type="hidden" name="admin_member" value="<?= (int)$member['user_id'] ?>"><?php endif; ?>
        <h2><?= e($title) ?></h2>
        <?php if ($section === 'contact'): ?><div class="member-form-notice">💡 To activate your listing, please fill in all of the required fields below.</div><?php endif; ?>
        <?php foreach ($configuredFields as $field): ?>
            <?php
            if ((string)($field['field_input_view'] ?? '1') === '0') continue;
            $name = trim((string)$field['field_name']);
            $type = formFieldInternalType((string)$field['field_type']);
            $label = trim(strip_tags((string)$field['field_text']));
            $displayClass = preg_replace('/[^a-zA-Z0-9 _-]/', '', (string)($field['display_class'] ?? ''));
            $inputClass = preg_replace('/[^a-zA-Z0-9 _-]/', '', (string)($field['input_class'] ?? ''));
            if ($type === 'section') {
                echo '<h3 class="member-form-section-title">' . e($label) . '</h3>';
                if ($section === 'contact' && $name === 'section_location') {
                    $address = trim(implode(', ', array_filter([
                        $member['address1'] ?? '', $member['address2'] ?? '',
                        $member['city'] ?? '', $member['state_ln'] ?? '',
                        $member['country_ln'] ?? '', $member['zip_code'] ?? ''
                    ])));
                    $mapQuery = rawurlencode($address);
                    echo '<div class="member-location-map"><label for="member-map-address">Enter a location</label><input id="member-map-address" type="text" value="' . e($address) . '" placeholder="Enter your business address"><iframe id="member-location-frame" title="Map showing the member address" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=' . $mapQuery . '&amp;output=embed"></iframe></div>';
                }
                continue;
            }
            if ($type === 'captcha') { echo renderConfiguredCaptcha($pdo); continue; }
            if ($type === 'submit') {
                $buttonMeta = json_decode((string)($field['json_meta'] ?? ''), true);
                if (!empty($buttonMeta['adminOnly']) && empty($GLOBALS['isAdminPreview'])) continue;
                echo '<button type="submit" class="member-save-button">' . e($label) . '</button>';
                continue;
            }
            if ($type === 'html') {
                require_once __DIR__ . '/../../includes/widgets.php';
                $widgetContent = (string)$field['field_text'] . ' ' . (string)($field['field_ldesc'] ?? '');
                if (preg_match('/\[widget=[a-zA-Z0-9_.-]+\]/', $widgetContent)) {
                    echo renderWidgetShortcodes($pdo, $widgetContent);
                    continue;
                }
                $storedType = strtolower(trim((string)$field['field_type']));
                $componentReference = trim((string)$field['field_text']);
                if (isTrustedComponentReference($componentReference)) {
                    echo renderTrustedPhpComponent($componentReference, [
                        'pdo' => $pdo,
                        'member' => $member,
                        'form_id' => $memberFormId,
                        'field_id' => (int)$field['field_id'],
                        'user_id' => (int)$member['user_id'],
                        'context' => !empty($GLOBALS['isAdminPreview']) ? 'admin-preview' : 'member',
                        'preview' => !empty($GLOBALS['isAdminPreview']),
                        'csrf' => $csrf,
                        'base_url' => $GLOBALS['config']['base_url']
                    ]);
                } elseif ($storedType === 'custom' && $name === 'user_consent_gdpr') {
                    echo '<div class="member-custom-widget"><label><input type="checkbox" name="fields[user_consent_gdpr]" value="1"> I agree to the processing of my personal information in accordance with the <a href="' . e($GLOBALS['config']['base_url']) . '/public/index.php#privacy" target="_blank" rel="noopener">Privacy Policy</a>.</label></div>';
                } elseif ($storedType === 'custom' && $name === 'widget_bootstrap_theme_form_agree_terms_conditions') {
                    echo '<div class="member-custom-widget"><label><input type="checkbox" name="fields[terms_accepted]" value="1" required> By continuing, you agree to our <a href="' . e($GLOBALS['config']['base_url']) . '/public/index.php#terms" target="_blank" rel="noopener">terms &amp; conditions</a>.</label></div>';
                } elseif (preg_match('/^\\[widget=([^\\]]+)\\]$/', trim((string)$field['field_text']), $widgetMatch)) {
                    echo '<div class="member-custom-widget">This custom widget (' . e($widgetMatch[1]) . ') is not available in the member form renderer.</div>';
                } else {
                    echo '<div class="member-custom-html ' . e($displayClass) . '">' . safeMemberRichText((string)$field['field_text']) . '</div>';
                }
                continue;
            }
            if ($name === '') continue;
            if ($type === 'help_alert') {
                echo '<div class="member-form-notice ' . e($displayClass) . '">' . e((string)($field['field_sdesc'] ?: $label)) . '</div>';
                continue;
            }
            $value = memberFieldValue($pdo, $member, $name, $section);
            if ($destinationRecord !== null) {
                if (array_key_exists($name, $destinationRecord)) $value = (string)$destinationRecord[$name];
                elseif (array_key_exists($name, $destinationMeta)) $value = (string)$destinationMeta[$name];
            }
            if ($value === '') $value = (string)($field['default_value'] ?? '');
            $options = preg_split('/\r\n|\r|\n/', (string)$field['field_options']);
            if (!$field['field_options'] && in_array($type, ['country','state','top_category','years'], true)) {
                $lists = loadBuilderLists($pdo);
                if ($type === 'country') $options = $lists['countries'];
                if ($type === 'state') $options = $lists['states'];
                if ($type === 'top_category') $options = $lists['categories'];
                if ($type === 'years') $options = range((int)date('Y'), 1900);
            }
            if ($name === 'listing_type' && in_array($name, memberDefaultFieldNames($section), true)) {
                $options = [['value' => 'Individual', 'label' => 'Personal / Individual'], ['value' => 'Company', 'label' => 'Company']];
            }
            if ($name === 'profession_id' && in_array($name, memberDefaultFieldNames($section), true) && !$field['field_options'] && tableExists($pdo, 'list_professions')) {
                $options = $pdo->query('SELECT profession_id, name FROM list_professions ORDER BY sort_order, name')->fetchAll();
            }
            if ($name === 'specialty_categories' && in_array($name, memberDefaultFieldNames($section), true) && tableExists($pdo, 'list_services')) {
                $serviceQuery = $pdo->prepare('SELECT service_id, name FROM list_services WHERE profession_id = ? ORDER BY sort_order, name');
                $serviceQuery->execute([(int)$member['profession_id']]);
                $options = $serviceQuery->fetchAll();
            }
            $required = (int)$field['field_required'] ? ' required' : '';
            $placeholder = (string)($field['field_placeholder'] ?? '');
            $help = trim((string)($field['field_sdesc'] ?? ''));
            if ($help === '') $help = trim((string)($field['field_ldesc'] ?? ''));
            $meta = json_decode((string)($field['json_meta'] ?? '{}'), true);
            if (!is_array($meta)) $meta = [];
            $isAboutHeadingField = $section === 'about' && (int)$field['field_id'] === $aboutHeadingFieldId;
            ?>
            <div class="member-form-row <?= e($displayClass) ?> <?= $name === 'specialty_categories' ? 'member-specialty-row' : '' ?> <?= $isAboutHeadingField ? 'member-about-richtext-row' : '' ?>">
                <?php if ($name !== 'specialty_categories' && !$isAboutHeadingField): ?><label for="field-<?= e($name) ?>"><?= e($label ?: ucwords(str_replace('_', ' ', $name))) ?><?= $required ? ' <span>*</span>' : '' ?></label><?php endif; ?>
                <div class="member-form-control">
                    <?php if (in_array($type, ['richtext','richtext_image','richtext_elements','richtext_media'], true)): ?>
                        <?php if ($isAboutHeadingField && $help !== ''): ?><div class="member-about-editor-help">💡 <?= e($help) ?></div><?php endif; ?>
                        <div class="member-richtext-editor">
                            <div class="member-richtext-toolbar" data-richtext-fallback-toolbar>
                                <button type="button" data-command="bold"><b>B</b></button>
                                <button type="button" data-command="italic"><i>I</i></button>
                                <button type="button" data-command="underline"><u>U</u></button>
                                <button type="button" data-command="insertUnorderedList">• List</button>
                                <button type="button" data-command="insertOrderedList">1. List</button>
                            </div>
                            <div class="member-richtext-content" contenteditable="true" data-richtext-editor><?= safeMemberRichText($value) ?></div>
                            <div class="member-richtext-ckeditor" data-ckeditor-editor hidden><?= safeMemberRichText($value) ?></div>
                            <textarea class="member-richtext-value <?= e($inputClass) ?>" id="field-<?= e($name) ?>" name="fields[<?= e($name) ?>]" hidden><?= e($value) ?></textarea>
                        </div>
                    <?php elseif ($type === 'textarea'): ?>
                        <textarea class="<?= e($inputClass) ?>" id="field-<?= e($name) ?>" name="fields[<?= e($name) ?>]" rows="3" placeholder="<?= e($placeholder) ?>"<?= $required ?>><?= e($value) ?></textarea>
                    <?php elseif ($type === 'hidden'): ?>
                        <input type="hidden" name="fields[<?= e($name) ?>]" value="<?= e($value) ?>">
                    <?php elseif ($type === 'yes_no'): ?>
                        <select class="<?= e($inputClass) ?>" id="field-<?= e($name) ?>" name="fields[<?= e($name) ?>]"<?= $required ?>><option value="">Select</option><option value="Yes" <?= $value === 'Yes' ? 'selected' : '' ?>>Yes</option><option value="No" <?= $value === 'No' ? 'selected' : '' ?>>No</option></select>
                    <?php elseif (in_array($type, ['select','radio','checkbox_group','country','state','top_category','years'], true)): ?>
                        <?php if ($type === 'radio' || $type === 'checkbox_group'): ?>
                            <?php if ($name === 'specialty_categories'): ?>
                            <div class="member-specialty-picker" data-limit="<?= e((string)(membershipCategoryLimit(membershipPlanFor($pdo, (int)$member['subscription_id'])) ?? '')) ?>">
                                <div class="member-specialty-heading"><strong><?= e($label ?: 'Select Categories You Specialize In') ?></strong><input type="search" data-specialty-search placeholder="Search By Keyword" aria-label="Search specialty categories"></div>
                                <div class="member-checkbox-options member-specialty-options">
                            <?php else: ?>
                            <div class="member-<?= $type === 'radio' ? 'radio' : 'checkbox' ?>-options">
                            <?php endif; ?>
                                <?php foreach ($options as $optionIndex => $option):
                                    $optionValue = is_array($option) ? (string)($option['value'] ?? $option['service_id'] ?? $option['profession_id'] ?? '') : (string)$option;
                                    $optionLabel = is_array($option) ? (string)($option['label'] ?? $option['name'] ?? '') : (string)$option;
                                    if (trim($optionLabel) === '') continue;
                                    $checked = $type === 'radio' ? $value === $optionValue : in_array($optionValue, preg_split('/\r\n|\r|\n/', $value), true);
                                ?>
                                    <label><input type="<?= $type === 'radio' ? 'radio' : 'checkbox' ?>" name="fields[<?= e($name) ?>]<?= $type === 'checkbox_group' ? '[]' : '' ?>" value="<?= e($optionValue) ?>" <?= $checked ? 'checked' : '' ?><?= $type === 'radio' || $optionIndex === 0 ? $required : '' ?>> <?= e($optionLabel) ?></label>
                                <?php endforeach; ?>
                                <?php if ($name === 'specialty_categories'): ?></div><div class="member-specialty-count" data-specialty-count></div></div><?php else: ?></div><?php endif; ?>
                        <?php else: ?>
                            <select class="<?= e($inputClass) ?>" id="field-<?= e($name) ?>" name="fields[<?= e($name) ?>]"<?= $required ?>><option value="">Select an option</option>
                                <?php foreach ($options as $optionIndex => $option):
                                    $optionValue = is_array($option) ? (string)($option['value'] ?? $option['profession_id'] ?? '') : (string)$option;
                                    $optionLabel = is_array($option) ? (string)($option['label'] ?? $option['name'] ?? '') : (string)$option;
                                    if (trim($optionLabel) === '') continue;
                                ?><option value="<?= e($optionValue) ?>" <?= $value === $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option><?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    <?php elseif ($type === 'file'): ?>
                        <?php $accept = (string)($meta['accept'] ?? (in_array($name, ['profile_photo','profile_logo'], true) ? 'image/jpeg,image/png,image/gif,image/webp' : '.pdf,.jpg,.jpeg,.png,.gif,.webp')); ?>
                        <input class="<?= e($inputClass) ?>" id="field-<?= e($name) ?>" type="file" name="uploads[<?= e($name) ?>]" accept="<?= e($accept) ?>"<?= $required ?>>
                        <?php if ($value !== ''): ?><small>Current file: <a href="<?= e($value) ?>" target="_blank" rel="noopener">View uploaded file</a></small><?php endif; ?>
                    <?php else: ?>
                        <?php $inputType = in_array($type, ['email','url','number','date','datetime-local','tel','phone_country','password','price'], true) ? ($type === 'price' ? 'number' : ($type === 'phone_country' ? 'tel' : $type)) : 'text'; ?>
                        <input class="<?= e($inputClass) ?>" id="field-<?= e($name) ?>" type="<?= e($inputType) ?>" name="fields[<?= e($name) ?>]" value="<?= $type === 'password' ? '' : e($value) ?>" placeholder="<?= e($placeholder) ?>"<?= $required ?>>
                    <?php endif; ?>
                    <?php if ($help !== ''): ?><small><?= e($help) ?></small><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <script src="https://cdn.ckeditor.com/ckeditor5/48.5.1/ckeditor5.umd.js"></script>
        <script>
        (function () {
            function initializeMemberEditors() {
                var library = window.CKEDITOR;
                var targets = document.querySelectorAll('[data-ckeditor-editor]');
                if (!targets.length) return;
                if (!library || !library.ClassicEditor || typeof library.ClassicEditor.create !== 'function') {
                    targets.forEach(function (element) {
                        var wrapper = element.closest('.member-richtext-editor');
                        if (!wrapper) return;
                        var fallback = wrapper.querySelector('[data-richtext-editor]');
                        var fallbackToolbar = wrapper.querySelector('[data-richtext-fallback-toolbar]');
                        var container = wrapper.querySelector('.member-richtext-ckeditor');
                        if (container) container.hidden = true;
                        if (fallback) fallback.hidden = false;
                        if (fallbackToolbar) fallbackToolbar.hidden = false;
                    });
                    return;
                }

                var pluginNames = [
                    'Essentials', 'Paragraph', 'Heading', 'Bold', 'Italic', 'Underline',
                    'Strikethrough', 'FontSize', 'FontFamily', 'FontColor',
                    'FontBackgroundColor', 'Alignment', 'List', 'ListProperties',
                    'Indent', 'IndentBlock', 'Link', 'Table', 'TableToolbar',
                    'HorizontalLine', 'BlockQuote', 'RemoveFormat',
                    'GeneralHtmlSupport', 'ImageBlock', 'ImageToolbar',
                    'ImageCaption', 'ImageStyle', 'ImageResize',
                    'ImageInsertViaUrl', 'MediaEmbed', 'Highlight', 'CodeBlock', 'SourceEditing', 'HtmlEmbed', 'LineHeight'
                ];
                var plugins = pluginNames.map(function (name) { return library[name]; }).filter(function (plugin) {
                    return typeof plugin === 'function';
                });
                var itemPlugins = {
                    heading: 'Heading', fontSize: 'FontSize', fontFamily: 'FontFamily',
                    bold: 'Bold', italic: 'Italic', underline: 'Underline',
                    strikethrough: 'Strikethrough', fontColor: 'FontColor',
                    fontBackgroundColor: 'FontBackgroundColor', removeFormat: 'RemoveFormat',
                    alignment: 'Alignment', numberedList: 'List', bulletedList: 'List',
                    insertTable: 'Table', outdent: 'Indent', indent: 'Indent',
                    horizontalLine: 'HorizontalLine', link: 'Link',
                    insertImageViaUrl: 'ImageInsertViaUrl', mediaEmbed: 'MediaEmbed',
                    blockQuote: 'BlockQuote'
                };
                var toolbarItems = [
                    'undo', 'redo', '|',
                    'bold', 'italic', 'underline', '|',
                    'fontFamily', 'heading', 'lineHeight', '|',
                    'fontColor', 'highlight', 'removeFormat', '|',
                    'alignment', 'numberedList', 'bulletedList', '|',
                    'insertTable', 'outdent', 'indent', 'link',
                    'insertImageViaUrl', 'mediaEmbed', 'htmlEmbed', 'sourceEditing'
                ].filter(function (item) {
                    return item === '|' || !itemPlugins[item] || typeof library[itemPlugins[item]] === 'function';
                });

                targets.forEach(function (element) {
                    if (element.dataset.editorInitializing === '1' || element.dataset.editorReady === '1') return;
                    var wrapper = element.closest('.member-richtext-editor');
                    if (!wrapper) return;
                    var fallback = wrapper.querySelector('[data-richtext-editor]');
                    var fallbackToolbar = wrapper.querySelector('[data-richtext-fallback-toolbar]');
                    var value = wrapper.querySelector('.member-richtext-value');
                    var container = wrapper.querySelector('.member-richtext-ckeditor');
                    var form = wrapper.closest('form');
                    if (!value || !container || !form) return;

                    element.dataset.editorInitializing = '1';
                    container.hidden = false;
                    element.hidden = false;
                    var initialValue = value.value || (fallback ? fallback.innerHTML : element.innerHTML) || element.innerHTML;

                    library.ClassicEditor.create(element, {
                        licenseKey: <?= json_encode((string)(getenv('CKEDITOR_LICENSE_KEY') ?: "eyJhbGciOiJFUzI1NiJ9.eyJleHAiOjE3OTYwODMxOTksImp0aSI6IjFjYTY4ZDY5LTA0YzctNDQ1Zi1iY2QyLTc1YjE3NGJkNzAzMyIsInVzYWdlRW5kcG9pbnQiOiJodHRwczovL3Byb3h5LWV2ZW50LmNrZWRpdG9yLmNvbSIsImRpc3RyaWJ1dGlvbkNoYW5uZWwiOlsiY2xvdWQiLCJkcnVwYWwiLCJzaCJdLCJ3aGl0ZUxhYmVsIjp0cnVlLCJsaWNlbnNlVHlwZSI6InRyaWFsIiwiZmVhdHVyZXMiOlsiKiJdLCJ2YyI6IjM5ZGFjN2UzIn0.d93BmN1ZFDuJtjLutQ8KNRdmIBR6pF886a4ktd4jv-OXCB22ipdbsL90FYeDbKCeWllq4qibKbXD5X7oGavMJQ"), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                        plugins: plugins,
                        toolbar: { items: toolbarItems, shouldNotGroupWhenFull: true },
                        image: { toolbar: ['imageTextAlternative', 'toggleImageCaption', 'imageStyle:inline', 'imageStyle:block', 'imageStyle:side'] },
                        table: { contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'] },
                        fontSize: { options: [9, 11, 13, 'default', 17, 19, 21, 24, 28, 32] },
                        htmlSupport: { allow: [{ name: /.*/, attributes: true, classes: true, styles: true }] }
                    }).then(function (instance) {
                        element.dataset.editorReady = '1';
                        delete element.dataset.editorInitializing;
                        instance.setData(initialValue);
                        if (fallback) fallback.hidden = true;
                        if (fallbackToolbar) fallbackToolbar.hidden = true;
                        container.hidden = false;
                        var editable = instance.ui.getEditableElement();
                        if (editable) {
                            editable.setAttribute('contenteditable', 'true');
                            editable.setAttribute('aria-readonly', 'false');
                            editable.removeAttribute('disabled');
                            editable.removeAttribute('aria-disabled');
                        }
                        if (instance.isReadOnly && typeof instance.disableReadOnlyMode === 'function') {
                            instance.disableReadOnlyMode('member-about-form');
                        }
                        var commandNames = ['bold','italic','underline','strikethrough','fontColor','fontBackgroundColor','alignment','numberedList','bulletedList','insertTable','link','undo','redo'];
                        var missingCommands = commandNames.filter(function (name) { return !instance.commands.get(name); });
                        if (missingCommands.length) console.warn('CKEditor commands unavailable:', missingCommands.join(', '));
                        var lastSynced = value.value;
                        function syncValue() {
                            value.value = instance.getData();
                            lastSynced = value.value;
                        }
                        function syncAutofill() {
                            if (value.value !== lastSynced) instance.setData(value.value);
                        }
                        value.addEventListener('input', syncAutofill);
                        value.addEventListener('change', syncAutofill);
                        instance.model.document.on('change:data', syncValue);
                        form.addEventListener('submit', function () {
                            syncAutofill();
                            syncValue();
                        }, true);
                        wrapper.dataset.ckeditorReady = '1';
                        wrapper.dataset.ckeditorError = '';
                    }).catch(function (error) {
                        delete element.dataset.editorInitializing;
                        container.hidden = true;
                        if (fallback) fallback.hidden = false;
                        if (fallbackToolbar) fallbackToolbar.hidden = false;
                        wrapper.dataset.ckeditorError = String(error && error.message ? error.message : error);
                        console.error('CKEditor initialization failed:', error);
                    });
                });
            }
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeMemberEditors);
            else initializeMemberEditors();
        }());
        </script>
        <script>
        document.querySelectorAll('.member-specialty-picker').forEach(function (picker) {
            var search = picker.querySelector('[data-specialty-search]');
            var options = picker.querySelectorAll('.member-specialty-options label');
            var count = picker.querySelector('[data-specialty-count]');
            function updateCount() {
                var selected = picker.querySelectorAll('.member-specialty-options input:checked').length;
                var limit = picker.dataset.limit === '' ? null : Number(picker.dataset.limit);
                picker.querySelectorAll('input[type=checkbox]').forEach(function (box) { box.disabled = !box.checked && limit !== null && selected >= limit; });
                if (count) count.textContent = 'You Are Listed In ' + selected + ' Categories' + (limit === null ? '' : ' (maximum ' + limit + ')');
            }
            if (search) search.addEventListener('input', function () {
                var term = search.value.trim().toLowerCase();
                options.forEach(function (option) { option.hidden = !option.textContent.toLowerCase().includes(term); });
            });
            picker.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) { checkbox.addEventListener('change', updateCount); });
            updateCount();
        });
        </script>
        <script>
        document.querySelectorAll('.member-richtext-editor').forEach(function (editor) {
            var content = editor.querySelector('[data-richtext-editor]');
            var value = editor.querySelector('.member-richtext-value');
            var form = editor.closest('form');
            if (!content || !value || !form) return;
            editor.querySelectorAll('[data-command]').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (editor.dataset.ckeditorReady === '1') return;
                    content.focus();
                    document.execCommand(button.dataset.command, false, null);
                    value.value = content.innerHTML;
                });
            });
            content.addEventListener('input', function () {
                if (editor.dataset.ckeditorReady !== '1') value.value = content.innerHTML;
            });
            var lastContent = content.innerHTML;
            value.addEventListener('input', function () {
                if (editor.dataset.ckeditorReady !== '1') { content.textContent = value.value; lastContent = content.innerHTML; }
            });
            form.addEventListener('submit', function () {
                if (editor.dataset.ckeditorReady !== '1' && content.innerHTML !== lastContent) value.value = content.innerHTML;
            }, true);
        });
        </script>

        <?php if ($section === 'contact'): ?>
        <script>
        (function () {
            var address = document.getElementById('member-map-address');
            var frame = document.getElementById('member-location-frame');
            if (!address || !frame) return;
            address.addEventListener('change', function () {
                frame.src = 'https://maps.google.com/maps?q=' + encodeURIComponent(address.value.trim()) + '&output=embed';
            });
        }());
        </script>
        <?php endif; ?>
    </form>
    <?php
}
