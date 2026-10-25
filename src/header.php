<?php
$pageTitle = $pageTitle ?? 'MyWordGame';
$loggedInHeader = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<nav>
  <a href="index.php" class="brand">MyWordGame</a>
  <div class="nav-right">
    <div class="nav-links" id="navLinks">
      <a href="index.php">Home</a>
      <?php if ($loggedInHeader): ?>
        <a href="dashboard.php">Dashboard</a>
        <a href="game.php">Word Trainer</a>
        <a href="logout.php">Logout</a>
      <?php else: ?>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
      <?php endif; ?>
    </div>
    <button class="menu-toggle" id="menuToggle" aria-label="Menu" aria-expanded="false" aria-controls="navLinks">&#9776;</button>
  </div>
  <div class="backdrop" id="backdrop"></div>
</nav>
<script>
(function () {
  var toggle = document.getElementById('menuToggle');
  var nav = toggle && toggle.closest('nav');
  var links = document.getElementById('navLinks');
  var backdrop = document.getElementById('backdrop');
  if (!toggle || !nav || !links) return;

  if (backdrop) backdrop.addEventListener('click', function () { setOpen(false); });

  function setOpen(open) {
    nav.classList.toggle('menu-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  toggle.addEventListener('click', function (e) {
    e.stopPropagation();
    setOpen(!nav.classList.contains('menu-open'));
  });

  document.addEventListener('click', function (e) {
    if (!nav.contains(e.target)) setOpen(false);
  });

  Array.prototype.forEach.call(links.querySelectorAll('a'), function (a) {
    a.addEventListener('click', function () { setOpen(false); });
  });
})();
</script>