<?php

declare(strict_types=1);

$bootstrapCandidates = [];
$envBootstrap = getenv('PRINCESS_BOOTSTRAP');
if ($envBootstrap) {
    $bootstrapCandidates[] = $envBootstrap;
}
$bootstrapCandidates[] = __DIR__ . '/../app/bootstrap.php';
$bootstrapCandidates[] = __DIR__ . '/../../apps/princess-tracker-private/app/bootstrap.php';
$bootstrapCandidates[] = dirname(__DIR__) . '/app/bootstrap.php';

$loaded = false;
foreach ($bootstrapCandidates as $candidate) {
    if ($candidate && is_file($candidate)) {
        require_once $candidate;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    http_response_code(500);
    echo 'Unable to locate app/bootstrap.php. Set PRINCESS_BOOTSTRAP or deploy app/ to the private path.';
    exit;
}

session_start_safe();
init_db();
