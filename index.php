<?php
/**
 * Login Page — Smart Microgrid Platform
 */

require_once __DIR__ . '/includes/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $user = authenticateUser($username, $password);
            if ($user) {
                header('Location: ' . BASE_URL . 'dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 50%, rgba(16, 185, 129, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 70% 30%, rgba(59, 130, 246, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 50% 80%, rgba(245, 158, 11, 0.05) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(2%, -1%) rotate(1deg); }
            50% { transform: translate(-1%, 2%) rotate(-1deg); }
            75% { transform: translate(-2%, -1%) rotate(0.5deg); }
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 2rem;
            position: relative;
            z-index: 2;
        }
        .login-card {
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }
        .brand-icon {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            background: linear-gradient(135deg, #10b981, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #fff;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        .brand-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #f1f5f9;
            text-align: center;
            margin-bottom: 0.3rem;
        }
        .brand-subtitle {
            color: #94a3b8;
            text-align: center;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
        .form-control {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255,255,255,0.1);
            color: #e2e8f0;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.95rem;
        }
        .form-control:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
            color: #f1f5f9;
        }
        .form-label { color: #cbd5e1; font-weight: 500; font-size: 0.9rem; }
        .input-group-text {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255,255,255,0.1);
            color: #64748b;
            border-radius: 12px 0 0 12px;
        }
        .btn-login {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            padding: 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            color: #fff;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
            color: #fff;
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            border-radius: 12px;
        }
        .demo-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.82rem;
            color: #93c5fd;
        }
        .demo-info strong { color: #bfdbfe; }
        .demo-info code { color: #a5f3fc; background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px; font-size: 0.82rem; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="brand-icon">
                <i class="bi bi-lightning-charge-fill"></i>
            </div>
            <div class="brand-title"><?= APP_NAME ?></div>
            <div class="brand-subtitle">Smart Hybrid Microgrid Platform</div>

            <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Enter username"
                               value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Enter password" required>
                        <button type="button" class="input-group-text" onclick="(function(){var i=document.getElementById('passwordInput'),ic=this.querySelector('i');if(i.type==='password'){i.type='text';ic.className='bi bi-eye-slash';}else{i.type='password';ic.className='bi bi-eye';}}).call(this)" style="cursor:pointer; background:rgba(15,23,42,0.6); border:1px solid rgba(255,255,255,0.1); color:#94a3b8;" title="Show/hide password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>

            <div class="demo-info">
                <strong><i class="bi bi-info-circle"></i> Demo Credentials</strong><br>
                Admin: <code>admin</code> / <code>admin123</code><br>
                User: <code>sharma</code> / <code>user123</code>
            </div>
        </div>
    </div>
</body>
</html>
