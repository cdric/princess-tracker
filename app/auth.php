<?php

declare(strict_types=1);

function session_start_safe(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $name = env_value('APP_SESSION_NAME', 'princess_tracker');
    session_name($name);
    session_start();
}

function is_logged_in(): bool
{
    return !empty($_SESSION['logged_in']);
}

function auth_accounts(): array
{
    $accounts = [];

    $adminUsername = env_value('APP_USERNAME', 'admin');
    if ($adminUsername !== null && $adminUsername !== '') {
        $accounts[] = [
            'username' => $adminUsername,
            'password_hash' => env_value('APP_PASSWORD_HASH'),
            'password' => env_value('APP_PASSWORD', 'change-me'),
            'role' => 'admin',
        ];
    }

    $userUsername = env_value('APP_USER_USERNAME', 'user');
    $userPasswordHash = env_value('APP_USER_PASSWORD_HASH');
    $userPassword = env_value('APP_USER_PASSWORD', 'change-me-user');
    if ($userUsername !== '') {
        $accounts[] = [
            'username' => $userUsername,
            'password_hash' => $userPasswordHash,
            'password' => $userPassword,
            'role' => 'user',
        ];
    }

    return $accounts;
}

function authenticate_user(string $username, string $password): ?array
{
    foreach (auth_accounts() as $account) {
        if (!hash_equals($account['username'], $username)) {
            continue;
        }

        $expectedHash = $account['password_hash'];
        if ($expectedHash) {
            if (password_verify($password, $expectedHash)) {
                return $account;
            }
            continue;
        }

        if (hash_equals($account['password'], $password)) {
            return $account;
        }
    }

    return null;
}

function require_login(): void
{
    session_start_safe();
    if (!is_logged_in()) {
        redirect_to('login.php');
    }
}

function attempt_login(string $username, string $password): bool
{
    return authenticate_user($username, $password) !== null;
}

function current_user_role(): string
{
    return (string)($_SESSION['role'] ?? '');
}

function current_username(): string
{
    return (string)($_SESSION['username'] ?? '');
}

function is_admin(): bool
{
    return current_user_role() === 'admin';
}

function require_admin(): void
{
    require_login();
    if (is_admin()) {
        return;
    }

    http_response_code(403);
    render_header('Access denied');
    echo '<section class="card"><div class="flash error">You do not have access to this page.</div></section>';
    render_footer();
    exit;
}

function login_user(array $account): void
{
    session_regenerate_id(true);
    $_SESSION['logged_in'] = true;
    $_SESSION['login_at'] = time();
    $_SESSION['username'] = $account['username'];
    $_SESSION['role'] = $account['role'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
