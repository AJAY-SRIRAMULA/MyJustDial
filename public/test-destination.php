<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public_forms.php';

$testShortcode = '[form=contact_form]';
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedShortcode = (string)($_POST['public_form_shortcode'] ?? '');

    if ($postedShortcode === 'contact_form') {
        $result = processPublicFormSubmission($pdo, $testShortcode);
        $message = $result['message'];
        $success = $result['ok'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Destination Table Test</title>
</head>
<body>

<h2>Destination Table Test</h2>

<?php if ($message !== ''): ?>
    <p><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<?= renderPublicForm($pdo, $testShortcode, csrfToken()) ?>

</body>
</html>