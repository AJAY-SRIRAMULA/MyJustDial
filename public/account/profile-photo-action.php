<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/member_forms.php';
require_once __DIR__ . '/../../includes/form_destination.php';

header('Content-Type: application/json; charset=utf-8');

function profilePhotoResponse(bool $ok, string $message, array $extra = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profilePhotoResponse(false, 'Method not allowed.', [], 405);
}

$requestLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
$postMaxSize = trim((string)ini_get('post_max_size'));
$postMaxBytes = 0;
if ($postMaxSize !== '') {
    $unit = strtolower(substr($postMaxSize, -1));
    $postMaxBytes = (int)$postMaxSize;
    if ($unit === 'g') $postMaxBytes *= 1024 * 1024 * 1024;
    elseif ($unit === 'm') $postMaxBytes *= 1024 * 1024;
    elseif ($unit === 'k') $postMaxBytes *= 1024;
}
if ($requestLength > 0 && $postMaxBytes > 0 && $requestLength > $postMaxBytes && empty($_POST)) {
    profilePhotoResponse(false, 'The selected image is too large for the server to receive. Choose an image smaller than 5 MB.', [], 413);
}

if (!hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) {
    profilePhotoResponse(false, 'Your session token expired. Refresh the page and try again.', [], 419);
}

$isAdminAction = !empty($_SESSION['admin_id']) && isset($_POST['admin_member']);
$memberId = $isAdminAction ? (int)$_POST['admin_member'] : (int)($_SESSION['member_id'] ?? 0);
if ($memberId < 1) {
    profilePhotoResponse(false, 'Please sign in to update your profile images.', [], 403);
}

if (!$isAdminAction && empty($_SESSION['member_id'])) {
    profilePhotoResponse(false, 'You are not authorized to update this image.', [], 403);
}

$imageType = (string)($_POST['image_type'] ?? '');
if (!in_array($imageType, ['photo', 'logo'], true)) {
    profilePhotoResponse(false, 'Invalid image type.', [], 400);
}

$memberQuery = $pdo->prepare('SELECT user_id, active FROM users_data WHERE user_id = ? LIMIT 1');
$memberQuery->execute([$memberId]);
$member = $memberQuery->fetch(PDO::FETCH_ASSOC);
if (!$member || (!$isAdminAction && !isActiveMemberRecord($member['active']))) {
    profilePhotoResponse(false, 'Member account is not available.', [], 403);
}

$existingQuery = $pdo->prepare('SELECT photo_id, file FROM users_photo WHERE user_id = ? AND type = ? ORDER BY photo_id DESC LIMIT 1');
$existingQuery->execute([$memberId, $imageType]);
$existing = $existingQuery->fetch(PDO::FETCH_ASSOC) ?: null;
$imageDirectory = __DIR__ . '/../../images/';
if (!is_dir($imageDirectory) && !mkdir($imageDirectory, 0755, true) && !is_dir($imageDirectory)) {
    profilePhotoResponse(false, 'Image directory is unavailable.', [], 500);
}

if (($_POST['action'] ?? '') === 'delete') {
    if (!$existing) profilePhotoResponse(true, 'There is no saved image to delete.');
    $delete = $pdo->prepare('DELETE FROM users_photo WHERE photo_id = ? AND user_id = ? AND type = ?');
    $delete->execute([(int)$existing['photo_id'], $memberId, $imageType]);
    $oldPath = $imageDirectory . basename((string)$existing['file']);
    $references = $pdo->prepare('SELECT COUNT(*) FROM users_photo WHERE file = ?');
    $references->execute([$existing['file']]);
    if (!(int)$references->fetchColumn() && is_file($oldPath)) unlink($oldPath);
    profilePhotoResponse(true, 'Image deleted successfully.');
}

