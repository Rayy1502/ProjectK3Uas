<?php
declare(strict_types=1);
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
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, username, email, password_hash, role, division_id FROM users WHERE username = :u AND is_active = 1");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && md5($password) === $user['password_hash']) {
            session_regenerate_id(true);
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['user_role']   = $user['role'];
            $_SESSION['division_id'] = $user['division_id'];
            header('Location: view_create_order.php');
            exit;
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Procurement System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', system-ui, sans-serif; }
        .login-card { background: rgba(30,41,59,.9); backdrop-filter: blur(16px); border: 1px solid rgba(99,102,241,.25); border-radius: 1.25rem; max-width: 420px; width: 100%; }
        .login-card .form-control { background: rgba(15,23,42,.8); border-color: rgba(99,102,241,.3); color: #e2e8f0; }
        .login-card .form-control:focus { border-color: #6366f1; box-shadow: 0 0 0 .2rem rgba(99,102,241,.25); }
        .btn-login { background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; }
        .btn-login:hover { background: linear-gradient(135deg, #4f46e5, #7c3aed); transform: translateY(-1px); }
        .glow-icon { font-size: 3rem; color: #6366f1; text-shadow: 0 0 30px rgba(99,102,241,.6); }
    </style>
</head>
<body>
    <div class="login-card p-5 shadow-lg">
        <div class="text-center mb-4">
            <i class="bi bi-box-seam-fill glow-icon"></i>
            <h4 class="text-light mt-3 fw-bold">E-Procurement</h4>
            <p class="text-secondary small">Sistem Manajemen Pengadaan Barang & Jasa</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label text-secondary small">username</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-envelope"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label text-secondary small">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" class="btn btn-login text-white w-100 py-2 fw-semibold">
                <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
            </button>
        </form>
    </div>
</body>
</html>
