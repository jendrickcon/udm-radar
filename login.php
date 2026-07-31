<?php
session_start();
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userid = $_POST['userid'];
    $password = $_POST['password'];

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['name']       = $user['name'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['identifier'] = $user['user_id'];

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
            
            /* SVG Background Variables - Gold Line & Arrow */
            --chart-bar: rgba(10, 25, 47, 0.03);
            --chart-line: rgba(217, 119, 6, 0.35); /* Gold */
            --chart-arrow: rgba(217, 119, 6, 0.55); /* Gold */
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

            /* SVG Background Variables - Bright Neon Gold */
            --chart-bar: rgba(255, 255, 255, 0.02);
            --chart-line: rgba(251, 191, 36, 0.25);
            --chart-arrow: rgba(251, 191, 36, 0.45);
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

        /* ── Formal SVG Animation Background ── */
        .academic-bg {
            position: absolute;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: 0;
            pointer-events: none;
        }

        .academic-bg svg {
            width: 100%;
            height: 100%;
        }

        .chart-bar {
            fill: var(--chart-bar);
            transform-origin: bottom;
            opacity: 0;
            animation: riseUp 1.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            transition: fill 0.4s ease;
        }
        
        .bar-1 { animation-delay: 0.1s; }
        .bar-2 { animation-delay: 0.5s; }
        .bar-3 { animation-delay: 0.9s; }
        .bar-4 { animation-delay: 1.3s; }
        .bar-5 { animation-delay: 1.7s; }
        .bar-6 { animation-delay: 2.1s; }

        @keyframes riseUp {
            from { transform: scaleY(0); opacity: 0; }
            to   { transform: scaleY(1); opacity: 1; }
        }

        .chart-line {
            fill: none;
            stroke: var(--chart-line);
            stroke-width: 8;
            stroke-linecap: round;
            stroke-linejoin: round;
            /* Recalculated to exact path length (1466px) so the timer is perfectly synced */
            stroke-dasharray: 1500;
            stroke-dashoffset: 1500;
            /* 3.6 second duration spanning the exact path length */
            animation: drawLine 3.6s ease-in-out forwards;
            transition: stroke 0.4s ease;
        }

        @keyframes drawLine {
            to { stroke-dashoffset: 0; }
        }

        .chart-arrow {
            fill: var(--chart-arrow);
            opacity: 0;
            transform: scale(0);
            /* Tip of the arrow */
            transform-origin: 1165px 145px;
            /* Starts at 3.3s so it blooms exactly as the line tip slides underneath it */
            animation: popArrow 0.4s cubic-bezier(0.16, 1, 0.3, 1) 3.3s forwards;
            transition: fill 0.4s ease;
        }

        @keyframes popArrow {
            to { opacity: 1; transform: scale(1); }
        }

        /* ── Foreground Content ── */
        .brand-section, .login-section {
            position: relative;
            z-index: 10;
        }

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
        }

        .brand-logo {
            width: 360px; 
            height: 360px; 
            margin-bottom: 16px;
            /* Stacked shadows: 
               1st is a tight, dark shadow so the thin text pops over the line.
               2nd is a wide, soft shadow for the large shield. */
            filter: drop-shadow(0px 2px 3px rgba(10, 25, 47, 0.5)) 
                    drop-shadow(0px 12px 24px rgba(10, 25, 47, 0.15));
            transition: filter 0.3s ease;
        }

        [data-theme="dark"] .brand-logo {
            filter: drop-shadow(0px 2px 3px rgba(0, 0, 0, 0.9)) 
                    drop-shadow(0px 12px 24px rgba(0, 0, 0, 0.6));
        }

        .login-section {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
        }

        .login-card {
            background: var(--card-bg);
            padding: 50px 40px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
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

        .form-group { margin-bottom: 20px; }

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

        .btn-login:hover { background-color: var(--navy-hover); }

        .error-msg {
            background-color: var(--error-bg);
            color: var(--error-text);
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border-left: 4px solid var(--error-border);
        }

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

        @media (max-width: 768px) {
            body { flex-direction: column; }
            .academic-bg { display: none; }
            .brand-section { padding: 40px 20px 20px; }
            .login-section { padding: 20px; align-items: flex-start; }
            .login-card { padding: 30px 20px; box-shadow: none; background: transparent; border: none; }
        }
    </style>
</head>
<body>

    <!-- Formal Academic Background Animation -->
    <div class="academic-bg">
        <svg viewBox="0 0 1440 800" preserveAspectRatio="xMidYMid slice">
            <!-- Staggered Bar Chart -->
            <rect class="chart-bar bar-1" x="100" y="600" width="120" height="200" />
            <rect class="chart-bar bar-2" x="300" y="450" width="120" height="350" />
            <rect class="chart-bar bar-3" x="500" y="500" width="120" height="300" />
            <rect class="chart-bar bar-4" x="700" y="350" width="120" height="450" />
            <rect class="chart-bar bar-5" x="900" y="400" width="120" height="400" />
            <rect class="chart-bar bar-6" x="1100" y="200" width="120" height="600" />
            
            <!-- Ascending Line, stops short at 1150, 160 so the rounded cap hides inside the arrowhead -->
            <path class="chart-line" d="M -50 700 L 160 550 L 360 400 L 560 480 L 760 300 L 960 350 L 1150 160" />
            
            <!-- Perfect 45-degree arrow pointing up and right -->
            <polygon class="chart-arrow" points="1125,155 1165,145 1155,185" />
        </svg>
    </div>

    <!-- Left Branding Side -->
    <div class="brand-section">
        <img src="assets/img/logo_sidebar.png" alt="UDM-RADAR Logo" class="brand-logo">        <h3 class="brand-tagline">Risk Analytics &amp; Decision-support for Academic Records</h3>
        <!-- Fixed typo: Computer -> Computing -->
        <p>A specialized portal for the College of Computing Studies to monitor academic trajectories and support student success.</p>
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