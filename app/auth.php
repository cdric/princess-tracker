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

function require_login(): void
{
    session_start_safe();
    if (!is_logged_in()) {
        redirect_to('login.php');
    }
}

function attempt_login(string $username, string $password): bool
{
    $expectedUser = env_value('APP_USERNAME', 'admin');
    $expectedHash = env_value('APP_PASSWORD_HASH');
    $expectedPassword = env_value('APP_PASSWORD', 'change-me');

    if (!hash_equals($expectedUser ?? '', $username)) {
        return false;
    }

    if ($expectedHash) {
        return password_verify($password, $expectedHash);
    }

    return hash_equals($expectedPassword ?? '', $password);
}

function login_user(): void
{
    session_regenerate_id(true);
    $_SESSION['logged_in'] = true;
    $_SESSION['login_at'] = time();
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
