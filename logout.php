<?php
// 1. Resume the current active session
session_start();

// 2. Clear all session variables (like user_id, role, name)
session_unset();

// 3. Completely destroy the session on the server
session_destroy();

// 4. Redirect the user back to the login page
header("Location: login.php");
exit();
?>