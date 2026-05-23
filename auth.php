<?php
declare(strict_types=1);

$config = [
    'admin_user' => getenv('ADMIN_USER') !== false && getenv('ADMIN_USER') !== '' ? getenv('ADMIN_USER') : 'admin',
    'admin_pass' => getenv('ADMIN_PASS') !== false && getenv('ADMIN_PASS') !== '' ? getenv('ADMIN_PASS') : 'password',
    'session_name' => 'image_exif_viewer',
];

if (session_status() === PHP_SESSION_NONE) {
    session_name($config['session_name']);
    session_start();
}

function isAdmin(): bool
{
    return !empty($_SESSION['is_admin']);
}

function requireAdmin(): void
{
    if (!isAdmin()) {
        header('Location: login.php');
        exit;
    }
}

function loginAdmin(string $username, string $password): bool
{
    global $config;
    if ($username === $config['admin_user'] && $password === $config['admin_pass']) {
        $_SESSION['is_admin'] = true;
        return true;
    }
    return false;
}

function logoutAdmin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
