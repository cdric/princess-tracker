<?php

declare(strict_types=1);

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        if ($needle === '') {
            return true;
        }

        $length = strlen($needle);
        return substr($haystack, -$length) === $needle;
    }
}

const APP_VERSION = '1.0.0-php';

define('PRIVATE_ROOT', dirname(__DIR__));
define('APP_ROOT', __DIR__);

load_env_file();
default_timezone_set_safe();

function default_timezone_set_safe(): void
{
    $tz = getenv('APP_TIMEZONE') ?: 'UTC';
    if (@date_default_timezone_set($tz) === false) {
        date_default_timezone_set('UTC');
    }
}

function load_env_file(): void
{
    $path = getenv('PRINCESS_ENV_PATH') ?: PRIVATE_ROOT . '/.env';
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env_value($key);
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function private_path(string $path): string
{
    if ($path === '') {
        return PRIVATE_ROOT;
    }
    if (str_starts_with($path, '/')) {
        return $path;
    }
    return PRIVATE_ROOT . '/' . $path;
}

function storage_path(string $file = ''): string
{
    $base = private_path(env_value('STORAGE_PATH', 'storage'));
    if (!is_dir($base)) {
        mkdir($base, 0700, true);
    }
    return $file === '' ? $base : $base . '/' . $file;
}

function app_base_url(): string
{
    $base = env_value('APP_BASE_URL', '');
    if ($base !== '') {
        return rtrim($base, '/');
    }
    return '';
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money_value($value, ?string $currency = null): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $prefix = $currency ? $currency . ' ' : '';
    return $prefix . number_format((float)$value, 0);
}

function now_iso(): string
{
    return gmdate('c');
}

function redirect_to(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash_set(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function render_header(string $title): void
{
    $flash = flash_get();
    $isLoggedIn = !empty($_SESSION['logged_in']);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<link rel="stylesheet" href="assets/style.css">';
    echo '</head><body><main class="page">';
    echo '<header class="topbar"><div><h1>' . h($title) . '</h1><p class="muted">Princess cruise price tracker</p></div>';
    if ($isLoggedIn) {
        echo '<nav><a href="index.php">Check</a><a href="history.php">History</a><a href="admin.php">Admin</a><a href="headers.php">Headers</a><a href="logout.php">Logout</a></nav>';
    }
    echo '</header>';
    if ($flash) {
        echo '<div class="flash ' . h($flash['type']) . '">' . h($flash['message']) . '</div>';
    }
}

function render_footer(): void
{
    echo '<footer class="footer">Version ' . h(APP_VERSION) . '</footer>';
    echo '</main></body></html>';
}

require_once APP_ROOT . '/db.php';
require_once APP_ROOT . '/auth.php';
require_once APP_ROOT . '/mailer.php';
require_once APP_ROOT . '/princess.php';
require_once APP_ROOT . '/graph.php';