$file = $_FILES['image'] ?? null;
if (!$file || !isset($file['error'])) {
    profilePhotoResponse(false, 'Choose an image before uploading.', [], 400);
}
if ((int)$file['error'] !== UPLOAD_ERR_OK) {
    $uploadMessages = [
        UPLOAD_ERR_INI_SIZE => 'The image exceeds the server upload limit. Choose an image smaller than 5 MB.',
        UPLOAD_ERR_FORM_SIZE => 'The image exceeds the allowed upload size of 5 MB.',
        UPLOAD_ERR_PARTIAL => 'The image upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Choose an image before uploading.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server upload folder is unavailable. Please contact support.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded image. Please try again.',
        UPLOAD_ERR_EXTENSION => 'The server blocked this image upload.',
    ];
    profilePhotoResponse(false, $uploadMessages[(int)$file['error']] ?? 'The image upload failed. Please try again.', [], 400);
}
if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    profilePhotoResponse(false, 'The uploaded image could not be verified. Please try again.', [], 400);
}
if ((int)$file['size'] > 5 * 1024 * 1024) {
    profilePhotoResponse(false, 'The image must be 5 MB or smaller.', [], 413);
}
$extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
if (!isset($allowed[$extension]) || $allowed[$extension] !== $mime || @getimagesize($file['tmp_name']) === false) {
    profilePhotoResponse(false, 'Only valid JPG, GIF, PNG and WEBP images are accepted.', [], 400);
}

$temporary = '';
$destination = '';
$newFileCreated = false;
$backupPath = '';

try {
    $pdo->beginTransaction();
    if ($existing) {
        $photoId = (int)$existing['photo_id'];
    } else {
        $insert = $pdo->prepare('INSERT INTO users_photo (user_id, file, original, type, date_added, resized, error, compliant) VALUES (?, ?, ?, ?, ?, ?, ?, 0)');
        $insert->execute([$memberId, '', '', $imageType, date('YmdHis'), '', '']);
        $photoId = (int)$pdo->lastInsertId();
    }

    $filename = $imageType === 'photo'
        ? 'pimage-' . $photoId . '-' . bin2hex(random_bytes(8)) . '.' . $extension
        : 'member-logo-' . bin2hex(random_bytes(12)) . '.' . $extension;
    $destination = $imageDirectory . $filename;
    $temporary = $imageDirectory . 'upload-' . bin2hex(random_bytes(12)) . '.' . $extension;
    if (is_file($destination)) {
        $backupPath = $destination . '.backup-' . bin2hex(random_bytes(6));
        if (!copy($destination, $backupPath)) {
            throw new RuntimeException('The existing image could not be protected before replacement.');
        }
    }
    if (!move_uploaded_file($file['tmp_name'], $temporary)) {
        throw new RuntimeException('The image could not be saved.');
    }
    if (!rename($temporary, $destination)) {
        @unlink($temporary);
        throw new RuntimeException('The image could not be moved into storage.');
    }
    $newFileCreated = true;

    $update = $pdo->prepare('UPDATE users_photo SET file = ?, original = ?, date_added = ?, resized = ?, error = ?, compliant = 0 WHERE photo_id = ? AND user_id = ? AND type = ?');
    $update->execute([$filename, '/images/' . $filename, date('YmdHis'), '', '', $photoId, $memberId, $imageType]);
    $photoDestination = configuredFormDestination($pdo, resolveMemberFormName($pdo, 'member_profile_photo'), 'users_data', 'user_id');
    if (!in_array($photoDestination[0], ['users_data', 'users_photo'], true)) {
        saveConfiguredMemberRecord($pdo, $photoDestination, $memberId, [$imageType === 'photo' ? 'profile_photo' : 'profile_logo' => $filename, 'file' => $filename, 'original' => '/images/' . $filename, 'type' => $imageType], []);
    }
    $pdo->commit();

    if ($backupPath !== '' && is_file($backupPath)) @unlink($backupPath);
    if ($existing) {
        $oldFilename = basename((string)$existing['file']);
        $references = $pdo->prepare('SELECT COUNT(*) FROM users_photo WHERE file = ?');
        $references->execute([$existing['file']]);
        if (!(int)$references->fetchColumn() && $oldFilename !== $filename && is_file($imageDirectory . $oldFilename)) unlink($imageDirectory . $oldFilename);
    }
    profilePhotoResponse(true, $imageType === 'photo' ? 'Profile photo updated successfully.' : 'Profile logo updated successfully.', ['url' => rtrim((string)$config['base_url'], '/') . '/images/' . rawurlencode($filename)]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($newFileCreated && $destination !== '' && is_file($destination)) @unlink($destination);
    if ($backupPath !== '' && is_file($backupPath) && $destination !== '') {
        if (is_file($destination)) @unlink($destination);
        @rename($backupPath, $destination);
    }
    if ($temporary !== '' && is_file($temporary)) @unlink($temporary);
    error_log('Profile image update failed: ' . $error->getMessage());
    profilePhotoResponse(false, 'The image could not be updated. Please try again.', [], 500);
}
