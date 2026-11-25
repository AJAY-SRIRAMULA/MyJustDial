<?php
require __DIR__.'/../includes/bootstrap.php';
require __DIR__.'/../includes/auth.php';
$pageTitle='Member Categories'; $activeMenu='members';
$level=max(1,min(3,(int)($_GET['level']??1))); $top=$level===1;
$table=$top?'list_professions':'list_services'; $pk=$top?'profession_id':'service_id';
$filter=$top?'1=1':($level===2?'master_id=0':'master_id<>0');
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="member-categories.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['level','id','name','filename','profession_id','master_id','sort_order','lead_price','keywords']);
    foreach ($pdo->query('SELECT * FROM list_professions ORDER BY profession_id') as $r) {
        fputcsv($out, [1,$r['profession_id'],$r['name'],$r['filename'],0,0,$r['sort_order'],$r['lead_price'],$r['keywords']]);
    }
    foreach ($pdo->query('SELECT * FROM list_services ORDER BY master_id,service_id') as $r) {
        fputcsv($out, [$r['master_id'] ? 3 : 2,$r['service_id'],$r['name'],$r['filename'],$r['profession_id'],$r['master_id'],$r['sort_order'],$r['lead_price'],$r['keywords']]);
    }
    fclose($out);
    exit;
}
$notice='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verifyCsrf();
 try {
 $pdo->beginTransaction();
 $action=$_POST['action']??'save';
 if ($action === 'import') {
    $file = $_FILES['categories_csv'] ?? [];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $file['size'] > 2000000) throw new RuntimeException('Choose a CSV file under 2 MB.');
    $handle = fopen($file['tmp_name'], 'r');
    $header = fgetcsv($handle);
    if ($header !== ['level','id','name','filename','profession_id','master_id','sort_order','lead_price','keywords']) throw new RuntimeException('Use the exported CSV column format.');
    $records = [];
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) !== 9) throw new RuntimeException('Invalid CSV row.');
        $records[] = $row;
    }
    fclose($handle);
    usort($records, static fn($a,$b) => (int)$a[0] <=> (int)$b[0]);
    $topMap = []; $subMap = [];
    foreach ($records as [$l,$oldId,$name,$slug,$profession,$master,$order,$price,$keywords]) {
        $l = (int)$l;
        if (!in_array($l,[1,2,3],true) || trim($name)==='' || !preg_match('/^[a-zA-Z0-9_-]+$/',$slug) || ($price!=='' && (!is_numeric($price)||$price<0))) throw new RuntimeException('Invalid category in CSV.');
        $price = $price === '' ? null : $price;
        if ($l===1) {
            $q=$pdo->prepare('SELECT profession_id FROM list_professions WHERE filename=?'); $q->execute([$slug]); $id=$q->fetchColumn();
            if (!$id) { $pdo->prepare("INSERT INTO list_professions (name,`desc`,filename,keywords,icon,sort_order,lead_price,image) VALUES (?,'',?,?,'',?,?,'')")->execute([$name,$slug,$keywords,(int)$order,$price]); $id=$pdo->lastInsertId(); }
            $topMap[$oldId]=$id;
        } else {
            $parent=$topMap[$profession]??0; $masterId=$l===3?($subMap[$master]??0):0;
            if (!$parent || ($l===3 && !$masterId)) throw new RuntimeException('CSV must include parent categories.');
            $q=$pdo->prepare('SELECT service_id FROM list_services WHERE filename=? AND profession_id=? AND master_id=?'); $q->execute([$slug,$parent,$masterId]); $id=$q->fetchColumn();
            if (!$id) { $pdo->prepare("INSERT INTO list_services (name,`desc`,profession_id,master_id,filename,keywords,sort_order,lead_price) VALUES (?,'',?,?,?,?,?,?)")->execute([$name,$parent,$masterId,$slug,$keywords,(int)$order,$price]); $id=$pdo->lastInsertId(); }
            if ($l===2) $subMap[$oldId]=$id;
        }
    }
 } elseif($action==='add'){
 $names = $top ? preg_split('/\R/u', (string)($_POST['names'] ?? '')) : [$_POST['name'] ?? ''];
 $names = array_values(array_unique(array_filter(array_map('trim', $names), static fn($name) => $name !== '')));
 if (!$names || count($names) > 200) throw new RuntimeException('Enter between 1 and 200 category names, one per line.');
 foreach ($names as $name) {
 $slug=trim(preg_replace('/[^a-z0-9]+/','-',strtolower($name)),'-');
 if ($slug === '') throw new RuntimeException('Enter a category name containing letters or numbers.');
 if ($top) {
     $check = $pdo->prepare('SELECT COUNT(*) FROM list_professions WHERE filename=? OR name=?');
     $check->execute([$slug, $name]);
     if ($check->fetchColumn()) throw new RuntimeException('Category already exists: '.$name);
 }
 if($top){$pdo->prepare("INSERT INTO list_professions (name,`desc`,filename,keywords,icon,sort_order,image) VALUES (?,'',?,'','',0,'')")->execute([$name,$slug]);}
 else {$parent=(int)($_POST['parent']??0); $p=$pdo->prepare($level===2?'SELECT profession_id FROM list_professions WHERE profession_id=?':'SELECT profession_id FROM list_services WHERE service_id=? AND master_id=0');$p->execute([$parent]);$profession=$p->fetchColumn();if(!$profession)throw new RuntimeException('Select a valid parent.');$pdo->prepare("INSERT INTO list_services (name,`desc`,profession_id,master_id,filename,keywords,sort_order) VALUES (?,'',?,?,?,'',0)")->execute([$name,$profession,$level===2?0:$parent,$slug]);}
 }
 } elseif($action==='delete'||($action==='bulk'&&($_POST['bulk']??'')==='delete')){
 $ids=$action==='delete'?[(int)$_POST['id']]:array_map('intval',$_POST['selected']??[]);
 foreach($ids as $id){$q=$pdo->prepare($top?'SELECT COUNT(*) FROM list_services WHERE profession_id=?':'SELECT COUNT(*) FROM list_services WHERE master_id=?');$q->execute([$id]);if($q->fetchColumn())throw new RuntimeException('Remove child categories first.');$q=$pdo->prepare($top?'SELECT COUNT(*) FROM users_data WHERE profession_id=?':'SELECT COUNT(*) FROM rel_services WHERE service_id=?');$q->execute([$id]);if($q->fetchColumn())throw new RuntimeException('Remove member associations first.');$pdo->prepare("DELETE FROM `$table` WHERE `$pk`=? AND $filter")->execute([$id]);}
 } elseif($action==='save'){
 foreach ($_POST['rows'] ?? [] as $id => $r) {
    $id = (int)$id;
    $name = trim($r['name']);
    $slug = trim($r['filename']);
    if ($name === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) throw new RuntimeException('Enter a name and valid URL slug.');
    $price = $r['lead_price'] === '' ? null : $r['lead_price'];
    if ($price !== null && (!is_numeric($price) || $price < 0)) throw new RuntimeException('Invalid lead price.');
    $current = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk`=? AND $filter FOR UPDATE");
    $current->execute([$id]);
    $old = $current->fetch();
    if (!$old) throw new RuntimeException('Category no longer exists.');
    if (!$top) {
        $parentId = (int)($r['parent'] ?? 0);
        $parentQuery = $pdo->prepare($level === 2 ? 'SELECT profession_id FROM list_professions WHERE profession_id=? FOR UPDATE' : 'SELECT profession_id FROM list_services WHERE service_id=? AND master_id=0 FOR UPDATE');
        $parentQuery->execute([$parentId]);
        $profession = $parentQuery->fetchColumn();
        if (!$profession) throw new RuntimeException('Select a valid parent category.');
        if ((int)$profession !== (int)$old['profession_id']) {
            $association = $pdo->prepare('SELECT COUNT(*) FROM rel_services r JOIN list_services s ON s.service_id=r.service_id LEFT JOIN users_data u ON u.user_id=r.user_id WHERE (s.service_id=? OR (?=2 AND s.master_id=?)) AND (u.user_id IS NULL OR u.profession_id<>?)');
            $association->execute([$id,$level,$id,$profession]);
            if ($association->fetchColumn()) throw new RuntimeException('Move associated members to the destination top-level category before moving this category.');
        }
        $pdo->prepare('UPDATE list_services SET profession_id=?, master_id=? WHERE service_id=?')->execute([$profession,$level===2?0:$parentId,$id]);
        if ($level===2) $pdo->prepare('UPDATE list_services SET profession_id=? WHERE master_id=?')->execute([$profession,$id]);
    }
    $pdo->prepare("UPDATE `$table` SET name=?,filename=?,sort_order=?,lead_price=?,keywords=? WHERE `$pk`=? AND $filter")->execute([$name,$slug,(int)$r['sort_order'],$price,$r['keywords'],$id]);
 }
 } elseif($action==='image'){
 $id=(int)$_POST['id'];$q=$pdo->prepare("SELECT `$pk` FROM `$table` WHERE `$pk`=? AND $filter");$q->execute([$id]);if(!$q->fetchColumn())throw new RuntimeException('Invalid category.');$f=$_FILES['image']??[];$mime=isset($f['tmp_name'])&&is_uploaded_file($f['tmp_name'])?(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']):'';$ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime]??'';if(!$ext||$f['size']>5000000)throw new RuntimeException('Choose a PNG, JPEG or WebP under 5 MB.');$dir=__DIR__.'/../public/assets/category-images';if(!is_dir($dir))mkdir($dir,0755,true);$path='public/assets/category-images/'.bin2hex(random_bytes(16)).'.'.$ext;if(!move_uploaded_file($f['tmp_name'],__DIR__.'/../'.$path))throw new RuntimeException('Upload failed.');if($top)$pdo->prepare('UPDATE list_professions SET image=? WHERE profession_id=?')->execute([$path,$id]);else{$pdo->prepare("DELETE FROM users_meta WHERE `database`='list_services' AND database_id=? AND `key`='category_image'")->execute([$id]);$pdo->prepare("INSERT INTO users_meta (`database`,database_id,`key`,value,date_added) VALUES ('list_services',?,'category_image',?,?)")->execute([$id,$path,date('YmdHis')]);}
 } else throw new RuntimeException('Select an action.');
 $pdo->commit();$notice='Changes saved.';
 }catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();$notice=$ex instanceof RuntimeException?$ex->getMessage():'Unable to save changes.';}
}
$counts=[$pdo->query('SELECT COUNT(*) FROM list_professions')->fetchColumn(),$pdo->query('SELECT COUNT(*) FROM list_services WHERE master_id=0')->fetchColumn(),$pdo->query('SELECT COUNT(*) FROM list_services WHERE master_id<>0')->fetchColumn()];
$parents=$pdo->query($level===3?'SELECT service_id id,name FROM list_services WHERE master_id=0 ORDER BY name':'SELECT profession_id id,name FROM list_professions ORDER BY name')->fetchAll();
$parent=(int)($_GET['parent']??0);$keyword=trim($_GET['q']??'');$params=[];$where=$filter;if(!$top&&$parent){$where.=$level===2?' AND c.profession_id=?':' AND c.master_id=?';$params[]=$parent;}if($keyword!==''){$where.=' AND (c.name LIKE ? OR c.keywords LIKE ?)';$params[]='%'.$keyword.'%';$params[]='%'.$keyword.'%';}
$orders=['id_asc'=>"c.$pk ASC",'id_desc'=>"c.$pk DESC",'name_asc'=>'c.name ASC','name_desc'=>'c.name DESC','sort_asc'=>'c.sort_order ASC','sort_desc'=>'c.sort_order DESC','count_asc'=>'member_count ASC','count_desc'=>'member_count DESC'];$sort=$_GET['sort']??'id_asc';$countSql=$top?'(SELECT COUNT(*) FROM users_data u WHERE u.profession_id=c.profession_id)':'(SELECT COUNT(DISTINCT r.user_id) FROM rel_services r WHERE r.service_id=c.service_id)';$imageSql=$top?'c.image':"(SELECT value FROM users_meta WHERE `database`='list_services' AND database_id=c.service_id AND `key`='category_image' LIMIT 1)";$countQuery = $pdo->prepare("SELECT COUNT(*) FROM `$table` c WHERE $where");
$countQuery->execute($params);
$total = (int)$countQuery->fetchColumn();
$perPage = 10;
$pageCount = max(1, (int)ceil($total / $perPage));
$page = min($pageCount, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$q = $pdo->prepare("SELECT c.*, $countSql member_count,$imageSql category_image FROM `$table` c WHERE $where ORDER BY ".($orders[$sort]??$orders['id_asc']).", c.$pk ASC LIMIT $perPage OFFSET $offset");
$q->execute($params);
$rows = $q->fetchAll();
function categoryPageLink(int $page): string {
    return '?' . http_build_query(array_merge($_GET, ['page'=>$page]));
}

require __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="<?=e($config['base_url'])?>/assets/css/member-categories.css">
<section class="cat-page"><div class="cat-heading"><div class="cat-banner">ⓘ <b>Member Categories</b> – Create, edit and manage categories that members can be listed in</div><div class="cat-menu cat-global"><button type="button" class="cat-trigger">Member Category Actions <span>▾</span></button><div class="cat-dropdown"><button type="button" onclick="document.getElementById('cat-import').showModal()">Import Categories</button><a href="?export=1">Export Categories</a><a href="toolbox/form-manager/index.php?q=member_listing_details">Edit Specialties Tab Code</a></div></div></div><?php if($notice):?><p><?=e($notice)?></p><?php endif;?>
<div class="cat-tabs"><?php foreach(['Top Level','Sub Level','Sub-Sub Level'] as $i=>$label):?><a class="<?=$level===$i+1?'selected':''?>" href="?level=<?=$i+1?>"><?=e($label)?> (<?=$counts[$i]?>)</a><?php endforeach;?><span></span><button type="button" onclick="document.getElementById('cat-add').showModal()">Add <?=e(['Top Level','Sub Level','Sub-Sub Level'][$level-1])?> Categories +</button><button class="save" form="cat-edit" name="action" value="save">Save Changes</button></div>
<div class="cat-panel"><div class="cat-toolbar"><select form="cat-edit" name="bulk"><option value="">(select bulk action)</option><option value="delete">Delete</option></select><button form="cat-edit" name="action" value="bulk" onclick="return confirm('Apply bulk action?')">Apply</button><form method="get"><input type="hidden" name="level" value="<?=$level?>"><?php if(!$top):?><select name="parent" onchange="this.form.submit()"><option value="0">Parent Category</option><?php foreach($parents as $p):?><option value="<?=$p['id']?>" <?=$parent==$p['id']?'selected':''?>><?=e($p['name'])?></option><?php endforeach;?></select><?php endif;?><select name="sort" onchange="this.form.submit()"><?php foreach(['Order by ID Ascending','Order by ID Descending','Order by Name A-Z','Order by Name Z-A','Sort Order Ascending','Sort Order Descending','Member Count Ascending','Member Count Descending'] as $i=>$label):$key=array_keys($orders)[$i];?><option value="<?=$key?>" <?=$sort===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select><input name="q" placeholder="Search by Keyword" value="<?=e($keyword)?>"></form></div>
<form id="cat-edit" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><table><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.cat-check').forEach(x=>x.checked=this.checked)"></th><th>Actions</th><th>Category Info</th><th>Homepage Image</th><th>Related Keywords</th></tr></thead><tbody><?php foreach($rows as $r):$id=(int)$r[$pk];$prefix="rows[$id]";?><tr><td><input class="cat-check" type="checkbox" name="selected[]" value="<?=$id?>"></td><td><a class="cat-count" href="search-members.php?<?=$top?'profession':'service'?>=<?=$id?>"><?=$r['member_count']?><small>members</small></a><div class="cat-menu cat-row-menu"><button type="button" class="cat-trigger">Actions <span>▾</span></button><div class="cat-dropdown"><a target="_blank" href="../public/search.php?<?=$top?'category':'service'?>=<?=$id?>">Live Page</a><button type="button" onclick="if(confirm('Delete category?')){document.getElementById('delete-id').value=<?=$id?>;document.getElementById('cat-delete').submit()}" class="cat-danger">Delete</button></div></div></td><td><small class="cat-id">ID: <?=$id?></small><div class="cat-fields"><?php foreach(['name'=>'Name','filename'=>'Link URL','sort_order'=>'Sort Order','lead_price'=>'Lead Price'] as $k=>$label):?><label><?=e($label)?><input name="<?=$prefix?>[<?=$k?>]" value="<?=e($r[$k]??'')?>"></label><?php endforeach;?><?php if(!$top):?><label><?=$level===2?'Parent Top-Level Category':'Parent Sub-Level Category'?><select name="<?=$prefix?>[parent]" required><?php foreach($parents as $p):?><option value="<?=$p['id']?>" <?=((int)$p['id']===(int)($level===2?$r['profession_id']:$r['master_id']))?'selected':''?>><?=e($p['name'])?></option><?php endforeach;?></select></label><?php endif;?></div></td><td><div class="cat-image"><?php if($r['category_image']):?><img src="<?=e($config['base_url'].'/'.$r['category_image'])?>" alt=""><?php else:?>Upload Image<svg viewBox="0 0 64 48" width="64" height="48" aria-hidden="true"><path d="M17 40H12A10 10 0 0 1 10 20 17 17 0 0 1 43 15 13 13 0 0 1 51 40H46" fill="none" stroke="#91c8e7" stroke-width="6" stroke-linecap="round"/><path d="M32 20 19 33H27V46H37V33H45Z" fill="#91c8e7"/></svg><?php endif;?></div><button type="button" onclick="document.getElementById('image-id').value=<?=$id?>;document.getElementById('cat-upload').showModal()">Upload Image</button><small>Recommended: 400px x 200px</small></td><td><textarea name="<?=$prefix?>[keywords]" placeholder="Separate keywords with comma"><?=e($r['keywords'])?></textarea></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="5"><div class="cat-empty">There is Currently No Data to Display</div></td></tr><?php endif;?></tbody></table><div class="cat-footer"><span>Showing <?=$total?$offset+1:0?> to <?=$offset+count($rows)?> of <?=$total?> entries</span><nav aria-label="Category pagination"><?php if($page>1):?><a class="cat-page-link" href="<?=e(categoryPageLink($page-1))?>">Previous</a><?php endif;?><?php for($i=max(1,$page-2);$i<=min($pageCount,$page+2);$i++):?><a class="cat-page-link" <?=$i===$page?'aria-current="page"':''?> href="<?=e(categoryPageLink($i))?>"><?=$i?></a><?php endfor;?><?php if($page<$pageCount):?><a class="cat-page-link" href="<?=e(categoryPageLink($page+1))?>">Next</a><?php endif;?></nav></div><button class="save cat-wide" name="action" value="save">Save Changes</button></form></div></section>
<form id="cat-delete" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="delete-id"></form>
<?php if ($top): ?>
<dialog id="cat-add" class="cat-add-top" aria-labelledby="cat-add-title">
    <form method="post">
        <header class="cat-add-header"><h2 id="cat-add-title">Add Top-Level Categories</h2></header>
        <button class="cat-add-close" type="button" onclick="this.closest('dialog').close()" aria-label="Close">× Close</button>
        <div class="cat-add-body">
            <p class="cat-add-alert">Enter top-level member categories below. ALERT: Only enter one new category per line</p>
            <input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>">
            <textarea name="names" aria-label="Top-level category names, one per line" placeholder="ALERT: ONLY ENTER ONE NEW CATEGORY PER LINE" required autofocus></textarea>
            <button class="cat-add-save" name="action" value="add">Save Top-Level Categories</button>
        </div>
    </form>
</dialog>
<?php else: ?>
<dialog id="cat-add"><form method="post"><h2>Add Category</h2><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input name="name" placeholder="Category name" required><?php if(!$top):?><select name="parent" required><option value="">Select parent</option><?php foreach($parents as $p):?><option value="<?=$p['id']?>"><?=e($p['name'])?></option><?php endforeach;?></select><?php endif;?><button name="action" value="add">Add Category</button><button type="button" onclick="this.closest('dialog').close()">Cancel</button></form></dialog>
<?php endif; ?>
<dialog id="cat-upload"><form method="post" enctype="multipart/form-data"><h2>Upload Image</h2><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="hidden" name="id" id="image-id"><input type="file" name="image" accept="image/png,image/jpeg,image/webp" required><button name="action" value="image">Upload</button><button type="button" onclick="this.closest('dialog').close()">Cancel</button></form></dialog>
<dialog id="cat-import"><form method="post" enctype="multipart/form-data"><h2>Import Categories</h2><p>Use the exported CSV format. Existing matching categories are retained.</p><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><input type="file" name="categories_csv" accept=".csv,text/csv" required><button name="action" value="import">Import Categories</button><button type="button" onclick="this.closest('dialog').close()">Cancel</button></form></dialog>
<?php require __DIR__.'/../includes/footer.php';?>
