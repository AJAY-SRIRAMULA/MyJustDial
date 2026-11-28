<?php
function passwordResetRequest(PDO $pdo, array $form): string
{
    $generic = 'If an account exists for this email address, a password reset email has been sent.';
    if (!hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? '')) || !empty($_POST['website_address'])) return 'Please refresh the page and try again.';
    $error = configuredCaptchaError($pdo, $form['fields']);
    if ($error !== '') return $error;
    $email = $_POST['fields']['email'] ?? '';
    if (!is_string($email) || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) return 'Enter a valid email address.';
    if (time() - (int)($_SESSION['password_reset_requested'] ?? 0) < 60) return $generic;
    $_SESSION['password_reset_requested'] = time();
    $query = $pdo->prepare('SELECT user_id,email,password,active FROM users_data WHERE email=? LIMIT 1');
    $query->execute([trim($email)]);
    $member = $query->fetch(PDO::FETCH_ASSOC);
    if (!$member || !isActiveMemberRecord($member['active'])) return $generic;
    $id = (int)$member['user_id'];
    $key = 'myjustdial-reset-'.$id;
    $token = bin2hex(random_bytes(32));
    $digest = hash_hmac('sha256', $token, $member['password']);
    $now = time();
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT password FROM users_data WHERE user_id=? FOR UPDATE');
        $lock->execute([$id]);
        if ($lock->fetchColumn() !== $member['password']) { $pdo->rollBack(); return $generic; }
        $old = $pdo->prepare('SELECT retrieval_session_token FROM password_retrieval_sessions WHERE user_token=? FOR UPDATE');
        $old->execute([$key]);
        $previous = explode(':', (string)$old->fetchColumn());
        if ($now - (int)($previous[0] ?? 0) < 60) { $pdo->rollBack(); return $generic; }
        $pdo->prepare('INSERT INTO password_retrieval_sessions (user_token,retrieval_session_token) VALUES (?,?) ON DUPLICATE KEY UPDATE retrieval_session_token=VALUES(retrieval_session_token)')->execute([$key,$now.':'.($now+1800).':'.$digest]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    $origin = rtrim(getenv('MYJUSTDIAL_RESET_ORIGIN') ?: 'http://localhost', '/');
    $url = $origin . $GLOBALS['config']['base_url'] . '/public/password-reset.php?uid='.$id.'&token='.$token;
    if (!@mail($member['email'], 'Reset your member password', "Open this link within 30 minutes to reset your password:\n".$url."\nIf you did not request this, ignore this message.")) {
        error_log('Password retrieval: mail delivery failed. Configure PHP mail transport.');
    }
    return $generic;
}

function passwordResetComplete(PDO $pdo, int $id, string $token, string $password): bool
{
    if ($id <= 0 || !preg_match('/^[a-f0-9]{64}$/D', $token)) return false;
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT password,active FROM users_data WHERE user_id=? FOR UPDATE');
        $query->execute([$id]);
        $member = $query->fetch(PDO::FETCH_ASSOC);
        $key = 'myjustdial-reset-'.$id;
        $query = $pdo->prepare('SELECT retrieval_session_token FROM password_retrieval_sessions WHERE user_token=? FOR UPDATE');
        $query->execute([$key]);
        $parts = explode(':', (string)$query->fetchColumn());
        if (!$member || !isActiveMemberRecord($member['active']) || count($parts)!==3 || (int)$parts[1]<time() || !hash_equals($parts[2],hash_hmac('sha256',$token,$member['password']))) { $pdo->rollBack(); return false; }
        $pdo->prepare('UPDATE users_data SET password=? WHERE user_id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
        $pdo->prepare('DELETE FROM password_retrieval_sessions WHERE user_token=?')->execute([$key]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
