<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/constants.php';
}
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>

    <!-- Pre-render Accessibility & Theme Initializer (Anti-FOUC) -->
    <script>
    (function() {
        try {
            var raw = localStorage.getItem('udmRadarDisplayPreferencesV1');
            var p = raw ? JSON.parse(raw) : null;
            var legacy = localStorage.getItem('theme');
            var colorStyle = (p && ['ccs','classic'].indexOf(p.colorStyle) !== -1) ? p.colorStyle : 'ccs';
            var theme = (p && ['system','light','dark'].indexOf(p.theme) !== -1) ? p.theme : (legacy === 'light' || legacy === 'dark' ? legacy : 'system');
            var activeTheme = theme;
            if (theme === 'system') {
                activeTheme = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
            }
            var root = document.documentElement;
            root.setAttribute('data-color-style', colorStyle);
            root.setAttribute('data-theme', activeTheme);
            if (p) {
                if (['small','default','large','extra-large'].indexOf(p.textSize) !== -1) {
                    root.setAttribute('data-font-size', p.textSize);
                } else {
                    root.setAttribute('data-font-size', 'default');
                }
                if (['system','reduce','allow'].indexOf(p.motion) !== -1) {
                    var activeMotion = p.motion === 'system' ? ((window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) ? 'reduce' : 'allow') : p.motion;
                    root.setAttribute('data-motion', activeMotion);
                } else {
                    root.setAttribute('data-motion', (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) ? 'reduce' : 'allow');
                }
                root.setAttribute('data-underline-links', p.underlineLinks === true ? 'true' : 'false');
            } else {
                root.setAttribute('data-font-size', 'default');
                root.setAttribute('data-motion', (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) ? 'reduce' : 'allow');
                root.setAttribute('data-underline-links', 'false');
            }
            root.removeAttribute('data-contrast');
        } catch (e) {
            document.documentElement.setAttribute('data-color-style', 'ccs');
            document.documentElement.setAttribute('data-theme', 'light');
            document.documentElement.setAttribute('data-font-size', 'default');
            document.documentElement.setAttribute('data-motion', 'allow');
            document.documentElement.setAttribute('data-underline-links', 'false');
            document.documentElement.removeAttribute('data-contrast');
        }
    })();
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css?v=<?= file_exists(__DIR__ . '/../assets/css/dashboard.css') ? filemtime(__DIR__ . '/../assets/css/dashboard.css') : 1 ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/accessibility-preferences.css?v=<?= file_exists(__DIR__ . '/../assets/css/accessibility-preferences.css') ? filemtime(__DIR__ . '/../assets/css/accessibility-preferences.css') : 1 ?>">
</head>
<body>