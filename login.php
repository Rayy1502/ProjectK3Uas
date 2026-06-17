<?php

declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/helpers/Session.php';

Session::start();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (Session::isLoggedIn()) {
    header('Location: view_create_order.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Session::verifyCsrf($token)) {
        $error = 'Token CSRF tidak valid.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            $error = 'Username dan password wajib diisi.';
        } else {
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
                $stmt->execute(['username' => $username]);
                $user = $stmt->fetch();

                if ($user && md5($password) === $user['password_hash']) {
                    if ((int)$user['is_active'] === 0) {
                        $error = 'Akun Anda tidak aktif.';
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['division_id'] = $user['division_id'];
                        header('Location: view_create_order.php');
                        exit;
                    }
                } else {
                    $error = 'Username atau password salah.';
                }
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Procurement - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bs-body-bg: #0f172a;
            --accent: #6366f1;
        }

        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .glass-card {
            background: rgba(30, 41, 59, .85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(99, 102, 241, .2);
            border-radius: 1rem;
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .btn-accent {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border: none;
            color: #fff;
            transition: all 0.3s ease;
        }

        .btn-accent:hover {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
            transform: translateY(-1px);
        }

        .brand-glow {
            text-shadow: 0 0 20px rgba(99, 102, 241, .5);
        }
    </style>
</head>

<body class="text-light">
    <div class="glass-card">
        <div class="text-center mb-4">
            <i class="bi bi-box-seam-fill fs-1" style="color:var(--accent)"></i>
            <h3 class="fw-bold mt-2 brand-glow">E-Procurement</h3>
            <p class="text-secondary small">Masuk untuk mengelola pengadaan</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?= Session::csrfField() ?>
            <div class="mb-3">
                <label for="username" class="form-label text-secondary">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" id="username" class="form-control bg-dark text-light border-secondary" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label text-secondary">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="password" class="form-control bg-dark text-light border-secondary" required>
                </div>
            </div>
            <button type="submit" class="btn btn-accent w-100 py-2 fw-semibold">
                <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
            </button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>