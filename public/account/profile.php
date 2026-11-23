<?php
$section = 'profile';
require __DIR__ . '/_bootstrap.php';
memberAccountHeader($member, $section, $config, $isAdminPreview);
?>
<div class="member-section-caption">Basic Information</div>
<section class="member-form-card">
    <p class="member-accepted">ACCEPTED FORMATS: JPG, GIF, PNG &amp; WEBP. Maximum 5 MB per image.</p>
    <?php renderMemberConfiguredForm($pdo, $member, 'member_profile_photo', 'profile', csrfToken()); ?>
</section>
<?php memberAccountFooter($config); ?>
