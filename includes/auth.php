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

function app_roles()
{
    return ['CASHIER', 'INVENTORY', 'PURCHASING', 'ACCOUNTING'];
}

function normalize_role($role)
{
    return strtoupper(trim((string)$role));
}

function is_valid_role($role)
{
    return in_array(normalize_role($role), app_roles(), true);
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
    $currentRole = normalize_role($user['role'] ?? '');
    $allowedRoles = is_array($role) ? array_values($role) : [$role];
    $allowedRoles = array_map('normalize_role', $allowedRoles);

    if (!in_array($currentRole, $allowedRoles, true)) {
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
        'role' => normalize_role($user['role'] ?? ''),
    ];
}

function ensure_department_accounts(PDO $pdo)
{
    $defaultPasswordHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    $defaults = [
        ['name' => 'Main Cashier', 'username' => 'cashier', 'role' => 'CASHIER'],
        ['name' => 'Inventory Officer', 'username' => 'inventory', 'role' => 'INVENTORY'],
        ['name' => 'Purchasing Officer', 'username' => 'purchasing', 'role' => 'PURCHASING'],
        ['name' => 'Accounting Officer', 'username' => 'accounting', 'role' => 'ACCOUNTING'],
    ];

    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $insertStmt = $pdo->prepare('INSERT INTO users (name, username, password, role) VALUES (:name, :username, :password, :role)');

    foreach ($defaults as $account) {
        $checkStmt->execute(['username' => $account['username']]);
        if ($checkStmt->fetch()) {
            continue;
        }

        $insertStmt->execute([
            'name' => $account['name'],
            'username' => $account['username'],
            'password' => $defaultPasswordHash,
            'role' => $account['role'],
        ]);
    }
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
