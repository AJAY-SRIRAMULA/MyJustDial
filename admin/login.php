
<?php
require __DIR__ . '/../includes/bootstrap.php';

if (!tableExists($pdo, 'administrators')) {
    redirect('/admin/setup.php');
}

if (!empty($_SESSION['admin_id'])) {
    redirect('/admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $statement = $pdo->prepare(
        'SELECT admin_id, admin_name, email, password_hash, status
         FROM administrators
         WHERE email = ?
         LIMIT 1'
    );

    $statement->execute([$email]);
    $admin = $statement->fetch();

    if (
        $admin
        && $admin['status'] === 'active'
        && password_verify($password, $admin['password_hash'])
    ) {
        session_regenerate_id(true);

        $_SESSION['admin_id'] = (int) $admin['admin_id'];
        $_SESSION['admin_name'] = $admin['admin_name'];
        $_SESSION['admin_email'] = $admin['email'];

        redirect('/admin/dashboard.php');
    }

    $error = 'Invalid email address or password.';
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | MyJustDial</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="<?= e($config['base_url']) ?>/assets/css/admin.css">
<link rel="icon" type="image/svg+xml" href="<?=e($config['base_url'])?>/assets/images/favicons/admin-favicon.svg">
</head>
<body class="login-page login-screen">
    <main class="login-card">
        <h2>Admin Area Login</h2>
        <p>Enter <strong>Account Email Address and Password</strong></p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <div class="form-group">
                <input
                    class="form-control input-lg"
                    type="email"
                    name="email"
                    placeholder="Enter Email Address"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="form-group login-password-wrap">
                <input
                    class="form-control input-lg"
                    id="admin-password"
                    type="password"
                    name="password"
                    placeholder="Enter Password"
                    autocomplete="current-password"
                    required
                >

                <button
                    class="login-password-icon"
                    type="button"
                    id="toggle-admin-password"
                    aria-label="Show password"
                    aria-pressed="false"
                    title="Show password"
                >
                    <i class="fa fa-lock" aria-hidden="true"></i>
                </button>
            </div>

            <button
                class="btn btn-primary btn-lg btn-block"
                type="submit"
            >
                Log In Now
            </button>
        </form>

        <a class="forgot-link" href="<?= e($config['base_url']) ?>/admin/setup.php">
            Forgot Password? Click to Reset
        </a>
    </main>

    <script>
        const passwordInput = document.getElementById('admin-password');
        const passwordToggle = document.getElementById('toggle-admin-password');
        const passwordIcon = passwordToggle.querySelector('i');

        passwordToggle.addEventListener('click', function () {
            const isPasswordHidden = passwordInput.type === 'password';

            passwordInput.type = isPasswordHidden ? 'text' : 'password';

            passwordIcon.classList.remove('fa-lock', 'fa-unlock');
            passwordIcon.classList.add(
                isPasswordHidden ? 'fa-unlock' : 'fa-lock'
            );

            passwordToggle.setAttribute(
                'aria-pressed',
                String(isPasswordHidden)
            );

            passwordToggle.setAttribute(
                'aria-label',
                isPasswordHidden ? 'Hide password' : 'Show password'
            );

            passwordToggle.setAttribute(
                'title',
                isPasswordHidden ? 'Hide password' : 'Show password'
            );
        });
    </script>
</body>
</html>
