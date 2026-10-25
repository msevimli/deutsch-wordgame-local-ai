<?php
require __DIR__ . '/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($username === '') {
        $error = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
        $error = 'Username must be 3-30 characters (letters, numbers, _ . -).';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email address is required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = db()->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
        $stmt->execute([':u' => $username, ':e' => $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email is already in use.';
        } else {
            try {
                $stmt = db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (:u, :e, :p)');
                $stmt->execute([
                    ':u' => $username,
                    ':e' => $email,
                    ':p' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                header('Location: login.php?registered=1');
                exit;
            } catch (PDOException $ex) {
                $error = 'Username or email is already in use.';
            }
        }
    }
}
$pageTitle = 'Register';
require __DIR__ . '/header.php'; ?>

<div class="card">
  <h1>Create account</h1>
  <?php if ($error !== ''): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post" action="register.php" autocomplete="off">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required autofocus value="<?= htmlspecialchars($username) ?>">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email) ?>">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>
    <label for="password2">Confirm password</label>
    <input type="password" id="password2" name="password2" required>
    <button type="submit" class="btn">Register</button>
  </form>
  <p class="hint">Already have an account? <a href="login.php">Login</a></p>
</div>
</body>
</html>
