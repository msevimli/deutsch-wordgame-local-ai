<?php
require __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = db();
$meStmt = $pdo->prepare('SELECT id, username, email, role FROM users WHERE id = :id');
$meStmt->execute([':id' => (int)$_SESSION['user_id']]);
$me = $meStmt->fetch();
if (!$me) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$isAdmin = ($me['role'] === 'admin');
$view = ($_GET['view'] ?? 'profile') === 'users' ? 'users' : 'profile';
if ($view === 'users' && !$isAdmin) {
    $view = 'profile';
}

$flash = '';
$error = '';

function validateUserFields(string $username, string $email, string $password, string $password2): string
{
    if ($username === '') {
        return 'Username is required.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
        return 'Username must be 3-30 characters (letters, numbers, _ . -).';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'A valid email address is required.';
    }
    if ($password !== '') {
        if (strlen($password) < 6) {
            return 'Password must be at least 6 characters.';
        }
        if ($password !== $password2) {
            return 'Passwords do not match.';
        }
    }
    return '';
}

function usernameOrEmailTaken(PDO $pdo, string $username, string $email, int $excludeId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE (username = :u OR email = :e) AND id != :id LIMIT 1');
    $stmt->execute([':u' => $username, ':e' => $email, ':id' => $excludeId]);
    return (bool)$stmt->fetch();
}

function applyUserUpdate(PDO $pdo, int $id, string $username, string $email, string $role, string $password): void
{
    if ($password !== '') {
        $stmt = $pdo->prepare('UPDATE users SET username = :u, email = :e, role = :r, password_hash = :p WHERE id = :id');
        $stmt->execute([
            ':u' => $username, ':e' => $email, ':r' => $role,
            ':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id,
        ]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET username = :u, email = :e, role = :r WHERE id = :id');
        $stmt->execute([':u' => $username, ':e' => $email, ':r' => $role, ':id' => $id]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        $error = validateUserFields($username, $email, $password, $password2);
        if ($error === '' && usernameOrEmailTaken($pdo, $username, $email, (int)$me['id'])) {
            $error = 'Username or email is already in use.';
        }
        if ($error === '') {
            applyUserUpdate($pdo, (int)$me['id'], $username, $email, $me['role'], $password);
            $_SESSION['username'] = $username;
            $me['username'] = $username;
            $me['email'] = $email;
            $flash = 'Profile updated.';
        }
    } elseif ($action === 'edituser' && $isAdmin) {
        $id        = (int)($_POST['id'] ?? 0);
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $role      = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if ($id === (int)$me['id'] && $role !== 'admin') {
            $error = 'You cannot change your own admin role.';
        } else {
            $error = validateUserFields($username, $email, $password, $password2);
            if ($error === '' && usernameOrEmailTaken($pdo, $username, $email, $id)) {
                $error = 'Username or email is already in use.';
            }
            if ($error === '') {
                applyUserUpdate($pdo, $id, $username, $email, $role, $password);
                $flash = 'User updated.';
            }
        }
    }
}

$pageTitle = 'Dashboard';
require __DIR__ . '/header.php'; ?>

<main class="dash">
  <div class="panel">
    <div class="panel-head">
      <div>
        <h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>
        <p>You are logged in to your dashboard.</p>
      </div>
      <div class="actions">
        <a class="btn" href="game.php">Word Trainer</a>
        <a class="btn ghost" href="logout.php">Logout</a>
      </div>
    </div>

    <div class="tabs" role="tablist">
      <a class="tab<?= $view === 'profile' ? ' active' : '' ?>" href="dashboard.php?view=profile">Profile</a>
      <?php if ($isAdmin): ?>
        <a class="tab<?= $view === 'users' ? ' active' : '' ?>" href="dashboard.php?view=users">Users</a>
      <?php endif; ?>
    </div>

    <?php if ($flash !== ''): ?>
      <div class="success"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($view === 'profile'): ?>
      <form method="post" action="dashboard.php?view=profile" class="profile-form">
        <input type="hidden" name="action" value="profile">
        <label for="p_username">Username</label>
        <input type="text" id="p_username" name="username" required value="<?= htmlspecialchars($me['username']) ?>">
        <label for="p_email">Email</label>
        <input type="email" id="p_email" name="email" required value="<?= htmlspecialchars($me['email']) ?>">
        <label for="p_password">New password (leave blank to keep current)</label>
        <input type="password" id="p_password" name="password" autocomplete="new-password">
        <label for="p_password2">Confirm new password</label>
        <input type="password" id="p_password2" name="password2" autocomplete="new-password">
        <button type="submit" class="btn">Save Profile</button>
      </form>
    <?php else: ?>
      <?php
      $users = $pdo->query('SELECT id, username, email, role, created_at FROM users ORDER BY id')->fetchAll();
      ?>
      <table class="user-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Created</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['role']) ?></td>
            <td><?= htmlspecialchars($u['created_at']) ?></td>
            <td><button type="button" class="btn ghost edit-user-btn" data-id="<?= (int)$u['id'] ?>">Edit</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <div id="editUserWrap" class="edit-user" hidden>
        <h2>Edit User</h2>
        <form method="post" action="dashboard.php?view=users" class="profile-form">
          <input type="hidden" name="action" value="edituser">
          <input type="hidden" name="id" id="eu_id">
          <label for="eu_username">Username</label>
          <input type="text" id="eu_username" name="username" required>
          <label for="eu_email">Email</label>
          <input type="email" id="eu_email" name="email" required>
          <label for="eu_role">Role</label>
          <select id="eu_role" name="role">
            <option value="user">user</option>
            <option value="admin">admin</option>
          </select>
          <label for="eu_password">New password (leave blank to keep current)</label>
          <input type="password" id="eu_password" name="password" autocomplete="new-password">
          <label for="eu_password2">Confirm new password</label>
          <input type="password" id="eu_password2" name="password2" autocomplete="new-password">
          <button type="submit" class="btn">Save User</button>
          <button type="button" id="eu_cancel" class="btn ghost">Cancel</button>
        </form>
      </div>
    <?php endif; ?>

    <p class="meta">Logged in as <?= htmlspecialchars($_SESSION['username']) ?> (user #<?= (int)$me['id'] ?>, <?= htmlspecialchars($me['role']) ?>)</p>
  </div>
</main>

<?php if ($view === 'users'): ?>
<script>
(function () {
    var wrap = document.getElementById('editUserWrap');
    var data = <?= json_encode($users, JSON_UNESCAPED_UNICODE) ?>;
    var map = {};
    data.forEach(function (u) { map[u.id] = u; });

    document.querySelectorAll('.edit-user-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var u = map[btn.dataset.id];
            if (!u) return;
            document.getElementById('eu_id').value = u.id;
            document.getElementById('eu_username').value = u.username;
            document.getElementById('eu_email').value = u.email;
            document.getElementById('eu_role').value = u.role;
            document.getElementById('eu_password').value = '';
            document.getElementById('eu_password2').value = '';
            wrap.hidden = false;
            wrap.scrollIntoView({ behavior: 'smooth' });
        });
    });

    document.getElementById('eu_cancel').addEventListener('click', function () {
        wrap.hidden = true;
    });
})();
</script>
<?php endif; ?>

</body>
</html>
