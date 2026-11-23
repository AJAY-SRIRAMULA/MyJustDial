<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
$pageTitle = 'Developer Hub';
$activeMenu = 'developer-hub';
$extraHead = <<<'HTML'
<style>
.devhub-wrap{max-width:1200px;margin:36px auto;padding:0 24px}.devhub-wrap h1{font-size:30px;margin:0 0 8px;color:#111}.devhub-wrap .subtitle{font-size:16px;color:#555;margin-bottom:26px}.devhub-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.devhub-card{background:#fff;border:1px solid #ddd;border-radius:15px;padding:24px;min-height:178px;box-shadow:0 2px 5px rgba(0,0,0,.04);display:flex;flex-direction:column;position:relative}.devhub-card h2{font-size:19px;margin:0 64px 8px 0;color:#111}.devhub-card p{font-size:15px;line-height:1.55;color:#555;margin:0 45px 20px 0;flex:1}.devhub-icon{position:absolute;right:20px;top:20px;width:50px;height:50px;border-radius:14px;background:#eaf5ee;border:1px solid #cce5d4;color:#15803d;display:flex;align-items:center;justify-content:center;font-size:22px}.devhub-icon.amber{background:#fff5e4;border-color:#f1d7a7;color:#a16207}.devhub-icon.blue{background:#eaf2ff;border-color:#c9dcf8;color:#2563eb}.devhub-btn{display:inline-flex;align-items:center;align-self:flex-start;border:1px solid #d5d5d5;background:#fff;border-radius:9px;padding:10px 14px;color:#111;font-weight:600;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,.04)}.devhub-btn:hover{background:#f5f5f5}.devhub-btn.static{cursor:default}.devhub-btn.db{cursor:pointer}@media(max-width:1000px){.devhub-grid{grid-template-columns:repeat(2,minmax(0,1fr)}}@media(max-width:650px){.devhub-grid{grid-template-columns:1fr}.devhub-wrap{padding:0 14px}}
</style>
HTML;
require __DIR__ . '/../includes/header.php';
?>
<section class="devhub-wrap">
    <h1>Developer Hub</h1>
    <p class="subtitle">Advanced tools for your website and integrations.</p>
    <div class="devhub-grid">
        <article class="devhub-card"><span class="devhub-icon"><i class="fa fa-key"></i></span><h2>Manage API Keys</h2><p>Activate API credentials so other apps can connect to your website.</p><span class="devhub-btn static">Manage API Keys</span></article>
        <article class="devhub-card"><span class="devhub-icon"><i class="fa fa-link"></i></span><h2>Manage Webhooks</h2><p>Activate and edit webhook links that send website events to other apps.</p><span class="devhub-btn static">Manage Webhooks</span></article>
        <article class="devhub-card"><span class="devhub-icon"><i class="fa fa-random"></i></span><h2>301 Redirect Rules</h2><p>Create and manage 301 redirect rules for web pages.</p><span class="devhub-btn static">Manage Redirects</span></article>
        <article class="devhub-card"><span class="devhub-icon amber"><i class="fa fa-picture-o"></i></span><h2>Image Settings</h2><p>Set the maximum dimensions for member image uploads.</p><span class="devhub-btn static">Edit Image Sizes</span></article>
        <article class="devhub-card"><span class="devhub-icon amber"><i class="fa fa-search"></i></span><h2>Autosuggest Settings</h2><p>Manage predictive keyword settings as visitors type in the search bar.</p><span class="devhub-btn static">Manage Autosuggest</span></article>
        <article class="devhub-card"><span class="devhub-icon amber"><i class="fa fa-file-text-o"></i></span><h2>Robots.txt File</h2><p>Customize or reset the website's Robots.txt file.</p><span class="devhub-btn static">Edit Robots.txt</span></article>
        <article class="devhub-card"><span class="devhub-icon blue"><i class="fa fa-terminal"></i></span><h2>HTACCESS File</h2><p>Customize or reset the website's HTACCESS file.</p><span class="devhub-btn static">Edit HTACCESS</span></article>
        <article class="devhub-card"><span class="devhub-icon blue"><i class="fa fa-database"></i></span><h2>MySQL Database</h2><p>Access and manage the website's MySQL database.</p><a class="devhub-btn db" href="http://localhost/phpmyadmin/index.php?route=/database/structure&amp;db=launc58415_directory" target="_blank" rel="noopener">Open Database <i class="fa fa-external-link" style="margin-left:8px"></i></a></article>
        <article class="devhub-card"><span class="devhub-icon blue"><i class="fa fa-server"></i></span><h2>cPanel Dashboard</h2><p>Access the website's cPanel dashboard and tools.</p><span class="devhub-btn static">Open cPanel <i class="fa fa-external-link" style="margin-left:8px"></i></span></article>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
