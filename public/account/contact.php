<?php
$section = 'contact';
require __DIR__ . '/_bootstrap.php';
memberAccountHeader($member, $section, $config, $isAdminPreview);
?>
<div class="member-section-caption">Basic Information</div>
<section class="member-form-card">
    
    <?php renderMemberConfiguredForm($pdo, $member, 'member_contact_details', 'contact', csrfToken()); ?>
</section>
<?php memberAccountFooter($config); ?>
