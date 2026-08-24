<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';

requireRole('admin');
$user = currentUser();
$db = getDB();

$error = '';
$success = '';

// Password Change Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        $success = 'Password updated successfully.';
    }
}

$pageTitle = 'Settings';
$navItems = [
    ['Dashboard',          'index.php',     '🏠'],
    ['Students',           'students.php',  '👥'],
    ['Faculty',            'faculty.php',   '👨‍🏫'],
    ['Grades',             'grades.php',    '📝'],
    ['Program Analytics',  'analytics.php', '📊'],
    ['Activity & Inbox',   'activity.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

// Load Active Model Metrics
$metricsPath = '../python_ml/model_metrics.json';
$activeMetrics = ['mae' => '--', 'rmse' => '--', 'r2' => '--'];
if (file_exists($metricsPath)) {
    $fileData = json_decode(file_get_contents($metricsPath), true);
    if ($fileData) {
        $activeMetrics = [
            'mae' => number_format($fileData['mae'], 4),
            'rmse' => number_format($fileData['rmse'], 4),
            'r2' => number_format($fileData['r2'], 4)
        ];
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
.governance-metric { font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 2px; }
.governance-label { font-size: 0.75rem; color: var(--text-gray); text-transform: uppercase; font-weight: 600; }
.metric-box { padding: 10px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 6px; text-align: center; }
.better-metric { color: var(--risk-low) !important; }
.worse-metric { color: var(--risk-high) !important; }
</style>

<div class="main-content">
    <div class="header" style="margin-bottom: 24px;">
        <div>
            <h1>Settings & System Governance</h1>
            <p style="color: var(--text-gray);">Manage your account security and oversee the core machine learning predictive model.</p>
        </div>
    </div>

    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); align-items: start; gap: 24px;">

        <!-- 1. Change Password Card -->
        <div class="card" style="margin-bottom: 0;">
            <div class="table-title">Change Password</div>
            <?php if ($error): ?>
                <p style="background:rgba(220,38,38,0.1); color:var(--risk-high); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high); font-weight: 600;"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p style="background:rgba(5,150,105,0.1); color:var(--risk-low); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low); font-weight: 600;"><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>
            <form method="POST" action="settings.php">
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px; color:var(--text-dark);">Current Password</label>
                    <input type="password" name="current_password" required style="width:100%; padding:12px 14px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-color); color:var(--text-dark); font-family: inherit;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px; color:var(--text-dark);">New Password</label>
                    <input type="password" name="new_password" required minlength="8" style="width:100%; padding:12px 14px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-color); color:var(--text-dark); font-family: inherit;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px; color:var(--text-dark);">Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="8" style="width:100%; padding:12px 14px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-color); color:var(--text-dark); font-family: inherit;">
                </div>
                <button type="submit" style="width:100%; padding:14px; background:var(--sidebar-bg); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-family: inherit;">Update Password</button>
            </form>
        </div>

        <!-- 2. AI Model Governance Card -->
        <div class="card" style="margin-bottom: 0;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <div class="table-title" style="margin-bottom: 0;">AI Model Governance</div>
                <span class="badge" style="background: rgba(5, 150, 105, 0.1); color: var(--risk-low); border: 1px solid rgba(5, 150, 105, 0.3);">Active Model Online</span>
            </div>
            
            <p style="font-size: 0.85rem; color: var(--text-gray); margin-bottom: 20px; line-height: 1.5;">
                Safely upload historical graduate data to train, evaluate, and promote a new candidate predictive model. This process will not overwrite the active model until explicitly authorized.
            </p>

            <!-- Active Model Status -->
            <div style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; padding: 16px; margin-bottom: 24px;">
                <h4 style="margin: 0 0 12px 0; color: var(--text-dark); font-size: 0.9rem; text-transform: uppercase;">Active Model Performance</h4>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                    <div class="metric-box">
                        <div class="governance-metric" id="active-mae"><?= $activeMetrics['mae'] ?></div>
                        <div class="governance-label">MAE</div>
                    </div>
                    <div class="metric-box">
                        <div class="governance-metric" id="active-rmse"><?= $activeMetrics['rmse'] ?></div>
                        <div class="governance-label">RMSE</div>
                    </div>
                    <div class="metric-box">
                        <div class="governance-metric" id="active-r2"><?= $activeMetrics['r2'] ?></div>
                        <div class="governance-label">R² Score</div>
                    </div>
                </div>
            </div>

            <!-- Upload & Train Form -->
            <form id="train-candidate-form" onsubmit="handleCandidateTraining(event)">
                <div style="margin-bottom: 16px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:8px; color:var(--text-dark);">Upload Historical Dataset (CSV)</label>
                    <input type="file" id="dataset-upload" accept=".csv" required style="width: 100%; padding: 10px; border: 2px dashed var(--border-color); border-radius: 8px; background: var(--bg-color); color: var(--text-dark); font-family: inherit; cursor: pointer;">
                    <p style="font-size: 0.75rem; color: var(--text-gray); margin-top: 6px;">Must contain official prior-term subject grades and the actual final outcome target.</p>
                </div>
                
                <button type="submit" id="btn-train" style="width:100%; padding:14px; background:var(--accent-blue); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; display:flex; justify-content:center; align-items:center; gap:8px; font-family: inherit; transition: opacity 0.2s;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    Train Candidate Model
                </button>
            </form>

            <!-- Candidate Evaluation Panel (Hidden initially) -->
            <div id="candidate-panel" style="display: none; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h4 style="margin: 0; color: var(--risk-mod); font-size: 0.95rem; text-transform: uppercase;">Candidate Model Ready</h4>
                    <span style="font-size: 0.8rem; color: var(--text-gray); font-weight: 600;" id="candidate-timestamp">Just now</span>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px;">
                    <div class="metric-box">
                        <div class="governance-metric" id="candidate-mae">--</div>
                        <div class="governance-label">MAE</div>
                    </div>
                    <div class="metric-box">
                        <div class="governance-metric" id="candidate-rmse">--</div>
                        <div class="governance-label">RMSE</div>
                    </div>
                    <div class="metric-box">
                        <div class="governance-metric" id="candidate-r2">--</div>
                        <div class="governance-label">R² Score</div>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 16px;">
                    <button type="button" onclick="cancelCandidate()" style="flex: 1; padding: 10px; background: var(--bg-color); color: var(--risk-high); border: 1px solid rgba(220, 38, 38, 0.3); border-radius: 6px; font-weight: 600; cursor: pointer; font-family: inherit;">Discard</button>
                    <button type="button" onclick="promoteCandidate()" style="flex: 2; padding: 10px; background: var(--risk-mod); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-family: inherit;">Promote to Active</button>
                </div>
            </div>
            
            <div id="training-status" style="display: none; margin-top: 16px; padding: 12px; background: rgba(30, 77, 183, 0.1); color: var(--accent-blue); border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-align: center;">
                Processing dataset and training model... Please wait.
            </div>

        </div>
    </div>
</div>

<script>
// --- Model Governance API Integrations ---

function handleCandidateTraining(event) {
    event.preventDefault();
    
    const fileInput = document.getElementById('dataset-upload');
    if (fileInput.files.length === 0) {
        alert("Please select a CSV file first.");
        return;
    }

    // UI State: Loading
    const btn = document.getElementById('btn-train');
    const statusBox = document.getElementById('training-status');
    const candidatePanel = document.getElementById('candidate-panel');
    
    btn.disabled = true;
    btn.style.opacity = '0.5';
    statusBox.style.display = 'block';
    candidatePanel.style.display = 'none';

    // Pack the file
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    // Send it to the Python Flask Server
    fetch('http://127.0.0.1:5000/api/train_candidate', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        statusBox.style.display = 'none';
        btn.disabled = false;
        btn.style.opacity = '1';

        if (data.error) {
            alert("Training Rejected: " + data.error);
            return;
        }

        // Successfully trained! Populate UI with real metrics
        document.getElementById('candidate-mae').innerText = data.metrics.mae.toFixed(4);
        document.getElementById('candidate-rmse').innerText = data.metrics.rmse.toFixed(4);
        document.getElementById('candidate-r2').innerText = data.metrics.r2.toFixed(4);
        
        // Reveal Staging Panel
        candidatePanel.style.display = 'block';
    })
    .catch(err => {
        statusBox.style.display = 'none';
        btn.disabled = false;
        btn.style.opacity = '1';
        alert("Connection Error. Is the Python ML server running on port 5000?");
        console.error(err);
    });
}

function cancelCandidate() {
    if(confirm("Are you sure you want to discard this candidate model?")) {
        fetch('http://127.0.0.1:5000/api/cancel_candidate', { method: 'POST' })
        .then(() => {
            document.getElementById('candidate-panel').style.display = 'none';
            document.getElementById('dataset-upload').value = "";
        });
    }
}

function promoteCandidate() {
    if(confirm("WARNING: Promoting this candidate will securely back up and replace the active predictive model. Are you sure you want to proceed?")) {
        fetch('http://127.0.0.1:5000/api/promote_candidate', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            if(data.error) {
                alert("Promotion Failed: " + data.error);
                return;
            }
            alert("Success! The candidate model is now LIVE and processing predictions.");
            document.getElementById('candidate-panel').style.display = 'none';
            document.getElementById('dataset-upload').value = "";
            
            // visually copy metrics to the active slot
            document.getElementById('active-mae').innerText = document.getElementById('candidate-mae').innerText;
            document.getElementById('active-rmse').innerText = document.getElementById('candidate-rmse').innerText;
            document.getElementById('active-r2').innerText = document.getElementById('candidate-r2').innerText;
        })
        .catch(err => alert("Error communicating with Python server."));
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>