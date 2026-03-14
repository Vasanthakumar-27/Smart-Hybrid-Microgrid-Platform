<?php
/**
 * Login Page — Smart Microgrid Platform
 * Super Sliding Animation Login Page
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            position: relative;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(circle at 30% 50%, rgba(16, 185, 129, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 70% 30%, rgba(59, 130, 246, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 50% 80%, rgba(245, 158, 11, 0.08) 0%, transparent 50%);
            animation: float 25s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(2%, -1%) scale(1.02); }
            50% { transform: translate(-1%, 2%) scale(1); }
            75% { transform: translate(-2%, -1%) scale(1.01); }
        }

        /* Main Container */
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            position: relative;
            z-index: 1;
        }

        /* LEFT SIDE - FEATURES */
        .features-section {
            flex: 0.8;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(59, 130, 246, 0.08));
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            animation: slideInLeft 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow-y: auto;
            max-height: 100vh;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-60px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .feature-header {
            margin-bottom: 2rem;
        }

        .feature-header-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #10b981, #34d399);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            margin-bottom: 0.8rem;
            animation: bounceIn 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) 0.2s backwards;
        }

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                transform: scale(1);
            }
        }

        .feature-header h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #f1f5f9;
            margin: 0;
            line-height: 1.2;
            animation: slideInUp 0.8s ease-out 0.1s backwards;
        }

        .feature-header p {
            color: #cbd5e1;
            font-size: 0.85rem;
            margin-top: 0.3rem;
            animation: slideInUp 0.8s ease-out 0.15s backwards;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .feature-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            animation: slideInUp 0.8s ease-out backwards;
            position: relative;
            overflow: hidden;
        }

        .feature-card:nth-child(1) { animation-delay: 0.2s; }
        .feature-card:nth-child(2) { animation-delay: 0.3s; }
        .feature-card:nth-child(3) { animation-delay: 0.4s; }
        .feature-card:nth-child(4) { animation-delay: 0.5s; }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #10b981, #3b82f6, #f59e0b);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(59, 130, 246, 0.12));
            border-color: rgba(16, 185, 129, 0.3);
            transform: translateY(-4px) translateX(8px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.15);
        }

        .feature-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            background: rgba(16, 185, 129, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #10b981;
            margin-bottom: 0.75rem;
            transition: all 0.4s ease;
        }

        .feature-card:hover .feature-icon {
            background: rgba(16, 185, 129, 0.3);
            transform: scale(1.1) rotate(5deg);
            color: #34d399;
        }

        .feature-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 0.4rem;
        }

        .feature-desc {
            font-size: 0.8rem;
            color: #94a3b8;
            line-height: 1.5;
            margin: 0;
        }

        /* RIGHT SIDE - LOGIN FORM */
        .login-section {
            flex: 1.2;
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            animation: slideInRight 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            min-height: 100vh;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(60px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .login-form-wrapper {
            width: 100%;
            max-width: 380px;
        }

        .login-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.7));
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            padding: 2rem;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
        }

        .brand-icon {
            width: 65px;
            height: 65px;
            border-radius: 12px;
            background: linear-gradient(135deg, #10b981, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            margin: 0 auto 1rem;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
            animation: popIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes popIn {
            0% {
                opacity: 0;
                transform: scale(0);
                rotate: -180deg;
            }
            100% {
                opacity: 1;
                transform: scale(1);
                rotate: 0deg;
            }
        }

        .brand-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #f1f5f9;
            text-align: center;
            margin-bottom: 0.2rem;
            animation: slideInUp 0.8s ease-out 0.15s backwards;
        }

        .brand-subtitle {
            color: #94a3b8;
            text-align: center;
            font-size: 0.8rem;
            margin-bottom: 1.5rem;
            animation: slideInUp 0.8s ease-out 0.2s backwards;
        }

        .form-group {
            margin-bottom: 1rem;
            animation: slideInUp 0.8s ease-out backwards;
        }

        .form-group:nth-of-type(1) { animation-delay: 0.25s; }
        .form-group:nth-of-type(2) { animation-delay: 0.3s; }

        .form-label {
            color: #cbd5e1;
            font-weight: 600;
            font-size: 0.8rem;
            margin-bottom: 0.4rem;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .input-group {
            position: relative;
        }

        .input-group-text {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #64748b;
            border-radius: 12px 0 0 12px;
            transition: all 0.3s ease;
        }

        .input-group:focus-within .input-group-text {
            background: rgba(15, 23, 42, 0.8);
            border-color: #10b981;
            color: #10b981;
        }

        .form-control {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
            padding: 0.75rem 1rem;
            border-radius: 0 12px 12px 0;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control::placeholder {
            color: #475569;
        }

        .form-control:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
            color: #f1f5f9;
        }

        .form-control:not(:placeholder-shown) {
            border-color: rgba(16, 185, 129, 0.4);
            background: rgba(15, 23, 42, 0.7);
        }

        .password-toggle {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-left: none;
            color: #64748b;
            border-radius: 0 12px 12px 0;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0 1rem;
        }

        .input-group:focus-within .password-toggle {
            background: rgba(15, 23, 42, 0.8);
            border-color: #10b981;
            color: #10b981;
        }

        .btn-login {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            color: white;
            width: 100%;
            margin-top: 0.8rem;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            animation: slideInUp 0.8s ease-out 0.35s backwards;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(16, 185, 129, 0.35);
        }

        .btn-login:active::before {
            width: 300px;
            height: 300px;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        .demo-info {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(6, 182, 212, 0.05));
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.75rem;
            color: #93c5fd;
            animation: slideInUp 0.8s ease-out 0.4s backwards;
        }

        .demo-info strong {
            color: #bfdbfe;
            display: block;
            margin-bottom: 0.3rem;
            font-size: 0.8rem;
        }

        .demo-info code {
            color: #a5f3fc;
            background: rgba(0, 0, 0, 0.3);
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 0.7rem;
            margin: 0 2px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .features-section {
                flex: 0.7;
                padding: 1.5rem 1.2rem;
            }

            .feature-header h1 {
                font-size: 1.8rem;
            }

            .feature-header-icon {
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }

            .login-section {
                flex: 1.3;
                padding: 1.5rem 1.2rem;
            }

            .login-card {
                padding: 1.5rem;
            }

            .brand-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }

        @media (max-width: 992px) {
            .login-wrapper {
                flex-direction: column;
            }

            .features-section {
                flex: 1;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
                padding: 2rem 1.5rem;
                max-height: none;
            }

            .feature-header h1 {
                font-size: 1.8rem;
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .login-section {
                flex: 1;
                min-height: auto;
                padding: 2rem 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .features-section {
                padding: 1.5rem 1rem;
            }

            .feature-header h1 {
                font-size: 1.5rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
                gap: 0.8rem;
            }

            .feature-card {
                padding: 0.8rem;
            }

            .login-section {
                padding: 1.5rem 1rem;
            }

            .login-card {
                padding: 1.5rem;
            }

            .brand-icon {
                width: 55px;
                height: 55px;
                font-size: 1.5rem;
                margin-bottom: 0.8rem;
            }

            .brand-title {
                font-size: 1.3rem;
            }

            .brand-subtitle {
                font-size: 0.75rem;
                margin-bottom: 1.2rem;
            }

            .login-form-wrapper {
                max-width: 100%;
            }

            .demo-info {
                padding: 0.8rem;
                margin-top: 0.8rem;
                font-size: 0.7rem;
            }
        }

        @media (max-width: 480px) {
            .feature-header h1 {
                font-size: 1.3rem;
            }

            .feature-title {
                font-size: 0.85rem;
            }

            .feature-desc {
                font-size: 0.75rem;
            }

            .brand-title {
                font-size: 1.2rem;
            }

            .btn-login {
                padding: 0.7rem 1.2rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- LEFT SIDE - FEATURES -->
        <div class="features-section">
            <div class="feature-header">
                <div class="feature-header-icon">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>
                <h1><?= APP_NAME ?></h1>
                <p>Smart Hybrid Microgrid Platform</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-speedometer2"></i></div>
                    <div class="feature-title">Real-time Monitoring</div>
                    <p class="feature-desc">Monitor solar and wind generation with live dashboards and instant alerts.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-battery-charging"></i></div>
                    <div class="feature-title">Battery Management</div>
                    <p class="feature-desc">Optimize energy storage with intelligent battery status tracking and forecasting.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-graph-up"></i></div>
                    <div class="feature-title">Advanced Analytics</div>
                    <p class="feature-desc">Analyze generation trends, consumption patterns, and performance metrics.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-piggy-bank"></i></div>
                    <div class="feature-title">Savings Tracking</div>
                    <p class="feature-desc">Track financial benefits and calculate savings from renewable energy generation.</p>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDE - LOGIN FORM -->
        <div class="login-section">
            <div class="login-form-wrapper">
                <div class="login-card">
                    <div class="brand-icon">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                    <div class="brand-title">Welcome Back</div>
                    <div class="brand-subtitle">Access your microgrid dashboard</div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?= h($error) ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="Enter your username"
                                       value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Enter your password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword()" title="Show/hide password">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-login">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                    </form>

                    <div class="demo-info">
                        <strong><i class="bi bi-info-circle"></i> Demo Credentials</strong>
                        Admin: <code>admin</code> / <code>admin123</code><br>
                        User: <code>sharma</code> / <code>user123</code>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const icon = document.getElementById('toggleIcon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>
