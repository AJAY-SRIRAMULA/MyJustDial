<?php
$section = 'about';
require __DIR__ . '/_bootstrap.php';
memberAccountHeader($member, $section, $config, $isAdminPreview);
?>
<div class="member-section-caption">Basic Information</div>
<section class="member-form-card">
    
    <?php renderMemberConfiguredForm($pdo, $member, 'about', 'about', csrfToken()); ?>
</section>
<?php memberAccountFooter($config); ?>
