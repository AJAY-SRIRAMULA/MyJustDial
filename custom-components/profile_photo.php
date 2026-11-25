<?php
$componentMember = $componentContext['member'] ?? null;
$componentPdo = $componentContext['pdo'] ?? null;
$componentPreview = !empty($componentContext['preview']) || !is_array($componentMember) || empty($componentMember['user_id']);
$componentBaseUrl = rtrim((string)($componentContext['base_url'] ?? ''), '/');
$componentPhoto = '';
$componentLogo = '';

if (!$componentPreview && $componentPdo instanceof PDO) {
    $photoQuery = $componentPdo->prepare('SELECT file, original FROM users_photo WHERE user_id = ? AND type = ? ORDER BY photo_id DESC LIMIT 1');
    $photoQuery->execute([(int)$componentMember['user_id'], 'photo']);
    $photoRecord = $photoQuery->fetch(PDO::FETCH_ASSOC) ?: [];
    $logoQuery = $componentPdo->prepare('SELECT file, original FROM users_photo WHERE user_id = ? AND type = ? ORDER BY photo_id DESC LIMIT 1');
    $logoQuery->execute([(int)$componentMember['user_id'], 'logo']);
    $logoRecord = $logoQuery->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach ([['record' => $photoRecord, 'target' => 'photo'], ['record' => $logoRecord, 'target' => 'logo']] as $imageItem) {
        $file = trim((string)($imageItem['record']['file'] ?? ''));
        require_once __DIR__ . '/../includes/member_forms.php';
        $url = memberPhotoUrl($file, (string)($imageItem['record']['original'] ?? ''));
        if ($imageItem['target'] === 'photo') $componentPhoto = $url;
        else $componentLogo = $url;
    }
}
$componentAdminMember = !empty($_SESSION['admin_id']) && isset($_GET['admin_member']) ? (int)$componentMember['user_id'] : 0;
$componentCsrf = (string)($componentContext['csrf'] ?? '');
$componentEndpoint = $componentBaseUrl . '/public/account/profile-photo-action.php';
?>
<div class="mjd-profile-upload" data-profile-upload data-admin-member="<?= $componentAdminMember ?>" data-preview="<?= $componentPreview ? '1' : '0' ?>" data-endpoint="<?= e($componentEndpoint) ?>" data-csrf="<?= e($componentCsrf) ?>">
    <h2>Upload Profile Photo</h2>
    <div class="mjd-profile-accepted">ACCEPTED FORMATS: JPG, GIF, PNG &amp; WEBP</div>
    <div class="mjd-profile-upload-grid">
        <section class="mjd-profile-upload-card">
            <h3>Profile Photo</h3>
            <p>Recommended Size: 400x400 pixels</p>
            <div class="mjd-profile-image-frame"><img data-image-preview="photo" src="<?= e($componentPhoto) ?>" alt="Profile Photo"<?= $componentPhoto === '' ? ' hidden' : '' ?>><span data-image-empty="photo"<?= $componentPhoto !== '' ? ' hidden' : '' ?>>Profile Photo</span></div>
            <input class="mjd-profile-file" type="file" accept=".jpg,.jpeg,.gif,.png,.webp,image/jpeg,image/gif,image/png,image/webp" data-file-input="photo"<?= $componentPreview ? ' disabled' : '' ?>>
            <div class="mjd-profile-actions"><button type="button" data-change="photo"<?= $componentPreview ? ' disabled' : '' ?>>Change Photo</button><button type="button" class="mjd-profile-delete" data-delete="photo"<?= $componentPreview || $componentPhoto === '' ? ' disabled' : '' ?>>⊗ Delete</button></div>
        </section>
        <section class="mjd-profile-upload-card">
            <h3>Profile Logo</h3>
            <p>Recommended Size: 400x400 pixels</p>
            <div class="mjd-profile-image-frame"><img data-image-preview="logo" src="<?= e($componentLogo) ?>" alt="Profile Logo"<?= $componentLogo === '' ? ' hidden' : '' ?>><span data-image-empty="logo"<?= $componentLogo !== '' ? ' hidden' : '' ?>>Profile Logo</span></div>
            <input class="mjd-profile-file" type="file" accept=".jpg,.jpeg,.gif,.png,.webp,image/jpeg,image/gif,image/png,image/webp" data-file-input="logo"<?= $componentPreview ? ' disabled' : '' ?>>
            <div class="mjd-profile-actions"><button type="button" data-change="logo"<?= $componentPreview ? ' disabled' : '' ?>>Change Logo</button><button type="button" class="mjd-profile-delete" data-delete="logo"<?= $componentPreview || $componentLogo === '' ? ' disabled' : '' ?>>⊗ Delete</button></div>
        </section>
    </div>
    <div class="mjd-profile-upload-message" role="status" aria-live="polite"></div>
