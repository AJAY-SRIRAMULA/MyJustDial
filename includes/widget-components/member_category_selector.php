<?php
require_once __DIR__ . '/../directory_search.php';

function memberCategoryWidgetMarkup(PDO $pdo): string
{
    $tree = directoryCategories($pdo);

    $json = htmlspecialchars(
        json_encode(
            $tree,
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        ),
        ENT_QUOTES,
        'UTF-8'
    );

    global $config;

    $endpoint = rtrim($config['base_url'], '/')
        . '/public/member-category-options.php';

    $html = '<div class="mjd-category-widget"
        data-category-tree="' . $json . '"
        data-category-url="' . htmlspecialchars(
            $endpoint,
            ENT_QUOTES,
            'UTF-8'
        ) . '">';

    $change = <<<'JS'
var r = this.closest('.mjd-category-widget');
var a = r.querySelector('[data-level=main]');
var b = r.querySelector('[data-level=sub]');
var c = r.querySelector('[data-level=leaf]');
var changed = this;

function fill(s, o, selected) {
    s.replaceChildren(new Option('Select category', ''));

    Object.entries(o || {}).forEach(function(p) {
        s.add(
            new Option(
                typeof p[1] === 'string' ? p[1] : p[1].name,
                p[0]
            )
        );
    });

    s.value = selected || '';
    s.disabled = s !== a && !Object.keys(o || {}).length;
    s.parentElement.hidden = s.disabled;
}

var av = a.value;
var bv = changed === a ? '' : b.value;
var cv = changed === a || changed === b ? '' : c.value;

var seq = r.categoryRequest = (r.categoryRequest || 0) + 1;

function apply(t) {
    if (seq !== r.categoryRequest) return;

    r.dataset.categoryTree = JSON.stringify(t);

    if (a.value !== av) return;

    if (changed === a) {
        fill(b, t[av]?.children, bv);
        fill(c, t[av]?.children?.[b.value]?.children, cv);
    } else if (changed === b && b.value === bv) {
        fill(c, t[av]?.children?.[bv]?.children, cv);
    }
}

apply(JSON.parse(r.dataset.categoryTree));

if (changed !== c) {
    try {
        fetch(
            new URL(r.dataset.categoryUrl, document.baseURI),
            {
                cache: 'no-store',
                credentials: 'omit'
            }
        )
        .then(function(response) {
            if (!response.ok) {
                throw Error('Category request failed');
            }

            return response.json();
        })
        .then(apply)
        .catch(function() {});
    } catch (error) {}
}
JS;

    $event = htmlspecialchars(
        $change,
        ENT_QUOTES,
        'UTF-8'
    );

    $html .= '<label>Main category
        <select class="form-control"
            name="fields[member_category_id]"
            data-level="main"
            onchange="' . $event . '">
            <option value="">Select category</option>';

    foreach ($tree as $id => $row) {
        $html .= '<option value="' . (int)$id . '">'
            . htmlspecialchars(
                $row['name'],
                ENT_QUOTES,
                'UTF-8'
            )
            . '</option>';
    }

    $html .= '</select></label>';

    $html .= '<label hidden>Subcategory
        <select class="form-control"
            name="fields[member_subcategory_id]"
            data-level="sub"
            disabled
            onchange="' . $event . '">
        </select>
    </label>';

    $html .= '<label hidden>Sub-subcategory
        <select class="form-control"
            name="fields[member_subsubcategory_id]"
            data-level="leaf"
            disabled>
        </select>
    </label>';

    $html .= '</div>';

    return $html;
}

function memberCategoryWidgetValues(
    array $tree,
    array $submitted
): array {
    $keys = [
        'member_category_id',
        'member_subcategory_id',
        'member_subsubcategory_id'
    ];

    $ids = [];

    foreach ($keys as $key) {
        $value = $submitted[$key] ?? '';

        if (
            !is_string($value) ||
            (
                $value !== '' &&
                !preg_match('/^[1-9][0-9]{0,9}$/D', $value)
            )
        ) {
            throw new InvalidArgumentException(
                'Choose a valid member category.'
            );
        }

        $ids[] = $value;
    }

    [$main, $sub, $leaf] = $ids;

    if (
        ($main !== '' && !isset($tree[$main])) ||
        ($sub !== '' && !isset($tree[$main]['children'][$sub])) ||
        (
            $leaf !== '' &&
            !isset($tree[$main]['children'][$sub]['children'][$leaf])
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid member category hierarchy.'
        );
    }

    $values = [];

    if ($main !== '') {
        $values['member_category'] = $tree[$main]['name'];
    }

    if ($sub !== '') {
        $values['member_subcategory'] =
            $tree[$main]['children'][$sub]['name'];
    }

    if ($leaf !== '') {
        $leafData =
            $tree[$main]['children'][$sub]['children'][$leaf];

        $values['member_subsubcategory'] =
            is_array($leafData)
                ? $leafData['name']
                : $leafData;
    }

    return $values;
}

return [
    'render' => static fn(PDO $pdo): string =>
        memberCategoryWidgetMarkup($pdo),

    'validate' => static fn(PDO $pdo, array $submitted): array =>
        memberCategoryWidgetValues(
            directoryCategories($pdo),
            $submitted
        ),
];