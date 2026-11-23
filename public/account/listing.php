<?php
$section = 'listing';
require __DIR__ . '/_bootstrap.php';
memberAccountHeader($member, $section, $config, $isAdminPreview);
?>
<div class="member-section-caption">Basic Information</div>
<section class="member-form-card">
    
    <?php renderMemberConfiguredForm($pdo, $member, 'member_listing_details', 'listing', csrfToken()); ?>
</section>
<?php memberAccountFooter($config); ?>
