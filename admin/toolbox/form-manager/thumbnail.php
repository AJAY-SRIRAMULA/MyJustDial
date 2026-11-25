<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/auth.php';
require __DIR__ . '/../../../includes/form_fields.php';
$id = (int)($_GET['id'] ?? 0);
$query = $pdo->prepare("SELECT form_name FROM forms WHERE form_id = ? AND form_action_type <> 'default'");
$query->execute([$id]);
$name = $query->fetchColumn();
if (!$name) { http_response_code(404); exit; }
$fields = loadBuilderFields($pdo, $name);
$base = e($config['base_url']);
$flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= $base ?>/assets/css/form-builder.css">
<link rel="stylesheet" href="<?= $base ?>/assets/css/targeted-ui.css">
</head><body class="fm-thumbnail-document fb-page"><div class="fb-preview-body" id="fbPreview"></div>
<script>
window.MJD_FIELD_GROUPS = <?= json_encode(formFieldGroups(), $flags) ?>;
window.MJD_FIELD_DATA = <?= json_encode(loadBuilderLists($pdo), $flags) ?>;
window.MJD_INITIAL_FIELDS = <?= json_encode($fields, $flags) ?>;
window.MJD_COMPONENT_PREVIEW_URL = <?= json_encode($config['base_url'] . '/admin/toolbox/form-manager/component-preview.php', $flags) ?>;
window.MJD_FORM_ID = <?= $id ?>;
window.MJD_CSRF_TOKEN = <?= json_encode(csrfToken(), $flags) ?>;
</script><script src="<?= $base ?>/assets/js/form-builder.js?v=20261125-1"></script><script>
(function () {
    const frame = window.frameElement;
    const preview = document.getElementById('fbPreview');
    if (!frame || !preview) return;
    function fit() {
        const tile = frame.parentElement;
        const scale = tile.clientWidth / 760;
        if (!scale || !tile.clientHeight) return;
        const height = tile.clientHeight / scale;
        frame.style.width = '760px';
        frame.style.height = height + 'px';
        frame.style.transform = 'scale(' + scale + ')';
        frame.style.left = '0px';
    }
    new ResizeObserver(fit).observe(frame.parentElement);
    fit();
}());
</script></body></html>
