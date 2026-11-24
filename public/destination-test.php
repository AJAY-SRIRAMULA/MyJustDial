<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public_forms.php';

$shortcode = 'contact_details';
$message = '';
$success = false;
$form = getPublicFormByShortcode($pdo, $shortcode);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['public_form_shortcode'] ?? '') === $shortcode) {
    $result = processPublicFormSubmission($pdo, $shortcode);
    $message = (string)($result['message'] ?? '');
    $success = (bool)($result['ok'] ?? false);
    $form = getPublicFormByShortcode($pdo, $shortcode);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destination Table Test</title>
</head>
<body>
    <main style="max-width:900px;margin:30px auto;padding:20px;font-family:Arial,sans-serif">
        <h1>Destination Table Test</h1>
        <p>This temporary page renders the form assigned the public shortcode <code>[form=contact_details]</code> and submits through the existing generic public form handler.</p>
        <?php if ($message !== ''): ?>
            <div style="padding:12px;margin:15px 0;background:<?= $success ? '#e8f5e9' : '#ffebee' ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>
        <?= renderPublicForm($pdo, $shortcode, csrfToken()) ?>
    </main>
</body>
</html>
