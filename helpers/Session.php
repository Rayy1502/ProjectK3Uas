<?php
declare(strict_types=1);

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Strict');
            session_start();
        }
    }

    public static function generateCsrf(): string
    {
        if (!empty($_SESSION['csrf_token'])) {
            return $_SESSION['csrf_token'];
        }
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public static function verifyCsrf(string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        unset($_SESSION['csrf_token']);
        return true;
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::generateCsrf()) . '">';
    }

    public static function isLoggedIn(): bool { return isset($_SESSION['user_id']); }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) { header('Location: login.php'); exit; }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        if (!in_array($_SESSION['user_role'] ?? '', $roles, true)) {
            http_response_code(403);
            die('403 - Akses Ditolak');
        }
    }

    public static function getUserId(): int { return (int)($_SESSION['user_id'] ?? 0); }
    public static function getUserRole(): string { return $_SESSION['user_role'] ?? ''; }
    public static function getDivisionId(): int { return (int)($_SESSION['division_id'] ?? 0); }

    public static function setFlash(string $type, string $msg): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
    }

    public static function getFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }
}
