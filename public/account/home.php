<?php
$section = 'home';
require __DIR__ . '/_bootstrap.php';

$completionFields = ['first_name', 'last_name', 'email', 'phone_number', 'company', 'city', 'website', 'about_me'];
$completedFields = 0;
foreach ($completionFields as $completionField) {
    if (trim((string)($member[$completionField] ?? '')) !== '') {
        $completedFields++;
    }
}
if ($profileImage !== '') {
    $completedFields++;
}
$progress = (int)round(($completedFields / (count($completionFields) + 1)) * 100);
$publicListingUrl = $config['base_url'] . '/public/member.php?id=' . (int)$member['user_id'];
$qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=' . rawurlencode($publicListingUrl);
$badgeImageUrl = $config['base_url'] . '/public/assets/images/memberbadge.svg';
$badgeCode = '<a href="' . $publicListingUrl . '" target="_blank" rel="noopener"><img src="' . $badgeImageUrl . '" alt="Find me on Brilliant Directories Demo" width="180"></a>';
$joinedDate = !empty($member['signup_date']) ? DateTime::createFromFormat('YmdHis', (string)$member['signup_date']) : false;

memberAccountHeader($member, $section, $config, $isAdminPreview);
?>
<h1 class="member-account-title"><i class="fa fa-home"></i> My Dashboard</h1>

<section class="member-progress-card">
    <div>Your listing is <?= $progress ?>% Complete</div>
    <div class="member-progress"><span style="width:<?= $progress ?>%"><?= $progress ?>%</span></div>
</section>

<div class="member-dashboard-cards">
    <section class="member-manage-card">
        <h2><i class="fa fa-id-card-o"></i> Manage Listing</h2>
        <a href="<?= e($config['base_url']) ?>/public/account/contact.php">Basic Information</a>
        <a href="<?= e($config['base_url']) ?>/public/account/profile.php">Profile Photo</a>
        <a href="<?= e($config['base_url']) ?>/public/account/listing.php">Additional Details</a>
        <a href="<?= e($config['base_url']) ?>/public/account/about.php">About Me</a>
    </section>

    <section class="member-details-card">
        <h2><i class="fa fa-cog"></i> Account Details</h2>
        <div class="member-account-detail-plan"><?= e((string)($member['subscription_name'] ?? 'Membership Plan')) ?></div>
        <p><i class="fa fa-arrow-circle-right"></i> Status: <strong><?= isActiveMemberRecord($member['active']) ? 'Active' : ((int)$member['active'] === 0 ? 'Inactive' : 'Pending / Other') ?></strong></p>
        <p><i class="fa fa-arrow-circle-right"></i> Join Date: <strong><?= $joinedDate ? e($joinedDate->format('d/m/Y')) : '—' ?></strong></p>
        <a href="<?= e($config['base_url']) ?>/public/login.php?logout=1"><i class="fa fa-arrow-circle-right"></i> Logout</a>
    </section>

    <section class="member-qr-card">
        <h2><i class="fa fa-bullhorn"></i> Free QR Code Image</h2>
        <p>Links to your public listing</p>
        <a class="member-qr-link" href="<?= e($publicListingUrl) ?>" target="_blank" rel="noopener">
            <img src="<?= e($qrImageUrl) ?>" alt="QR code linking to <?= e(trim($member['first_name'] . ' ' . $member['last_name'])) ?>'s public listing" loading="lazy">
        </a>
        <p>Use the QR code image to attract people directly to your listing</p>
    </section>
</div>

<section class="member-badge-card">
    <h2>Attract More Clients<br>Add This To Your Website</h2>
    <p>Increases Trust &amp; Credibility</p>
    <img class="member-badge-image" src="<?= e($badgeImageUrl) ?>" alt="Brilliant Directories member badge">
    <label for="member-badge-code">Copy and paste the code below into your website's source code</label>
    <textarea id="member-badge-code" readonly><?= e($badgeCode) ?></textarea>
    <button type="button" class="member-copy-badge" onclick="var field=document.getElementById('member-badge-code');field.select();field.setSelectionRange(0,field.value.length);if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(field.value);}else{document.execCommand('copy');}this.textContent='Copied';">Copy Badge Code</button>
</section>

<?php memberAccountFooter($config); ?>
