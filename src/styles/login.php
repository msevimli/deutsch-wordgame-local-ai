<?php
require __DIR__ . '/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']  = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Invalid username or password.';
}
$pageTitle = 'Login';
require __DIR__ . '/header.php'; ?>

<div class="card">
  <h1>Sign in</h1>
  <?php if (isset($_GET['registered'])): ?>
    <div class="success">Account created! Please sign in.</div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post" action="login.php" autocomplete="off">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required autofocus value="<?= htmlspecialchars($username) ?>">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>
    <button type="submit" class="btn">Login</button>
  </form>
  <p class="hint">Default account: admin / admin123</p>
  <p class="hint">Don't have an account? <a href="register.php">Register</a></p>
</div>
</body>
</html>
