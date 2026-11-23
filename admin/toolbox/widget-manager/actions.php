<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/widgets.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
verifyCsrf();
$action = (string)($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);
$return = '/admin/toolbox/widget-manager/index.php';
try {
    if (!tableExists($pdo, 'data_widgets')) throw new RuntimeException('data_widgets table is unavailable.');
    if ($action === 'bulk') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['widget_ids'] ?? [])), static fn($value) => $value > 0)));
        $bulk = (string)($_POST['bulk_action'] ?? '');
        if (!$ids || !in_array($bulk, ['enable','disable','delete'], true)) throw new RuntimeException('Select widgets and a valid bulk action.');
        $pdo->beginTransaction();
        foreach ($ids as $widgetId) {
            if ($bulk === 'delete') {
                $pdo->prepare('DELETE FROM data_widgets_rel WHERE widget_id=?')->execute([(string)$widgetId]);
                if (tableExists($pdo, 'users_meta')) {
                    $pdo->prepare("DELETE FROM users_meta WHERE `database`='data_widgets' AND database_id=?")->execute([$widgetId]);
                }
                $pdo->prepare('DELETE FROM data_widgets WHERE widget_id=?')->execute([$widgetId]);
            } else {
                $pdo->prepare('UPDATE data_widgets SET widget_viewport=?,date_updated=? WHERE widget_id=?')->execute([$bulk === 'enable' ? 'front' : 'disabled',date('Y-m-d H:i:s'),$widgetId]);
            }
        }
        $pdo->commit();
        flash('success','Bulk widget operation completed.');
        redirect($return);
    }
    if ($action === 'create' || $action === 'update') {
        $name = trim((string)($_POST['widget_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 255) throw new RuntimeException('Enter a widget name (up to 255 characters).');
        $type = trim((string)($_POST['widget_type'] ?? 'Widget')) ?: 'Widget';
        $html = (string)($_POST['widget_data'] ?? '');
        $css = (string)($_POST['widget_style'] ?? '');
        $js = (string)($_POST['widget_javascript'] ?? '');
        if (trim($js) !== '' && (!preg_match('/^\s*<script\b[^>]*>.*<\/script>\s*$/is', $js))) {
            throw new RuntimeException('Javascript code must be wrapped in <script> and </script> tags.');
        }
        if (strlen($html) + strlen($css) + strlen($js) > 2000000) throw new RuntimeException('Widget content is too large.');
        $user = (string)($_SESSION['admin_name'] ?? $_SESSION['admin_id']);
        if ($action === 'create') {
            $shortCode = newWidgetShortCode($pdo);
            $query = $pdo->prepare('INSERT INTO data_widgets (widget_name,widget_type,widget_data,widget_style,widget_class,widget_viewport,date_updated,updated_by,short_code,div_id,bootstrap_enabled,ssl_enabled,mobile_enabled,widget_html_element,widget_javascript,file_type,widget_settings,widget_values) VALUES (?,?,?,?,?,?,?,?,?,?,1,1,1,?,?,?,?,?)');
            $query->execute([$name,$type,$html,$css,'','front',date('Y-m-d H:i:s'),$user,$shortCode,'','div',$js,'','','']);
            $id = (int)$pdo->lastInsertId();
            $createdWidget = widgetByReference($pdo, (string)$id);
            $publicShortcode = $createdWidget ? widgetPublicShortcode($pdo, $createdWidget) : '';
            flash('success', 'Widget created. Shortcode: ' . $publicShortcode);
        } else {
            $existing = widgetByReference($pdo, (string)$id);
            if (!$existing) throw new RuntimeException('Widget not found.');
            $pdo->beginTransaction();
            if (tableExists($pdo, 'users_meta')) {
                $history = $pdo->prepare("INSERT INTO users_meta (`database`,database_id,`key`,`value`,date_added) VALUES ('data_widgets',?,'widget_revision',?,?)");
                $history->execute([$id,json_encode([
                    'widget_name'=>$existing['widget_name'],
                    'widget_type'=>$existing['widget_type'],
                    'widget_data'=>$existing['widget_data'],
                    'widget_style'=>$existing['widget_style'],
                    'widget_javascript'=>$existing['widget_javascript']
                ],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),date('YmdHis')]);
            }
            $query = $pdo->prepare('UPDATE data_widgets SET widget_name=?,widget_type=?,widget_data=?,widget_style=?,widget_javascript=?,date_updated=?,updated_by=? WHERE widget_id=?');
            $query->execute([$name,$type,$html,$css,$js,date('Y-m-d H:i:s'),$user,$id]);
            $pdo->commit();
            flash('success', 'Widget saved successfully.');
        }
        redirect('/admin/toolbox/widget-manager/edit.php?id=' . $id);
    }
    $existing = widgetByReference($pdo, (string)$id);
    if (!$existing) throw new RuntimeException('Widget not found.');
    if ($action === 'delete') {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM data_widgets_rel WHERE widget_id = ?')->execute([(string)$id]);
        if (tableExists($pdo, 'users_meta')) {
            $pdo->prepare("DELETE FROM users_meta WHERE `database`='data_widgets' AND database_id=?")->execute([$id]);
        }
        $pdo->prepare('DELETE FROM data_widgets WHERE widget_id = ?')->execute([$id]);
        $pdo->commit();
        flash('success', 'Widget deleted. Existing shortcode references will display unavailable.');
    } elseif ($action === 'toggle') {
        $next = $existing['widget_viewport'] === 'disabled' ? 'front' : 'disabled';
        $pdo->prepare('UPDATE data_widgets SET widget_viewport=?,date_updated=? WHERE widget_id=?')->execute([$next,date('Y-m-d H:i:s'),$id]);
        flash('success', $next === 'disabled' ? 'Widget disabled.' : 'Widget enabled.');
    } elseif ($action === 'clone') {
        $newCode = newWidgetShortCode($pdo);
        $columns = ['widget_name','widget_type','widget_data','widget_style','widget_class','widget_viewport','date_updated','updated_by','short_code','div_id','bootstrap_enabled','ssl_enabled','mobile_enabled','widget_html_element','widget_javascript','file_type','widget_settings','widget_values'];
        $values = array_map(static fn($column) => $existing[$column], $columns);
        $values[0] .= ' (Copy)'; $values[6] = date('Y-m-d H:i:s'); $values[8] = $newCode;
        $pdo->prepare('INSERT INTO data_widgets (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0,count($columns),'?')) . ')')->execute($values);
        $newId = (int)$pdo->lastInsertId();
        $clonedWidget = widgetByReference($pdo, (string)$newId);
        if ($clonedWidget) widgetPublicShortcode($pdo, $clonedWidget);
        flash('success', 'Widget cloned.');
    } else throw new RuntimeException('Unknown widget action.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('warning', $error->getMessage());
    if (($action === 'update') && $id > 0) redirect('/admin/toolbox/widget-manager/edit.php?id=' . $id);
}
redirect($return);
