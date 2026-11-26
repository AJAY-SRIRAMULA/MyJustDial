<?php
require_once __DIR__ . '/directory_search.php';

function memberCategoryWidgetMarkup(PDO $pdo): string
{
    $tree = directoryCategories($pdo);
    $json = htmlspecialchars(json_encode($tree, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
    $html = '<div class="mjd-category-widget" data-category-tree="' . $json . '">';
    $change = 'var r=this.closest(".mjd-category-widget"),t=JSON.parse(r.dataset.categoryTree),a=r.querySelector("[data-level=main]"),b=r.querySelector("[data-level=sub]"),c=r.querySelector("[data-level=leaf]");function fill(s,o){s.replaceChildren(new Option("Select category",""));Object.entries(o||{}).forEach(function(p){s.add(new Option(typeof p[1]==="string"?p[1]:p[1].name,p[0]));});s.disabled=Object.keys(o||{}).length===0;s.parentElement.hidden=s.disabled;}if(this===a){fill(b,t[a.value]?.children);fill(c,{});}else if(this===b){fill(c,t[a.value]?.children?.[b.value]?.children);}';
    $event = htmlspecialchars($change, ENT_QUOTES, 'UTF-8');
    $html .= '<label>Main category<select class="form-control" name="fields[member_category_id]" data-level="main" onchange="' . $event . '"><option value="">Select category</option>';
    foreach ($tree as $id => $row) {
        $html .= '<option value="' . (int)$id . '">' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $html .= '</select></label><label hidden>Subcategory<select class="form-control" name="fields[member_subcategory_id]" data-level="sub" disabled onchange="' . $event . '"></select></label><label hidden>Sub-subcategory<select class="form-control" name="fields[member_subsubcategory_id]" data-level="leaf" disabled></select></label></div>';
    return $html;
}

function memberCategoryWidgetValues(array $tree, array $submitted): array
{
    $keys = ['member_category_id', 'member_subcategory_id', 'member_subsubcategory_id'];
    $ids = [];
    foreach ($keys as $key) {
        $value = $submitted[$key] ?? '';
        if (!is_string($value) || ($value !== '' && !preg_match('/^[1-9][0-9]{0,9}$/D', $value))) {
            throw new InvalidArgumentException('Choose a valid member category.');
        }
        $ids[] = $value;
    }
    [$main, $sub, $leaf] = $ids;
    if (($main !== '' && !isset($tree[$main])) || ($sub !== '' && !isset($tree[$main]['children'][$sub])) || ($leaf !== '' && !isset($tree[$main]['children'][$sub]['children'][$leaf]))) {
        throw new InvalidArgumentException('Choose a valid member category hierarchy.');
    }
    return array_filter(array_combine($keys, $ids), static fn($value) => $value !== '');
}
