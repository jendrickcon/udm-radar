<?php
// includes/footer.php — closes the <body>/<html> opened by header.php.
// Place any shared bottom-of-page <script> tags here so every page gets them.
require_once __DIR__ . '/glossary_modal.php';
?>
<script src="<?= BASE_URL ?>assets/js/ui.js"></script>
<script src="<?= BASE_URL ?>assets/js/display-preferences.js?v=<?= file_exists(__DIR__ . '/../assets/js/display-preferences.js') ? filemtime(__DIR__ . '/../assets/js/display-preferences.js') : 1 ?>"></script>
</body>
</html>
