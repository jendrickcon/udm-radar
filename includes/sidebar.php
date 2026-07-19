<?php
// includes/sidebar.php — Sidebar nav (replaces Sidebar widget in widgets.py)
// $navItems must be set before including this file, each item:
//   [label, href relative-to-current-folder, icon/emoji]
// Example: $navItems = [['Dashboard', 'index.php', '🏠'], ['Grades', 'grades.php', '📚']];
?>
<nav id="sidebar" class="sidebar">
  <div class="sidebar-brand">
    <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" height="32" onerror="this.style.display='none'">
    <div>UDM-<span><?= APP_NAME === 'UDM-RADAR' ? 'RADAR' : APP_NAME ?></span></div>
  </div>
  <ul class="sidebar-nav">
    <?php foreach ($navItems as [$label, $href, $icon]): ?>
    <li>
      <a href="<?= htmlspecialchars($href) ?>"
         class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === basename($href) ? 'active' : '' ?>">
        <span><?= $icon ?></span> <?= htmlspecialchars($label) ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>logout.php" class="logout-btn">🚪 Logout</a>
  </div>
</nav>
