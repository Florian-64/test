<?php
$page_title = 'Log In — EventHub';
require_once __DIR__ . '/header.php';

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $email    = input('email');
        $password = $_POST['password'] ?? '';

        if ($email === '') $errors[] = 'Email is required.';
        if ($password === '') $errors[] = 'Password is required.';

        if (empty($errors)) {
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                set_flash('success', 'Welcome back, ' . $user['username'] . '!');
                header('Location: ' . url('dashboard.php'));
                exit;
            } else {
                $errors[] = 'Invalid email or password.';
            }
        }
    }
}
?>

<section class="auth-section">
    <div class="auth-card">
        <h1>Log In</h1>
        <p class="auth-subtitle">Welcome back to EventHub</p>

        <?php if ($errors): ?>
            <div class="flash flash-error">
                <?php foreach ($errors as $e): ?>
                    <div><?= sanitize($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= sanitize($email) ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Log In</button>
        </form>

         <p class="auth-footer">
            Don&rsquo;t have an account?
            <a href="<?= url('register.php') ?>">Sign up</a>
        </p>

        <div class="demo-credentials">
            <p class="demo-credentials-title">🔑 Test accounts</p>
            <table class="demo-credentials-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Password</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>admin@eventmanager.local</code></td>
                        <td><code>password</code></td>
                    </tr>
                    <tr>
                        <td><code>visitor@eventmanager.local</code></td>
                        <td><code>password</code></td>
                    </tr>
                    <tr>
                        <td><code>john@eventmanager.local</code></td>
                        <td><code>password</code></td>
                    </tr>
                    <tr>
                        <td><code>organizer@eventmanager.local</code></td>
                        <td><code>password</code></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    </section>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
