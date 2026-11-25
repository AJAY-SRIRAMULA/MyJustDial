<?php
function directoryText(array $source, string $key): string
{
    return isset($source[$key]) && is_string($source[$key]) ? trim($source[$key]) : '';
}

function directoryCategories(PDO $pdo): array
{
    $tree = [];
    foreach ($pdo->query('SELECT profession_id, name FROM list_professions ORDER BY sort_order, name, profession_id') as $row) {
        $tree[$row['profession_id']] = ['name' => $row['name'], 'children' => []];
    }
    $services = $pdo->query('SELECT service_id, profession_id, master_id, name FROM list_services ORDER BY sort_order, name, service_id')->fetchAll();
    foreach ($services as $row) {
        if (!(int)$row['master_id'] && isset($tree[$row['profession_id']])) {
            $tree[$row['profession_id']]['children'][$row['service_id']] = ['name' => $row['name'], 'children' => []];
        }
    }
    foreach ($services as $row) {
        if (isset($tree[$row['profession_id']]['children'][$row['master_id']])) {
            $tree[$row['profession_id']]['children'][$row['master_id']]['children'][$row['service_id']] = $row['name'];
        }
    }
    return $tree;
}

function directoryCategorySelect(PDO $pdo, array $criteria, string $prefix = ''): string
{
    $selected = directoryText($criteria, 'selection');
    if ($selected === '') {
        $service = directoryText($criteria, 'service');
        $category = directoryText($criteria, 'category');
        $selected = $service && $service !== '0' ? 's:' . $service : ($category && $category !== '0' ? 'p:' . $category : '');
    }
    $name = $prefix === '' ? 'selection' : $prefix . '[selection]';
    $html = '<select name="' . e($name) . '"><option value="">All Categories</option>';
    $option = static function ($value, $label) use ($selected) {
        return '<option value="' . e($value) . '"' . ($selected === $value ? ' selected' : '') . '>' . e($label) . '</option>';
    };
    foreach (directoryCategories($pdo) as $id => $top) {
        $html .= $option('p:' . $id, $top['name']);
        foreach ($top['children'] as $sid => $sub) {
            $html .= $option('s:' . $sid, '— ' . $sub['name']);
            foreach ($sub['children'] as $cid => $child) {
                $html .= $option('s:' . $cid, '—— ' . $child);
            }
        }
    }
    return $html . '</select>';
}

function directoryKeywordWords(string $text): array
{
    $words = preg_split('/[^\p{L}\p{N}]+/u', strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $ignored = ['i', 'am', 'a', 'an', 'the', 'for', 'to', 'my', 'need', 'want', 'looking', 'please', 'with', 'is', 'me', 'near', 'can', 'you', 'have', 'help'];
    return array_values(array_unique(array_diff($words, $ignored)));
}

function directoryKeywordMatches(string $query, string $aliases): bool
{
    $words = directoryKeywordWords($query);
    if (!$words) return false;
    foreach (preg_split('/[,;\r\n]+/u', $aliases) ?: [] as $alias) {
        $terms = directoryKeywordWords($alias);
        if (!$terms) continue;
        if (!array_diff($words, $terms) || (count($terms) > 1 && !array_diff($terms, $words))) return true;
    }
    return false;
}

function directoryKeywordCategories(PDO $pdo, string $keyword): array
{
    $top = [];
    $services = [];
    foreach ($pdo->query('SELECT profession_id, name, keywords FROM list_professions') as $row) {
        if (directoryKeywordMatches($keyword, $row['name'] . ',' . $row['keywords'])) {
            $top[] = (int)$row['profession_id'];
        }
    }
    foreach ($pdo->query('SELECT service_id, name, keywords FROM list_services') as $row) {
        if (directoryKeywordMatches($keyword, $row['name'] . ',' . $row['keywords'])) {
            $services[] = (int)$row['service_id'];
        }
    }
    return [$top, $services];
}
