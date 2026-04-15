<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in()
{
    return isset($_SESSION['user']);
}

function current_user()
{
    return $_SESSION['user'] ?? null;
}

function require_login()
{
    if (!is_logged_in()) {
        header('Location: ' . app_url('login.php'));
        exit;
    }
}

function require_role($role)
{
    require_login();

    $user = current_user();
    if (($user['role'] ?? '') !== $role) {
        header('Location: ' . app_url('unauthorized.php'));
        exit;
    }
}

function login_user($user)
{
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'role' => $user['role'],
    ];
}

function logout_user()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
