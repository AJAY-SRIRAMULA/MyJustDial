<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/widgets.php';

$demoName = 'Day 4 Hello World To-Do Widget';
$lookup = $pdo->prepare('SELECT * FROM data_widgets WHERE widget_name=? LIMIT 1');
$lookup->execute([$demoName]);
$widget = $lookup->fetch(PDO::FETCH_ASSOC);

$helloHtml = '<div style="padding:20px;font-family:Arial"><h2>Hello World!</h2><p>This custom widget was retrieved from the database.</p></div>';
$todoHtml = '<div class="day4-todo"><h2>My To-Do List</h2><form class="todo-form"><input class="todo-input" type="text" placeholder="Add a task..." aria-label="Task"><button type="submit">Add</button></form><ul class="todo-list"></ul><p class="todo-empty">No tasks yet.</p></div>';
$todoCss = '.day4-todo{font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:20px;border:1px solid #ddd;border-radius:12px;background:#fff;color:#222}.day4-todo h2{margin:0 0 16px;font-size:22px}.todo-form{display:flex;gap:8px}.todo-input{flex:1;min-width:0;padding:10px;border:1px solid #ccc;border-radius:8px}.todo-form button{padding:10px 16px;background:#087568;color:white;border:0;border-radius:8px;cursor:pointer}.todo-list{padding:0;margin:16px 0;list-style:none}.todo-list li{display:flex;align-items:center;gap:9px;padding:10px 0;border-bottom:1px solid #eee}.todo-list li span{flex:1}.todo-list li.done span{text-decoration:line-through;color:#777}.todo-list button{border:0;background:#eee;padding:6px 9px;border-radius:6px;cursor:pointer}.todo-empty{color:#777;font-size:13px}.todo-list:empty{display:none}';
$todoJs = '(function(){const root=document.querySelector(".day4-todo");if(!root)return;const form=root.querySelector(".todo-form"),input=root.querySelector(".todo-input"),list=root.querySelector(".todo-list"),empty=root.querySelector(".todo-empty");form.addEventListener("submit",e=>{e.preventDefault();const text=input.value.trim();if(!text)return;const li=document.createElement("li"),check=document.createElement("input");check.type="checkbox";const span=document.createElement("span");span.textContent=text;check.addEventListener("change",()=>li.classList.toggle("done",check.checked));const remove=document.createElement("button");remove.type="button";remove.textContent="Remove";remove.addEventListener("click",()=>{li.remove();empty.hidden=list.children.length>0});li.append(check,span,remove);list.append(li);input.value="";empty.hidden=true});})();';

if (!$widget) {
    $short = newWidgetShortCode($pdo);
    $now = date('YmdHis');
    $insert = $pdo->prepare('INSERT INTO data_widgets (widget_name,widget_type,widget_data,widget_style,widget_class,widget_viewport,date_updated,updated_by,short_code,div_id,bootstrap_enabled,ssl_enabled,mobile_enabled,widget_html_element,widget_javascript,file_type,widget_settings,widget_values) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $insert->execute([$demoName,'Custom Widget',$helloHtml,'','day4-demo','front',$now,'Day4 Practical',$short,'day4-demo',1,1,1,'div','', 'html','','']);
    $widgetId=(int)$pdo->lastInsertId();
    $widget=['widget_id'=>$widgetId,'widget_name'=>$demoName,'short_code'=>$short];
    setWidgetMetaValue($pdo,$widgetId,'widget_shortcode','day4_hello_world');
    setWidgetMetaValue($pdo,$widgetId,'widget_revision',json_encode(['widget_name'=>$demoName,'widget_type'=>'Custom Widget','widget_data'=>$helloHtml,'widget_style'=>'','widget_javascript'=>''],JSON_UNESCAPED_SLASHES));
}
if (isset($_GET['upgrade']) && $_GET['upgrade']==='todo') {
    $update=$pdo->prepare('UPDATE data_widgets SET widget_data=?,widget_style=?,widget_javascript=?,date_updated=?,updated_by=? WHERE widget_id=?');
    $update->execute([$todoHtml,$todoCss,$todoJs,date('YmdHis'),'Day4 Practical',(int)$widget['widget_id']]);
    setWidgetMetaValue($pdo,(int)$widget['widget_id'],'widget_shortcode','day4_hello_world');
    $widget['widget_data']=$todoHtml;$widget['widget_style']=$todoCss;$widget['widget_javascript']=$todoJs;
    $message='The Hello World widget has been upgraded to the interactive To-Do List.';
}
$widget=widgetByReference($pdo,'day4_hello_world');
$shortcode='[widget=day4_hello_world]';
require __DIR__ . '/includes/header.php';
?>
<div style="max-width:900px;margin:30px auto;padding:20px">
<h1>Day 4 – Custom Widget Practical</h1>
<p>This demonstration creates a custom widget in the existing <code>data_widgets</code> table, retrieves it using its readable shortcode, and renders it below.</p>
<p><strong>Shortcode:</strong> <code><?= e($shortcode) ?></code></p>
<?php if (!empty($message)): ?><p style="color:green"><?= e($message) ?></p><?php endif; ?>
<div style="border:1px solid #ddd;padding:16px;border-radius:12px;margin:20px 0">
<h2>Widget output</h2>
<?= renderWidgetShortcodes($pdo,$shortcode) ?>
</div>
<p><a href="?upgrade=todo" style="display:inline-block;padding:10px 15px;background:#087568;color:white;border-radius:8px;text-decoration:none">Upgrade Hello World to To-Do List</a></p>
<p><a href="<?= e($config['base_url']) ?>/admin/toolbox/widget-manager/edit.php?id=<?= (int)$widget['widget_id'] ?>">Edit this widget in Widget Manager</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
