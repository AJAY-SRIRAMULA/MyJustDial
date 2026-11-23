<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/widgets.php';
$pageTitle = 'Widget Manager';
$activeMenu = 'widgets';
$extraHead = '<link rel="stylesheet" href="' . e($config['base_url']) . '/assets/css/widget-manager.css"><link rel="stylesheet" href="' . e($config['base_url']) . '/assets/css/widget-manager-polish.css">';
if (tableExists($pdo, 'data_widgets')) ensureWidgetShortcodeMappings($pdo);
$search = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? 'all');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = in_array((int)($_GET['limit'] ?? 25), [10, 25, 50, 100], true) ? (int)($_GET['limit'] ?? 25) : 25;
$sort = (string)($_GET['sort'] ?? 'updated');
$direction = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$sortColumns = ['id' => 'widget_id', 'name' => 'widget_name', 'status' => 'widget_viewport', 'created' => 'date_updated', 'updated' => 'revision_timestamp'];
if (!isset($sortColumns[$sort])) $sort = 'updated';
$order = $sortColumns[$sort] . ' ' . strtoupper($direction) . ', widget_id DESC';
$sortLink = static function (string $field) use ($sort, $direction): string {
    $query = $_GET;
    $query['sort'] = $field;
    $query['dir'] = $sort === $field && $direction === 'asc' ? 'desc' : 'asc';
    $query['page'] = 1;
    return '?' . http_build_query($query);
};
$sortArrows = static function (string $field) use ($sort, $direction): string {
    $active = $sort === $field;
    return '<span class="wm-sort-arrows" aria-hidden="true"><span class="' . ($active && $direction === 'asc' ? 'is-active' : '') . '">↑</span><span class="' . ($active && $direction === 'desc' ? 'is-active' : '') . '">↓</span></span>';
};
$formatWidgetDate = static function ($value): array {
    if (!$value) return ['—', ''];
    try { $date = new DateTime((string)$value); return [$date->format('M j, Y'), $date->format('g:i a')]; }
    catch (Throwable $error) { return [(string)$value, '']; }
};
$rows = [];
$total = 0;
if (tableExists($pdo, 'data_widgets')) {
    $where = ' WHERE 1=1';
    $params = [];
    if ($search !== '') { $where .= " AND (widget_name LIKE ? OR short_code LIKE ? OR EXISTS (SELECT 1 FROM users_meta wm WHERE wm.`database`='data_widgets' AND wm.database_id=data_widgets.widget_id AND wm.`key`='widget_shortcode' AND wm.value LIKE ?))"; $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%'; }
    if ($status === 'enabled') $where .= " AND widget_viewport <> 'disabled'";
    if ($status === 'disabled') $where .= " AND widget_viewport = 'disabled'";
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date'] ?? ''))) { $where .= ' AND revision_timestamp >= ?'; $params[] = (string)$_GET['date'] . ' 00:00:00'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? ''))) { $where .= ' AND revision_timestamp < DATE_ADD(?, INTERVAL 1 DAY)'; $params[] = (string)$_GET['date_to']; }
    $count = $pdo->prepare('SELECT COUNT(*) FROM data_widgets' . $where);
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / $limit));
    if ($page > $pages) $page = $pages;
    $query = $pdo->prepare('SELECT * FROM data_widgets' . $where . ' ORDER BY ' . $order . ' LIMIT ' . $limit . ' OFFSET ' . (($page - 1) * $limit));
    $query->execute($params);
    $rows = $query->fetchAll();
} else {
    $pages = 1;
}
$baseQuery = ['q' => $search, 'status' => $status, 'sort' => $sort, 'dir' => $direction, 'limit' => $limit];
if (!empty($_GET['date'])) $baseQuery['date'] = (string)$_GET['date'];
if (!empty($_GET['date_to'])) $baseQuery['date_to'] = (string)$_GET['date_to'];
$pageUrl = static function (int $target) use ($baseQuery): string { return '?' . http_build_query($baseQuery + ['page' => $target]); };
require __DIR__ . '/../../../includes/header.php';
?>
<div class="wm-page">
  <div class="wm-alert"><span class="wm-alert-icon" aria-hidden="true">ⓘ</span><span>Customizing widgets affects your website. Disable widgets to troubleshoot issues.</span></div>
  <div class="wm-top"><div><h1>Widget Manager</h1><p>Customize the widgets your website uses, or create your own.</p></div><button class="wm-primary" type="button" data-open-widget>＋ New Widget</button></div>
  <section class="wm-panel" aria-label="Custom widgets">
    <form class="wm-filters" method="get" id="widgetFilters">
      <div class="wm-filter-combo wm-status-search">
        <select name="status" aria-label="Widget status">
          <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Any status</option>
          <option value="enabled" <?= $status === 'enabled' ? 'selected' : '' ?>>Enabled</option>
          <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <span class="wm-filter-divider" aria-hidden="true"></span>
        <span class="wm-search-icon" aria-hidden="true">⌕</span>
        <input class="wm-search-input" name="q" placeholder="Search" value="<?= e($search) ?>" aria-label="Search widgets">
      </div>
      <div class="wm-filter-combo wm-date-combo">
        <select class="wm-date-type" aria-label="Sort widgets by" name="sort">
          <option value="updated" <?= $sort === 'updated' ? 'selected' : '' ?>>Updated</option>
          <option value="created" <?= $sort === 'created' ? 'selected' : '' ?>>Created</option>
          <option value="id" <?= $sort === 'id' ? 'selected' : '' ?>>ID</option>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option>
          <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
        </select>
        <span class="wm-filter-divider" aria-hidden="true"></span>
        <details class="wm-date-range" data-date-range>
          <summary><span aria-hidden="true">▦</span><span data-date-label><?= !empty($_GET['date']) || !empty($_GET['date_to']) ? e((string)($_GET['date'] ?? '')) . (!empty($_GET['date_to']) ? ' – ' . e((string)$_GET['date_to']) : '') : 'Date range' ?></span></summary>
          <div class="wm-date-popover">
            <label>From<input type="date" name="date" value="<?= e((string)($_GET['date'] ?? '')) ?>"></label>
            <label>To<input type="date" name="date_to" value="<?= e((string)($_GET['date_to'] ?? '')) ?>"></label>
            <div><button type="button" data-clear-dates>Clear</button><button type="submit">Apply</button></div>
          </div>
        </details>
      </div>
      <input type="hidden" name="dir" value="<?= e($direction) ?>">
      <input type="hidden" name="limit" value="<?= $limit ?>">
      <span class="wm-count"><?= $total ?> records</span>
      <button type="button" class="wm-icon-button" data-columns-toggle title="Show or hide table columns" aria-label="Show or hide columns">▥</button>
      <button type="button" class="wm-icon-button" data-table-density title="Change table density" aria-label="Change table density">☷</button>
    </form>
    <div class="wm-column-menu" id="widgetColumnMenu" hidden>
      <strong>Visible columns</strong>
      <?php foreach (['name' => 'Custom Widgets', 'status' => 'Status', 'created' => 'Created', 'updated' => 'Updated'] as $columnKey => $columnLabel): ?>
        <label><input type="checkbox" data-visible-column="<?= e($columnKey) ?>" checked> <?= e($columnLabel) ?></label>
      <?php endforeach; ?>
    </div>
    <form id="bulkWidgetForm" action="actions.php" method="post" class="wm-bulk" hidden>
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="bulk">
      <span data-selected-count>0 selected</span>
      <select name="bulk_action" required aria-label="Bulk action"><option value="">Bulk actions</option><option value="enable">Enable selected</option><option value="disable">Disable selected</option><option value="delete">Delete selected</option></select>
      <button type="submit">Apply</button>
    </form>
    <div class="wm-table-wrap">
      <table class="wm-table">
        <colgroup><col class="wm-col-select"><col class="wm-col-id"><col class="wm-col-actions"><col class="wm-col-name"><col class="wm-col-status"><col class="wm-col-created"><col class="wm-col-updated"></colgroup>
        <thead><tr>
          <th class="wm-th-select"><input type="checkbox" data-widget-select-all aria-label="Select all widgets"></th>
          <th colspan="2"><a class="wm-sort-heading <?= $sort === 'id' ? 'wm-sort-active' : '' ?>" href="<?= e($sortLink('id')) ?>" aria-label="Sort by ID">ID<?= $sortArrows('id') ?></a></th>
          <?php foreach (['name' => 'CUSTOM WIDGETS', 'status' => 'STATUS', 'created' => 'CREATED', 'updated' => 'UPDATED'] as $column => $title): ?>
          <th class="wm-column-<?= e($column) ?>"><a class="wm-sort-heading <?= $sort === $column ? 'wm-sort-active' : '' ?>" href="<?= e($sortLink($column)) ?>" aria-label="Sort by <?= e($title) ?>"><?= e($title) ?><?= $sortArrows($column) ?></a></th>
          <?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $widget):
          $id = (int)$widget['widget_id'];
          $enabled = $widget['widget_viewport'] !== 'disabled';
          $shortcode = widgetPublicShortcode($pdo, $widget);
          $createdDate = $formatWidgetDate($widget['date_updated'] ?? '');
          $updatedDate = $formatWidgetDate($widget['revision_timestamp'] ?? $widget['date_updated'] ?? '');
        ?>
          <tr>
            <td><input type="checkbox" form="bulkWidgetForm" name="widget_ids[]" value="<?= $id ?>" data-widget-select aria-label="Select widget <?= $id ?>"></td>
            <td class="wm-id-cell"><?= $id ?></td>
            <td class="wm-actions">
              <a class="wm-black" href="edit.php?id=<?= $id ?>">Edit</a>
              <details class="wm-row-actions" data-row-actions>
                <summary>Actions <span class="wm-action-chevron" aria-hidden="true"></span></summary>
                <div class="wm-dropdown">
                  <button type="button" data-copy="<?= e($shortcode) ?>">Copy Shortcode</button>
                  <form action="actions.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= $id ?>">
                    <button name="action" value="clone">Clone Widget</button>
                    <button type="button" data-export-widget="<?= $id ?>">Export Widget</button>
                    <button type="button" data-history-widget="<?= $id ?>">View History</button>
                    <button name="action" value="toggle"><?= $enabled ? 'Disable' : 'Enable' ?> Widget</button>
                    <button name="action" value="delete" onclick="return confirm('Delete this widget?')">Delete Widget</button>
                  </form>
                </div>
              </details>
            </td>
            <td class="wm-name-cell wm-column-name"><a href="edit.php?id=<?= $id ?>"><?= e($widget['widget_name']) ?></a></td>
            <td class="wm-column-status"><span class="wm-status <?= $enabled ? '' : 'off' ?>"><?= $enabled ? 'Enabled' : 'Disabled' ?></span></td>
            <td class="wm-column-created"><span><?= e($createdDate[0]) ?></span><small><?= e($createdDate[1]) ?></small></td>
            <td class="wm-updated-cell wm-column-updated"><span class="wm-date-stack"><span><?= e($updatedDate[0]) ?></span><small><?= e($updatedDate[1]) ?></small></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="wm-empty">No widgets found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <div class="wm-pages">
    <div class="wm-page-summary">Showing <?= $total ? (($page - 1) * $limit + 1) : 0 ?>–<?= min($page * $limit, $total) ?> of <?= $total ?> <label>Rows per page <select data-page-size aria-label="Rows per page"><?php foreach ([10, 25, 50, 100] as $size): ?><option value="<?= $size ?>" <?= $size === $limit ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></label></div>
    <nav class="wm-pagination" aria-label="Widget pages"><a class="<?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page > 1 ? e($pageUrl($page - 1)) : '#' ?>" aria-label="Previous page">‹</a><span class="wm-current-page"><?= $page ?></span><a class="<?= $page >= $pages ? 'is-disabled' : '' ?>" href="<?= $page < $pages ? e($pageUrl($page + 1)) : '#' ?>" aria-label="Next page">›</a></nav>
  </div>
  <section class="wm-panel wm-default"><h2>Default Widgets</h2><p>Default widget templates are managed by Brilliant Directories. This local database contains <?= $total ?> widget record(s); no additional default templates were supplied.</p></section>
</div>
<dialog class="wm-modal" id="newWidgetDialog"><form action="actions.php" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="create"><header><strong>New Widget</strong><div><button type="button" data-close-widget>Cancel</button><button class="wm-primary" type="submit">Save</button></div></header><div class="wm-modal-body"><label>Widget Name <b>*</b><input name="widget_name" placeholder="Enter widget name" required maxlength="255"></label><div class="wm-tabs" role="tablist"><button type="button" class="active" data-code-tab="widget_data">HTML</button><button type="button" data-code-tab="widget_style">CSS</button><button type="button" data-code-tab="widget_javascript">Javascript</button></div><p class="wm-hint">The HTML box supports HTML, CSS, Javascript and PHP. PHP execution is disabled for security.</p><?php $widget=[]; require __DIR__ . '/../../../includes/widget_code_editor.php'; ?><label>Type of widget (optional)<select name="widget_type"><option>Widget</option><option>Custom</option></select></label></div></form></dialog>
<div class="wm-toast" id="widgetToast" role="status"></div><script src="<?= e($config['base_url']) ?>/assets/js/widget-manager.js"></script>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
