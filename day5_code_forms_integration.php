<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/widgets.php';

function day5EnsureWidget(PDO $pdo, string $name, string $alias, string $html, string $css = '', string $js = ''): array
{
    $q = $pdo->prepare('SELECT * FROM data_widgets WHERE widget_name=? LIMIT 1');
    $q->execute([$name]);
    $widget = $q->fetch(PDO::FETCH_ASSOC);
    if (!$widget) {
        $code = newWidgetShortCode($pdo);
        $now = date('YmdHis');
        $insert = $pdo->prepare('INSERT INTO data_widgets (widget_name,widget_type,widget_data,widget_style,widget_class,widget_viewport,date_updated,updated_by,short_code,div_id,bootstrap_enabled,ssl_enabled,mobile_enabled,widget_html_element,widget_javascript,file_type,widget_settings,widget_values) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insert->execute([$name,'Custom Widget',$html,$css,'day5-component','front',$now,'Day5 Practical',$code,$alias,1,1,1,'div',$js,'html','','']);
        $id=(int)$pdo->lastInsertId();
        $widget=['widget_id'=>$id,'widget_name'=>$name,'short_code'=>$code];
    } else {
        $widgetId=(int)$widget['widget_id'];
        $update=$pdo->prepare('UPDATE data_widgets SET widget_data=?,widget_style=?,widget_javascript=?,date_updated=?,updated_by=? WHERE widget_id=?');
        $update->execute([$html,$css,$js,date('YmdHis'),'Day5 Practical',$widgetId]);
    }
    setWidgetMetaValue($pdo,(int)$widget['widget_id'],'widget_shortcode',$alias);
    $q->execute([$name]);
    return ensureWidgetShortcodeMapping($pdo,$q->fetch(PDO::FETCH_ASSOC));
}

$css = '.day5-form{font-family:Arial,sans-serif;color:#222;max-width:680px;margin:auto;padding:20px}.day5-form h2{margin-top:0}.day5-form label{display:block;margin:14px 0 6px;font-weight:600}.day5-form input,.day5-form select{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:7px;box-sizing:border-box}.day5-form .row{display:grid;grid-template-columns:1fr 1fr;gap:14px}.day5-form button{margin-top:18px;padding:10px 16px;border:0;border-radius:7px;background:#087568;color:#fff}.day5-form .conditional{display:none}.day5-form .conditional.visible{display:block}@media(max-width:600px){.day5-form .row{grid-template-columns:1fr}}';
$aHtml='<div class="day5-form"><h2>Widget A – Name Fields</h2><div class="row"><div><label for="d5_first">First Name</label><input id="d5_first" name="first_name" type="text" placeholder="First name"></div><div><label for="d5_last">Last Name</label><input id="d5_last" name="last_name" type="text" placeholder="Last name"></div></div></div>';
$bHtml='<div class="day5-form"><h2>Widget B – Contact Fields</h2><label for="d5_email">Email</label><input id="d5_email" name="email" type="email" placeholder="Email address"><label for="d5_phone">Phone</label><input id="d5_phone" name="phone" type="tel" placeholder="Phone number"></div>';
$cHtml='<div class="day5-form"><h2>Widget C – Account Type</h2><label for="d5_account">Account Type</label><select id="d5_account" name="account_type"><option value="">Select account type</option><option value="individual">Individual</option><option value="business">Business</option></select></div>';
$js='(function(){const select=document.getElementById("d5_account"),extras=document.querySelectorAll(".conditional");if(!select)return;select.addEventListener("change",function(){extras.forEach(function(x){x.classList.toggle("visible",select.value==="business");});});})();';
$a=day5EnsureWidget($pdo,'Day 5 formfieldsA','formfieldsA',$aHtml,$css);
$b=day5EnsureWidget($pdo,'Day 5 formfieldsB','formfieldsB',$bHtml,$css);
$c=day5EnsureWidget($pdo,'Day 5 formfieldsC','formfieldsC',$cHtml,$css);
$dHtml='<div class="day5-form"><h2>Widget D – Integrated Form</h2><div class="row"><div><label for="d5d_first">First Name</label><input id="d5d_first" name="first_name" type="text"></div><div><label for="d5d_last">Last Name</label><input id="d5d_last" name="last_name" type="text"></div></div><label for="d5d_email">Email</label><input id="d5d_email" name="email" type="email"><label for="d5d_phone">Phone</label><input id="d5d_phone" name="phone" type="tel"><label for="d5d_account">Account Type</label><select id="d5d_account" name="account_type"><option value="">Select account type</option><option value="individual">Individual</option><option value="business">Business</option></select><div class="conditional" id="d5d_company"><label for="d5d_company_name">Company Name</label><input id="d5d_company_name" name="company_name" type="text"></div><div class="conditional" id="d5d_gst"><label for="d5d_gst_number">GST Number</label><input id="d5d_gst_number" name="gst_number" type="text"></div><button type="button" onclick="alert(\'Day 5 integrated widget demo: fields collected in this widget.\')">Submit Demo</button></div>';
$dJs='(function(){const s=document.getElementById("d5d_account"),a=document.getElementById("d5d_company"),b=document.getElementById("d5d_gst");if(!s)return;function sync(){const show=s.value==="business";a.classList.toggle("visible",show);b.classList.toggle("visible",show);}s.addEventListener("change",sync);sync();})();';
$d=day5EnsureWidget($pdo,'Day 5 formfieldsD','formfieldsD',$dHtml,$css,$dJs);
$pageTitle='Day 5 – Code and Forms Integration';
require __DIR__ . '/includes/header.php';
?>
<div style="max-width:1100px;margin:25px auto;padding:20px"><h1>Day 5 – Code and Forms Integration</h1><p>Three independent widgets are stored in the existing widget records. Widget D combines the five logical fields and conditionally displays Company Name and GST Number when Business is selected.</p><h2>Independent widgets</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px"><section style="border:1px solid #ddd;border-radius:12px;padding:12px"><h3>formfieldsA</h3><?=renderWidgetShortcodes($pdo,'[widget=formfieldsA]')?></section><section style="border:1px solid #ddd;border-radius:12px;padding:12px"><h3>formfieldsB</h3><?=renderWidgetShortcodes($pdo,'[widget=formfieldsB]')?></section><section style="border:1px solid #ddd;border-radius:12px;padding:12px"><h3>formfieldsC</h3><?=renderWidgetShortcodes($pdo,'[widget=formfieldsC]')?></section></div><h2 style="margin-top:30px">Combined widget D</h2><p><code>[widget=formfieldsD]</code></p><div style="border:1px solid #ddd;border-radius:12px;padding:12px"><?=renderWidgetShortcodes($pdo,'[widget=formfieldsD]')?></div><h2>Widget Manager records</h2><ul><?php foreach([$a,$b,$c,$d] as $w): ?><li><a href="<?=e($config['base_url'])?>/admin/toolbox/widget-manager/edit.php?id=<?=(int)$w['widget_id']?>"><?=e($w['widget_name'])?></a> – <code>[widget=<?=e($w['public_shortcode'])?>]</code></li><?php endforeach; ?></ul><p>These practical widgets use existing <code>data_widgets</code> and <code>users_meta</code> storage. No database tables were added.</p></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
