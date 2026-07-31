<?php
session_start();
require_once 'config/db.php'; // Ensure your DB connection is linked

$error = '';

// Handle the login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userid = $_POST['userid'];
    $password = $_POST['password'];

    try {
        $db = getDB();
        // Check if user exists
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Password is correct, set sessions
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['name']       = $user['name'];       // generated column (CONCAT_WS of the split names) — unchanged for every other page
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['identifier'] = $user['user_id'];    // e.g. "23-22-040" or "admin"

            // Route to correct dashboard based on role
            if ($user['role'] === 'student') {
                header("Location: student/index.php");
            } elseif ($user['role'] === 'faculty') {
                header("Location: faculty/index.php");
            } elseif ($user['role'] === 'admin') {
                header("Location: admin/index.php");
            }
            exit();
        } else {
            $error = "Invalid User ID or Password.";
        }
    } catch (PDOException $e) {
        // This will now print the exact database error on the screen
        $error = "System error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | UDM-RADAR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Apply saved theme before CSS renders, same pattern as header.php uses site-wide -->
    <script>
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

        :root {
            --bg-color: #F0F4F8;
            --card-bg: #FFFFFF;
            --text-dark: #1E293B;
            --text-gray: #64748B;
            --border-color: #E2E8F0;
            --navy: #0A192F;
            --navy-hover: #112240;
            --accent-blue: #1E4DB7;
            --error-bg: #FFEBEE;
            --error-text: #C62828;
            --error-border: #C62828;
            
            /* Ambient Background Orbs */
            --orb-1: rgba(30, 77, 183, 0.12); /* Accent blue tint */
            --orb-2: rgba(16, 185, 129, 0.10); /* Soft teal tint */
            --orb-3: rgba(14, 116, 144, 0.10); /* Soft cyan tint */
        }

        [data-theme="dark"] {
            --bg-color: #0B1120;
            --card-bg: #1E293B;
            --text-dark: #F8FAFC;
            --text-gray: #94A3B8;
            --border-color: #334155;
            --navy: #6C8EEF;
            --navy-hover: #8AA6F5;
            --accent-blue: #6C8EEF;
            --error-bg: rgba(239, 68, 68, 0.15);
            --error-text: #FCA5A5;
            --error-border: #EF4444;

            /* Ambient Background Orbs */
            --orb-1: rgba(108, 142, 239, 0.20); 
            --orb-2: rgba(5, 150, 105, 0.15); 
            --orb-3: rgba(14, 116, 144, 0.15);
        }

        body {
            background-color: var(--bg-color);
            display: flex;
            height: 100vh;
            color: var(--text-dark);
            transition: background-color 0.3s ease, color 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        /* ── Animated Background ── */
        .ambient-bg {
            position: absolute;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: 0; /* Keeps it strictly in the background */
            overflow: hidden;
            pointer-events: none; /* Prevents overlap/click blocking */
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            animation: float 15s infinite ease-in-out alternate;
            transition: background-color 0.4s ease;
        }

        .orb-1 {
            width: 45vw; height: 45vw;
            background: var(--orb-1);
            top: -10%; left: -5%;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 40vw; height: 40vw;
            background: var(--orb-2);
            bottom: -10%; right: -5%;
            animation-delay: -4s;
            animation-duration: 18s;
        }

        .orb-3 {
            width: 35vw; height: 35vw;
            background: var(--orb-3);
            bottom: 20%; left: 20%;
            animation-delay: -8s;
            animation-duration: 12s;
        }

        @keyframes float {
            0%   { transform: translate(0, 0) scale(1); }
            50%  { transform: translate(5vw, 10vh) scale(1.05); }
            100% { transform: translate(-5vw, -5vh) scale(0.95); }
        }

        /* ── Foreground Content ── */
        .brand-section, .login-section {
            position: relative;
            z-index: 10; /* Elevates above the background orbs */
        }

        /* Left Side: Branding */
        .brand-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            text-align: center;
        }

        .brand-tagline {
            font-weight: 600;
            color: var(--accent-blue);
            margin-bottom: 14px;
            transition: color 0.3s ease;
        }

        .brand-section p {
            color: var(--text-gray);
            max-width: 400px;
            line-height: 1.6;
            text-shadow: 0 1px 2px var(--bg-color); /* Readability against moving orbs */
        }

        /* Right Side: Login Card */
        .login-section {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
        }

        .login-card {
            background: var(--card-bg); /* Solid standard card, no frosted glass */
            padding: 50px 40px;
            border-radius: 16px;
            border: 1px solid var(--border-color); /* Added explicit border to match dashboards */
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 450px;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .login-card h3 {
            font-size: 1.8rem;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .login-card p {
            color: var(--text-gray);
            margin-bottom: 30px;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
            background-color: var(--bg-color);
            color: var(--text-dark);
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            background-color: var(--card-bg);
            outline: none;
            box-shadow: 0 0 0 3px rgba(30, 77, 183, 0.15);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background-color: var(--navy);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 10px;
        }

        [data-theme="dark"] .btn-login { color: #0A192F; }

        .btn-login:hover {
            background-color: var(--navy-hover);
        }

        .error-msg {
            background-color: var(--error-bg);
            color: var(--error-text);
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border-left: 4px solid var(--error-border);
        }

        /* Theme toggle — floating circle, bottom-right */
        .theme-toggle-fab {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px rgba(15,23,42,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 999;
            transition: transform 0.2s ease, background-color 0.3s ease;
        }
        .theme-toggle-fab:hover { transform: scale(1.07); }

        .theme-toggle-fab svg {
            position: absolute;
            width: 22px;
            height: 22px;
            color: var(--text-dark);
            transition: opacity 0.25s ease, transform 0.35s ease;
        }
        #icon-moon { opacity: 1; transform: rotate(0deg); }
        #icon-sun  { opacity: 0; transform: rotate(-90deg); }
        [data-theme="dark"] #icon-moon { opacity: 0; transform: rotate(90deg); }
        [data-theme="dark"] #icon-sun  { opacity: 1; transform: rotate(0deg); }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .brand-section { padding: 40px 20px 20px; }
            .login-section { padding: 20px; align-items: flex-start; }
            .login-card { padding: 30px 20px; box-shadow: none; background: transparent; border: none; }
        }
    </style>
</head>
<body>

    <!-- Ambient Animated Background -->
    <div class="ambient-bg">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <!-- Left Branding Side -->
    <div class="brand-section">
        <img src="assets/img/logo_sidebar.png" alt="UDM-RADAR Logo" style="width:360px; height:360px; margin-bottom:16px;">
        <h3 class="brand-tagline">Risk Analytics &amp; Decision-support for Academic Records</h3>
        <p>A specialized portal for the College of Computer Studies to monitor academic trajectories and support student success.</p>
    </div>

    <!-- Right Login Side -->
    <div class="login-section">
        <div class="login-card">
            <h3>Welcome Back</h3>
            <p>Please enter your university credentials to continue.</p>

            <?php if (!empty($error)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="userid">Student No. / Username</label>
                    <input type="text" id="userid" name="userid" class="form-control" placeholder="e.g. 23-22-040" required autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn-login">Login to Portal</button>
            </form>
        </div>
    </div>

    <!-- Dark mode toggle -->
    <button id="theme-toggle" class="theme-toggle-fab" aria-label="Toggle dark mode" title="Toggle dark mode">
        <svg id="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
        <svg id="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="5"></circle>
            <line x1="12" y1="1" x2="12" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="23"></line>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
            <line x1="1" y1="12" x2="3" y2="12"></line>
            <line x1="21" y1="12" x2="23" y2="12"></line>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
        </svg>
    </button>

    <script>
        (function() {
            const root = document.documentElement;
            const btn = document.getElementById('theme-toggle');
            let current = localStorage.getItem('theme') || 'light';

            btn.addEventListener('click', () => {
                current = current === 'light' ? 'dark' : 'light';
                root.setAttribute('data-theme', current);
                localStorage.setItem('theme', current);
            });
        })();
    </script>

</body>
</html>