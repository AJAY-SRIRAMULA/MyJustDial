<?php
require_once __DIR__.'/public_forms.php';
function ensureDirectoryForms(PDO $pdo): void {
 $defs=['newsletter_modal_signup'=>['Join Our Newsletter','form_inquiries','inquiry_id',[['Name','yourname','text','Name'],['Email','inquiry_email','email','Enter email address']]],'find_members'=>['Find Members','','',[['What do you need:','q','text','Name or Keyword'],['Search by location:','location','text','City or Post Code']]]];
 foreach($defs as $name=>$d){$q=$pdo->prepare("SELECT form_id FROM forms WHERE form_name=? OR short_code=? LIMIT 1");$q->execute([$name,'[form='.$name.']']);if($q->fetchColumn())continue;
 $pdo->beginTransaction();try{$pdo->prepare("INSERT INTO forms (form_name,form_desc,form_url,form_target,form_action,form_database,form_table,table_index,form_email_recipient,form_title,form_email_on,email_template,email_admin,email_from,email_template_admin,form_class,form_style,form_element_id,short_code,form_layout,trigger_data_flow,data_flow_name,form_action_type,form_action_div) VALUES (?,'Directory form','','','post','',?,?,'',?,0,'',0,'','','','','',?,'bootstrapvertical','No','','widget','')")->execute([$name,$d[1],$d[2],$d[0],'[form='.$name.']']);
 $q=$pdo->prepare("INSERT INTO form_fields (form_name,field_text,field_name,field_type,field_order,field_sdesc,field_ldesc,field_placeholder,field_required,field_options,input_class,display_class,default_value,field_display_view,field_input_view,field_grid_view,field_email_view,form_section,field_search_view,display_div_id,input_div_id,field_icon,json_meta) VALUES (?,?,?,?,?,'','',?,?,'','','','','1','1','1','1','','0','','','','{}')");foreach($d[3] as $i=>$f)$q->execute([$name,$f[0],$f[1],$f[2],$i+1,$f[3],$name==='newsletter_modal_signup'?1:0]);$pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}}
}


require_once __DIR__ . '/widgets.php';

function ensureDirectoryWidgets(PDO $pdo): void
{
    $definitions = [
        'Find Members' => '<section><h2>Find Members</h2>[form=find_members]</section>',
        'Join Our Newsletter' => '<section><h2>Join Our Newsletter</h2><button type="button" onclick="document.getElementById(\'newsletter-dialog\').showModal()">Click to Subscribe</button></section><dialog id="newsletter-dialog"><button type="button" class="directory-close" onclick="this.closest(\'dialog\').close()">×</button><h2>Join Our Newsletter</h2>[form=newsletter_modal_signup]</dialog>',
        'Join Our Community' => '<section><h2>Join Our Community</h2><p>1. Register an account<br>2. Create a searchable listing<br>3. Connect with more clients</p><a href="membership.php">Get Listed Today</a></section>'
    ];
    foreach ($definitions as $name => $html) {
        $query = $pdo->prepare('SELECT widget_id FROM data_widgets WHERE widget_name=? LIMIT 1');
        $query->execute([$name]);
        $id = $query->fetchColumn();
        if (!$id) {
            $insert = $pdo->prepare("INSERT INTO data_widgets (widget_name,widget_type,widget_data,widget_style,widget_class,widget_viewport,date_updated,updated_by,short_code,div_id,bootstrap_enabled,ssl_enabled,mobile_enabled,widget_html_element,widget_javascript,file_type,widget_settings,widget_values) VALUES (?,?,?,'','','front',?,'directory',?,'',1,1,1,'div','','','','')");
            $insert->execute([$name,'Widget',$html,date('Y-m-d H:i:s'),newWidgetShortCode($pdo)]);
            $id = $pdo->lastInsertId();
        }
        $query = $pdo->prepare("SELECT rel_id FROM data_widgets_rel WHERE widget_id=? AND page_type='member_categories' LIMIT 1");
        $query->execute([(string)$id]);
        if (!$query->fetchColumn()) {
            $pdo->prepare("INSERT INTO data_widgets_rel (widget_id,data_id,page_type,active,display_order,date_updated,updated_by,sidebar_id,website_id) VALUES (?,'0','member_categories',1,?,?, 'directory',0,0)")->execute([(string)$id,array_search($name,array_keys($definitions))+1,date('Y-m-d H:i:s')]);
        }
    }
}

function renderDirectoryWidgets(PDO $pdo): string
{
    $query = $pdo->query("SELECT w.* FROM data_widgets_rel r JOIN data_widgets w ON CASE WHEN r.widget_id REGEXP '^[0-9]+$' THEN CAST(r.widget_id AS UNSIGNED) ELSE NULL END = w.widget_id WHERE r.page_type='member_categories' AND r.active=1 AND w.widget_viewport<>'disabled' ORDER BY r.display_order,r.rel_id");
    $html = '';
    foreach ($query->fetchAll() as $widget) {
        $content = composeWidgetContent($pdo,$widget);
        $body = preg_replace_callback('/\[form=([a-zA-Z0-9_-]+)\]/', static function ($match) use ($pdo) {
            return renderPublicForm($pdo,$match[1],csrfToken());
        }, $content['html']);
        $html .= '<style>'.$content['css'].'</style>'.$body.'<script>'.$content['js'].'</script>';
    }
    return $html;
}
