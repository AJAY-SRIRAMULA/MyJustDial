<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/widgets.php';
$id = (int)($_GET['id'] ?? 0);
$widget = widgetByReference($pdo,(string)$id);
if (!$widget) { flash('warning','Widget not found.'); redirect('/admin/toolbox/widget-manager/index.php'); }
$pageTitle = 'Edit Widget'; $activeMenu='widgets';
$extraHead='<link rel="stylesheet" href="' . e($config['base_url']) . '/assets/css/widget-manager.css"><link rel="stylesheet" href="' . e($config['base_url']) . '/assets/css/widget-manager-polish.css">';
$shortcode = widgetPublicShortcode($pdo, $widget);
$revisionRows = [];
if (tableExists($pdo, 'users_meta')) {
    $revisionQuery = $pdo->prepare("SELECT meta_id, date_added FROM users_meta WHERE `database`='data_widgets' AND database_id=? AND `key`='widget_revision' ORDER BY meta_id DESC LIMIT 50");
    $revisionQuery->execute([$id]);
    $revisionRows = $revisionQuery->fetchAll(PDO::FETCH_ASSOC);
}
$formatRevisionDate = static function ($value): string {
    $value = trim((string)$value);
    $date = DateTime::createFromFormat('!YmdHis', $value);
    if (!$date) {
        try { $date = new DateTime($value); } catch (Throwable $error) { return $value; }
    }
    return $date->format('d/m/Y H:i:s');
};
$currentRevisionLabel = '';
try { $currentRevisionLabel = (new DateTime((string)($widget['revision_timestamp'] ?? $widget['date_updated'] ?? 'now')))->format('d/m/Y H:i:s'); }
catch (Throwable $error) { $currentRevisionLabel = (string)($widget['revision_timestamp'] ?? 'Current'); }
require __DIR__ . '/../../../includes/header.php';
?>
<div class="wm-page wm-editor-page"><div class="wm-breadcrumb"><a href="index.php">Widget Manager</a> &nbsp;/&nbsp; Edit Widget #<?= $id ?></div>
<div class="wm-top"><h1><?= e($widget['widget_name']) ?> <button type="button" class="wm-title-edit" data-rename-widget title="Rename widget" aria-label="Rename widget"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m4 20 4.5-1 11-11a2 2 0 0 0-3-3l-11 11L4 20Z"/></svg></button></h1><div><a href="index.php?new=1" class="wm-primary">＋ New Widget</a> <form action="index.php" method="get" class="wm-search"><input name="q" placeholder="Search widgets"><button>Search</button></form></div></div>
<form action="actions.php" method="post" id="widgetEditForm"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $id ?>">
<div class="wm-edit-toolbar"><div class="wm-tabs"><button type="button" class="active" data-code-tab="widget_data">HTML</button><button type="button" data-code-tab="widget_style">CSS</button><button type="button" data-code-tab="widget_javascript">Javascript</button></div><div class="wm-tools"><details class="wm-revisions-menu"><summary>Revisions <span class="wm-action-chevron" aria-hidden="true"></span></summary><div class="wm-dropdown wm-revisions-dropdown"><div class="wm-current-revision">Current version · <?= e($currentRevisionLabel) ?></div><?php if ($revisionRows): ?><div class="wm-revision-list"><?php foreach ($revisionRows as $revision): ?><button type="button" data-restore-revision="<?= (int)$revision['meta_id'] ?>" data-widget-id="<?= $id ?>" data-csrf="<?= e(csrfToken()) ?>"><span><?= e($formatRevisionDate($revision['date_added'])) ?></span><small>Restore this version</small></button><?php endforeach; ?></div><?php else: ?><div class="wm-no-revisions">No previous revisions yet. A revision is created each time you save an edit.</div><?php endif; ?><a class="wm-view-history" href="history.php?id=<?= $id ?>">View revision history</a></div></details><details><summary><span aria-hidden="true">♧</span> Shortcode <span class="wm-action-chevron" aria-hidden="true"></span></summary><div class="wm-dropdown"><button type="button" data-copy="<?= e($shortcode) ?>">Copy Shortcode</button><button type="button" data-copy="<?= e('<?= renderWidgetShortcodes($pdo, ' . var_export($shortcode, true) . '); ?>') ?>">PHP Shortcode</button></div></details><a class="wm-outline" href="export.php?id=<?= $id ?>">⇩ Export</a><button type="button" class="wm-butler" data-bd-butler>✦ BD Butler</button></div></div>
<p class="wm-hint" data-hint-html>The HTML box supports HTML, CSS, Javascript and PHP. PHP execution is disabled for security.</p><p class="wm-hint" data-hint-css hidden>The CSS box doesn't need &lt;style&gt; tags inside.</p><p class="wm-hint" data-hint-js hidden>The Javascript box needs &lt;script&gt; tags wrapping the javascript code.</p>
<?php require __DIR__ . '/../../../includes/widget_code_editor.php'; ?>
<label class="wm-type">Type of widget (optional) <select name="widget_type"><option value="Widget" <?= $widget['widget_type']==='Widget'?'selected':'' ?>>Widget (Default)</option><option value="Custom" <?= $widget['widget_type']==='Custom'?'selected':'' ?>>Custom</option></select></label>
<label class="wm-type">Widget name <input name="widget_name" value="<?= e($widget['widget_name']) ?>" required maxlength="255"></label>
</form></div><div class="wm-toast" id="widgetToast" role="status"></div><script src="<?= e($config['base_url']) ?>/assets/js/widget-manager.js?v=20261124-2"></script>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
