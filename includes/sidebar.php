<?php
// includes/sidebar.php

/** @var array<int, array{0:string, 1:string, 2:string}> $navItems */
$navItems = $navItems ?? [];

// Calculate Items Requiring Attention for Notification Badges
$navBadges = [];
if (isset($user) && isset($user['role'])) {
    $badgeDb = getDB();
    
    if ($user['role'] === 'faculty') {
        // FIXED: Faculty now counts the new support_case_referrals table
        $stmtBadges = $badgeDb->prepare("
            SELECT 
                (SELECT COUNT(DISTINCT f.id) FROM feedback_reports f JOIN student_profiles sp ON sp.user_id = f.submitted_by JOIN faculty_class_loads fcl ON fcl.subject_id = f.subject_id AND fcl.section = sp.section JOIN users u ON u.id = f.submitted_by WHERE fcl.faculty_user_id = ? AND u.role = 'student' AND f.status IN ('open', 'faculty_review'))
                +
                (SELECT COUNT(*) FROM support_case_referrals WHERE faculty_id = ? AND status = 'needs_review')
        ");
        $stmtBadges->execute([$user['id'], $user['id']]);
        $count = (int)$stmtBadges->fetchColumn();
        if ($count > 0) $navBadges['Concerns & Reports'] = $count;
        
    } elseif ($user['role'] === 'admin') {
        // FIXED: Admin now counts Parent cases that are not closed
        $count = (int)$badgeDb->query("
            SELECT 
            (SELECT COUNT(*) FROM feedback_reports WHERE status IN ('open', 'awaiting_admin')) +
            (SELECT COUNT(*) FROM pending_corrections WHERE status = 'pending') +
            (SELECT COUNT(*) FROM pending_grade_batches WHERE status = 'pending') +
            (SELECT COUNT(*) FROM academic_support_cases WHERE status != 'closed')
        ")->fetchColumn();
        
        if ($count > 0) {
            $navBadges['Activity & Inbox'] = $count;
        }
        
    } elseif ($user['role'] === 'student') {
        // FIXED: Student counts child referrals waiting for their acknowledgment
        $stmtBadges = $badgeDb->prepare("
            SELECT COUNT(r.id) 
            FROM support_case_referrals r 
            JOIN academic_support_cases c ON r.case_id = c.id 
            WHERE c.student_id = ? AND r.status = 'action_taken'
        ");
        $stmtBadges->execute([$user['id']]);
        $count = (int)$stmtBadges->fetchColumn();
        if ($count > 0) $navBadges['Feedback & Support'] = $count;
    }
}
?>
<style>
.nav-badge { background: var(--risk-high); color: white; font-size: 0.75rem; font-weight: 700; padding: 2px 7px; border-radius: 12px; margin-left: auto; line-height: 1.2; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.sidebar-link { display: flex; align-items: center; width: 100%; }
</style>
<nav id="sidebar" class="sidebar">
  <div class="sidebar-brand">
    <img src="<?= BASE_URL ?>assets/img/logo_sidebar.png" alt="Logo" class="sidebar-logo" onerror="this.style.display='none'">
  </div>
  <ul class="sidebar-nav">
    <?php foreach ($navItems as [$label, $href, $icon]): 
        $badgeHtml = isset($navBadges[$label]) ? '<span class="nav-badge">' . $navBadges[$label] . '</span>' : '';
    ?>
    <?php
        $svg = match($label) {
            'Home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
            'Dashboard' => '<rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect>',
            'Students', 'Faculty' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
            'Grades' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>',
            'Academic History' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>',
            'Performance Trend', 'Performance Trends' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline>',
            'Program Analytics', 'Class Analytics' => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>',
            'Feedback & Reports', 'Feedback & Support', 'Activity & Inbox', 'Concerns & Reports' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>',
            'Settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>',
            default => '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>'
        };
    ?>
    <li>
      <a href="<?= htmlspecialchars($href) ?>" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === basename($href) ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon">
            <?= $svg ?>
        </svg>
        <span><?= htmlspecialchars($label) ?></span> <?= $badgeHtml ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
  <div class="sidebar-footer">
    <button onclick="toggleTheme()" class="theme-toggle-btn">
      <div class="theme-icon-wrapper">
          <svg id="sidebar-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
          <svg id="sidebar-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
      </div>
      <span id="theme-toggle-text">Dark Mode</span>
    </button>
    <a href="<?= BASE_URL ?>logout.php" class="logout-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sidebar-icon"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        <span>Logout</span>
    </a>
  </div>
</nav>
<script>
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateToggleText(newTheme);
}
function updateToggleText(theme) {
    const textEl = document.getElementById('theme-toggle-text');
    if(textEl) textEl.innerText = theme === 'light' ? 'Dark Mode' : 'Light Mode';
}
document.addEventListener('DOMContentLoaded', () => {
    updateToggleText(document.documentElement.getAttribute('data-theme'));
    const activeLink = document.querySelector('.sidebar-link.active');
    if (activeLink) activeLink.scrollIntoView({ block: 'nearest' });
});
</script>