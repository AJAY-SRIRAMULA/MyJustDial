<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/widgets.php';
$id = (int)($_REQUEST['id'] ?? 0);
$widget = widgetByReference($pdo, (string)$id);
if (!$widget) { flash('warning','Widget not found.'); redirect('/admin/toolbox/widget-manager/index.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $revision = (int)($_POST['revision'] ?? 0);
    try {
        if (!tableExists($pdo, 'users_meta')) throw new RuntimeException('Revision storage unavailable.');
        $query = $pdo->prepare("SELECT value FROM users_meta WHERE meta_id=? AND `database`='data_widgets' AND database_id=? AND `key`='widget_revision'");
        $query->execute([$revision, $id]);
        $data = json_decode((string)$query->fetchColumn(), true);
        if (!is_array($data) || !isset($data['widget_name'],$data['widget_type'],$data['widget_data'],$data['widget_style'],$data['widget_javascript'])) throw new RuntimeException('Revision unavailable.');
        $pdo->beginTransaction();
        $snapshot = $pdo->prepare("INSERT INTO users_meta (`database`,database_id,`key`,`value`,date_added) VALUES ('data_widgets',?,'widget_revision',?,?)");
        $snapshot->execute([$id,json_encode(array_intersect_key($widget,array_flip(['widget_name','widget_type','widget_data','widget_style','widget_javascript'])),JSON_UNESCAPED_UNICODE),date('YmdHis')]);
        $update = $pdo->prepare('UPDATE data_widgets SET widget_name=?,widget_type=?,widget_data=?,widget_style=?,widget_javascript=?,date_updated=? WHERE widget_id=?');
        $update->execute([$data['widget_name'],$data['widget_type'],$data['widget_data'],$data['widget_style'],$data['widget_javascript'],date('Y-m-d H:i:s'),$id]);
        $pdo->commit();
        flash('success','Widget revision restored.');
        redirect('/admin/toolbox/widget-manager/edit.php?id='.$id);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('warning',$error->getMessage());
        redirect('/admin/toolbox/widget-manager/edit.php?id='.$id);
    }
}
$formatRevisionDate = static function ($value): string {
    $value = trim((string)$value);
    $date = DateTime::createFromFormat('!YmdHis', $value);
    if (!$date) {
        try { $date = new DateTime($value); } catch (Throwable $error) { return $value; }
    }
    return $date->format('d/m/Y H:i:s');
};
$revisions = [];
if (tableExists($pdo,'users_meta')) {
    $query = $pdo->prepare("SELECT meta_id,date_added FROM users_meta WHERE `database`='data_widgets' AND database_id=? AND `key`='widget_revision' ORDER BY meta_id DESC LIMIT 100");
    $query->execute([$id]); $revisions = $query->fetchAll();
}
$pageTitle='Widget Save History'; $activeMenu='widgets';
$extraHead='<link rel="stylesheet" href="'.e($config['base_url']).'/assets/css/widget-manager.css"><link rel="stylesheet" href="'.e($config['base_url']).'/assets/css/widget-manager-polish.css">';
require __DIR__ . '/../../../includes/header.php';
?>
<div class="wm-page"><div class="wm-breadcrumb"><a href="index.php">Widget Manager</a> / <a href="edit.php?id=<?= $id ?>"><?= e($widget['widget_name']) ?></a> / Revisions</div>
<h1>Widget Save History</h1><p>Restore a previous saved version. Current widget data is saved as a revision before restoration.</p>
<section class="wm-panel"><table class="wm-table"><thead><tr><th>Revision</th><th>Saved at</th><th>Action</th></tr></thead><tbody>
<?php foreach ($revisions as $revision): ?><tr><td>#<?= (int)$revision['meta_id'] ?></td><td><?= e($formatRevisionDate($revision['date_added'])) ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= (int)$revision['meta_id'] ?>"><button class="wm-primary" onclick="return confirm('Restore this revision?')" type="submit">Restore</button></form></td></tr><?php endforeach; ?>
<?php if (!$revisions): ?><tr><td colspan="3">No previous revisions. Revisions are recorded when widgets are saved.</td></tr><?php endif; ?>
</tbody></table></section></div>
<div class="wm-toast" id="widgetToast" role="status"></div><script src="<?= e($config['base_url']) ?>/assets/js/widget-manager.js"></script>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
