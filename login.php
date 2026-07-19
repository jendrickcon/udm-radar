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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        
        /* Navy Blue Theme */
        body { 
            background-color: #F0F8FF; /* Pale Teal/Blue */
            display: flex; 
            height: 100vh; 
            color: #1a1a1a;
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
        
        .brand-section h1 {
            font-size: 3.5rem;
            color: #0A192F; /* Dark Navy */
            font-weight: 700;
            letter-spacing: -1px;
            margin-bottom: 10px;
        }
        
        .brand-section h2 {
            font-size: 1.2rem;
            color: #1a365d;
            font-weight: 500;
            margin-bottom: 30px;
        }

        .brand-section p {
            color: #555;
            max-width: 400px;
            line-height: 1.6;
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
            background: white;
            padding: 50px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 450px;
        }

        .login-card h3 {
            font-size: 1.8rem;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .login-card p {
            color: #666;
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
            color: #333;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
            background-color: #f9f9f9;
        }

        .form-control:focus {
            border-color: #0A192F;
            background-color: #fff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(10, 25, 47, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background-color: #0A192F; /* Dark Navy */
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 10px;
        }

        .btn-login:hover {
            background-color: #112240;
        }

        .error-msg {
            background-color: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border-left: 4px solid #c62828;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .brand-section { padding: 40px 20px 20px; }
            .brand-section h1 { font-size: 2.5rem; }
            .login-section { padding: 20px; align-items: flex-start; }
            .login-card { padding: 30px 20px; box-shadow: none; background: transparent; }
        }
    </style>
</head>
<body>

    <!-- Left Branding Side -->
    <div class="brand-section">
        <img src="assets/img/logo.png" alt="UDM-RADAR Logo" style="width:110px; height:110px; margin-bottom:16px;">
        <h1>UDM-RADAR</h1>
        <h2>Predictive Analytics System</h2>
        <p style="font-weight:600; color:#0e7490; margin-bottom:14px;">Risk Analytics &amp; Decision-support for Academic Records</p>
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

</body>
</html>