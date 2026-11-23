<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/public_website.php';

$settings = getPublicWebsiteSettings($pdo);
$pageTitle = 'Website Settings';
$activeMenu = 'website-settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="module-intro">
    <div>
        <h2>Public Website Settings</h2>
        <p>Manage the content and appearance shown on your public directory website.</p>
    </div>
    <a class="btn btn-default" href="<?= e($config['base_url']) ?>/public/index.php" target="_blank" rel="noopener">View Public Website</a>
</div>

<div class="form-card" style="max-width: 900px; padding: 24px;">
    <form method="post" action="<?= e($config['base_url']) ?>/admin/website-settings-save.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

        <h3>Website Branding</h3>
        <label>Website Name</label>
        <input class="form-control" type="text" name="site_name" maxlength="150" required value="<?= e($settings['site_name']) ?>">

        <label>Tagline</label>
        <input class="form-control" type="text" name="tagline" maxlength="255" value="<?= e($settings['tagline']) ?>">

        <label>Logo Image URL or Relative Path</label>
        <input class="form-control" type="text" name="logo_url" maxlength="500" value="<?= e($settings['logo_url']) ?>" placeholder="/MyJustDialNew/public/assets/images/logo.png">

        <h3 style="margin-top: 28px;">Homepage Content</h3>
        <label>Hero Heading</label>
        <input class="form-control" type="text" name="hero_heading" maxlength="255" required value="<?= e($settings['hero_heading']) ?>">

        <label>Hero Description</label>
        <textarea class="form-control" name="hero_description" rows="3"><?= e($settings['hero_description']) ?></textarea>

        <label>Hero Background Image URL or Relative Path</label>
        <input class="form-control" type="text" name="hero_image" maxlength="500" value="<?= e($settings['hero_image']) ?>" placeholder="/MyJustDialNew/public/assets/images/hero.jpg">

        <h3 style="margin-top: 28px;">Colors</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div>
                <label>Button / Accent Color</label>
                <input class="form-control" type="color" name="primary_color" value="<?= e($settings['primary_color']) ?>">
            </div>
            <div>
                <label>Navigation Color</label>
                <input class="form-control" type="color" name="nav_color" value="<?= e($settings['nav_color']) ?>">
            </div>
        </div>

        <h3 style="margin-top: 28px;">Contact Information</h3>
        <label>Contact Phone</label>
        <input class="form-control" type="text" name="contact_phone" maxlength="50" value="<?= e($settings['contact_phone']) ?>">

        <label>Contact Email</label>
        <input class="form-control" type="email" name="contact_email" maxlength="255" value="<?= e($settings['contact_email']) ?>">

        <div style="margin-top: 24px;">
            <button class="btn btn-primary" type="submit">Save Website Settings</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
