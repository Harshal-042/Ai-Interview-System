<?php
require_once __DIR__ . '/../includes/auth.php';

initSession();

// If already logged in, redirect straight to dashboard
if (checkAdminAuth()) {
    header('Location: dashboard.php?token=' . urlencode(getActiveAdminToken()));
    exit;
}

$error = '';
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
          || isset($_POST['ajax']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Allow empty password if one-click demo login triggered
    $isDemoLogin = isset($_POST['demo_login']) || ($username === 'admin' && ($password === 'admin123' || empty($password)));

    $authenticated = false;
    $adminId = 1;
    $adminUsername = 'admin';

    if ($isDemoLogin) {
        $authenticated = true;
    } elseif (!empty($username) && !empty($password)) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $authenticated = true;
                $adminId = (int)$admin['id'];
                $adminUsername = $admin['username'];
            } elseif ($username === 'admin' && $password === 'admin123') {
                $authenticated = true;
            }
        } catch (Exception $e) {
            if ($username === 'admin' && $password === 'admin123') {
                $authenticated = true;
            }
        }
    }

    if ($authenticated) {
        $token = getAdminAuthToken($adminId);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $adminId;
        $_SESSION['admin_username'] = $adminUsername;
        $_SESSION['admin_token'] = $token;

        // Set cross-site cookie with SameSite=None and Secure
        @setcookie('admin_token', $token, [
            'expires' => time() + 86400 * 7,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None',
        ]);

        $redirectUrl = 'dashboard.php?token=' . urlencode($token);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'token' => $token,
                'redirect' => $redirectUrl
            ]);
            exit;
        }

        // Output redirect with localStorage sync for iframe resilience
        ?>
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>Signing In...</title></head>
        <body>
            <script>
                try {
                    localStorage.setItem('admin_token', <?= json_encode($token) ?>);
                    sessionStorage.setItem('admin_token', <?= json_encode($token) ?>);
                } catch(e) {}
                window.location.replace(<?= json_encode($redirectUrl) ?>);
            </script>
            <p>Signing in... <a href="<?= htmlspecialchars($redirectUrl) ?>">Click here if you are not redirected automatically.</a></p>
        </body>
        </html>
        <?php
        exit;
    } else {
        $error = 'Invalid username or password. Use demo credentials (admin / admin123).';
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - AI Interview System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="background-color: #f8fafc;">
<div class="container container-narrow" style="margin-top: clamp(24px, 6vh, 60px);">
    <div style="text-align: center; margin-bottom: 24px;">
        <a href="../index.php" style="font-size: 0.9rem; color: var(--text-muted);">&larr; Back to Home</a>
        <h1 style="margin-top: 8px; font-size: 1.8rem;">AI Interview System</h1>
        <p style="color: var(--text-muted);">Administrator Sign In</p>
    </div>

    <div class="card" style="box-shadow: var(--shadow-md);">
        <div id="alertBox" class="alert alert-danger" style="<?= $error ? '' : 'display: none;' ?>">
            <?= htmlspecialchars($error) ?>
        </div>

        <form method="POST" action="login.php" id="loginForm">
            <div class="form-group">
                <label class="form-label" for="username">Username or Email</label>
                <input type="text" id="username" name="username" class="form-control" value="admin" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" value="admin123" required>
            </div>

            <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 10px;">
                <button type="submit" id="loginBtn" class="btn btn-primary" style="width: 100%; font-size: 1rem; padding: 12px;">
                    Sign In as Admin &rarr;
                </button>

                <button type="button" id="demoLoginBtn" class="btn btn-outline" style="width: 100%; font-size: 0.9rem;">
                    Instant 1-Click Demo Login
                </button>
            </div>
        </form>

        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color); font-size: 0.85rem; color: var(--text-muted); text-align: center;">
            Default credentials: <strong>admin</strong> / <strong>admin123</strong>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // If we already have a saved admin token in localStorage, test it
    try {
        const existingToken = localStorage.getItem('admin_token');
        if (existingToken) {
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('logged_out')) {
                window.location.replace('dashboard.php?token=' + encodeURIComponent(existingToken));
                return;
            }
        }
    } catch(e) {}

    const form = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const demoBtn = document.getElementById('demoLoginBtn');
    const alertBox = document.getElementById('alertBox');

    async function performLogin(username, password, isDemo = false) {
        loginBtn.disabled = true;
        loginBtn.textContent = 'Signing in...';
        if (demoBtn) demoBtn.disabled = true;
        alertBox.style.display = 'none';

        try {
            const formData = new FormData();
            formData.append('username', username);
            formData.append('password', password);
            formData.append('ajax', '1');
            if (isDemo) formData.append('demo_login', '1');

            const res = await fetch('login.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const data = await res.json();
            if (data.success && data.token) {
                try {
                    localStorage.setItem('admin_token', data.token);
                    sessionStorage.setItem('admin_token', data.token);
                } catch(e) {}
                window.location.replace(data.redirect || ('dashboard.php?token=' + encodeURIComponent(data.token)));
            } else {
                throw new Error(data.error || 'Authentication failed');
            }
        } catch (err) {
            console.warn('AJAX login fallback:', err);
            // If fetch fails or has an error, submit form natively
            if (err.message && !err.message.includes('fetch')) {
                alertBox.textContent = err.message;
                alertBox.style.display = 'block';
                loginBtn.disabled = false;
                loginBtn.textContent = 'Sign In as Admin \u2192';
                if (demoBtn) demoBtn.disabled = false;
            } else {
                form.submit();
            }
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const u = document.getElementById('username').value.trim();
        const p = document.getElementById('password').value;
        performLogin(u, p, false);
    });

    demoBtn.addEventListener('click', () => {
        performLogin('admin', 'admin123', true);
    });
});
</script>
</body>
</html>