</div>
<style>
.mjd-profile-upload{width:100%;color:#333}.mjd-profile-upload h2{font-size:23px;font-weight:600;margin:0 0 10px}.mjd-profile-accepted{background:#050505;color:#fff;padding:12px 9px;font-size:14px;font-weight:600;margin-bottom:20px}.mjd-profile-upload-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px}.mjd-profile-upload-card{border:1px solid #e8e8e8;padding:16px 12px 18px;text-align:center;min-width:0}.mjd-profile-upload-card h3{font-size:21px;margin:0 0 8px;font-weight:600}.mjd-profile-upload-card p{font-size:14px;margin:0 0 13px}.mjd-profile-image-frame{height:300px;max-width:300px;width:100%;border:1px solid #ddd;margin:0 auto 8px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fff}.mjd-profile-image-frame img{width:100%;height:100%;object-fit:contain}.mjd-profile-image-frame span{color:#999}.mjd-profile-file{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}.mjd-profile-actions{display:grid;grid-template-columns:1fr 1fr;gap:5px}.mjd-profile-actions button{height:46px;border:0;background:#050505;color:#fff;font-size:14px;cursor:pointer}.mjd-profile-actions .mjd-profile-delete{background:#f00000}.mjd-profile-actions button:disabled{opacity:.5;cursor:not-allowed}.mjd-profile-upload-message{font-size:13px;margin-top:10px}.mjd-profile-upload-message:empty{display:none}@media(max-width:650px){.mjd-profile-upload-grid{grid-template-columns:1fr}.mjd-profile-image-frame{height:260px}}
</style>
<script>
(function () {
    document.querySelectorAll('[data-profile-upload]').forEach(function (root) {
        if (root.dataset.initialized === '1') return;
        root.dataset.initialized = '1';

        var message = root.querySelector('.mjd-profile-upload-message');
        var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        var maxBytes = 5 * 1024 * 1024;

        function say(text, isError) {
            message.textContent = text;
            message.style.color = isError ? '#b42318' : '#067647';
        }

        function syncAccountImage(type, url) {
            if (type === 'logo') {
                document.querySelectorAll('[data-account-logo]').forEach(function (avatar) {
                    var child = document.createElement(url ? 'img' : 'i');
                    if (url) { child.src = url; child.alt = ''; }
                    else child.className = 'fa fa-user';
                    avatar.replaceChildren(child);
                });
            }
            var selector = type === 'logo' ? '[data-account-dropdown-logo]' : '[data-account-photo]';
            document.querySelectorAll(selector).forEach(function (avatar) {
                var next = document.createElement(url ? 'img' : 'span');
                var className = type === 'logo' ? 'member-dropdown-avatar' : 'member-profile-avatar';
                next.className = className + (!url && type === 'logo' ? ' member-avatar-placeholder' : '');
                next.setAttribute(type === 'logo' ? 'data-account-dropdown-logo' : 'data-account-photo', '');
                if (url) { next.src = url; next.alt = type === 'logo' ? 'Profile logo' : 'Profile photo'; }
                else { var icon = document.createElement('i'); icon.className = 'fa fa-user'; next.appendChild(icon); }
                avatar.replaceWith(next);
            });
        }

        root.querySelectorAll('[data-change]').forEach(function (button) {
            button.addEventListener('click', function () {
                root.querySelector('[data-file-input="' + button.dataset.change + '"]').click();
            });
        });

        root.querySelectorAll('[data-file-input]').forEach(function (input) {
            input.addEventListener('change', async function () {
                var type = input.dataset.fileInput;
                var file = input.files && input.files[0];
                if (!file) return;

                var extension = (file.name.split('.').pop() || '').toLowerCase();
                var allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (file.size > maxBytes) {
                    say('The image is too large. Maximum allowed size is 5 MB.', true);
                    input.value = '';
                    return;
                }
                if (allowedExtensions.indexOf(extension) === -1 || (file.type && allowedTypes.indexOf(file.type) === -1)) {
                    say('Invalid image type. Only JPG, JPEG, PNG, GIF and WEBP files are accepted.', true);
                    input.value = '';
                    return;
                }

                var preview = root.querySelector('[data-image-preview="' + type + '"]');
                var empty = root.querySelector('[data-image-empty="' + type + '"]');
                var deleteButton = root.querySelector('[data-delete="' + type + '"]');
                var previousSrc = preview.getAttribute('src');
                var wasHidden = preview.hidden;
                var objectUrl = URL.createObjectURL(file);
                preview.src = objectUrl;
                preview.hidden = false;
                empty.hidden = true;

                var data = new FormData();
                data.append('csrf_token', root.dataset.csrf);
                data.append('image_type', type);
                if (Number(root.dataset.adminMember) > 0) data.append('admin_member', root.dataset.adminMember);
                data.append('image', file);
                say('Uploading image...', false);

                try {
                    var response = await fetch(root.dataset.endpoint, {
                        method: 'POST',
                        body: data,
                        credentials: 'same-origin'
                    });
                    var result = await response.json();
                    if (!response.ok || !result.ok) throw new Error(result.message || 'The image could not be uploaded.');
                    preview.src = result.url;
                    syncAccountImage(type, result.url);
                    deleteButton.disabled = false;
                    say(result.message || 'Image updated successfully.', false);
                } catch (error) {
                    if (previousSrc) {
                        preview.src = previousSrc;
                        preview.hidden = wasHidden;
                        empty.hidden = !wasHidden;
                    } else {
                        preview.removeAttribute('src');
                        preview.hidden = true;
                        empty.hidden = false;
                    }
                    say(error.message || 'The image could not be uploaded. Please try again.', true);
                } finally {
                    URL.revokeObjectURL(objectUrl);
                    input.value = '';
                }
            });
        });

        root.querySelectorAll('[data-delete]').forEach(function (button) {
            button.addEventListener('click', async function () {
                if (!window.confirm('Delete this image?')) return;
                var type = button.dataset.delete;
                var data = new FormData();
                data.append('csrf_token', root.dataset.csrf);
                data.append('image_type', type);
                if (Number(root.dataset.adminMember) > 0) data.append('admin_member', root.dataset.adminMember);
                data.append('action', 'delete');
                say('Deleting image...', false);
                try {
                    var response = await fetch(root.dataset.endpoint, {
                        method: 'POST',
                        body: data,
                        credentials: 'same-origin'
                    });
                    var result = await response.json();
                    if (!response.ok || !result.ok) throw new Error(result.message || 'The image could not be deleted.');
                    var image = root.querySelector('[data-image-preview="' + type + '"]');
                    image.hidden = true;
                    image.removeAttribute('src');
                    root.querySelector('[data-image-empty="' + type + '"]').hidden = false;
                    button.disabled = true;
                    syncAccountImage(type, '');
                    say(result.message || 'Image deleted successfully.', false);
                } catch (error) {
                    say(error.message || 'The image could not be deleted. Please try again.', true);
                }
            });
        });
    });
})();
</script>
