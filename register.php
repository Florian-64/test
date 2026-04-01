<?php
$page_title = 'Sign Up — EventHub';
require_once __DIR__ . '/header.php';

$errors   = [];
$username = '';
$email    = '';
$role     = 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $username = input('username');
        $email    = input('email');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';
        $role     = input('role');

        // Validation
        if ($username === '') $errors[] = 'Username is required.';
        elseif (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters.';

        if ($email === '') $errors[] = 'Email is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';

        if ($password === '') $errors[] = 'Password is required.';
        elseif (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        if (!in_array($role, ['user', 'organizer'], true)) $role = 'user';

        if (empty($errors)) {
            // Check uniqueness
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                $errors[] = 'Email or username already taken.';
            }
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare(
                'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$username, $email, $hash, $role]);
            $_SESSION['user_id'] = (int)db()->lastInsertId();
            set_flash('success', 'Account created! Welcome aboard.');
            header('Location: ' . url('dashboard.php'));
            exit;
        }
    }
}
?>

<section class="auth-section">
    <div class="auth-card">
        <h1>Create Account</h1>
        <p class="auth-subtitle">Join EventHub and discover events</p>

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
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= sanitize($username) ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= sanitize($email) ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       required minlength="8">
                <small>At least 8 characters</small>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>

            <div class="form-group">
                <label for="role">Account Type</label>
                <select id="role" name="role">
                    <option value="user"      <?= $role === 'user' ? 'selected' : '' ?>>Attendee</option>
                    <option value="organizer"  <?= $role === 'organizer' ? 'selected' : '' ?>>Organizer</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>

        <p class="auth-footer">
            Already have an account?
            <a href="<?= url('login.php') ?>">Log in</a>
        </p>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
