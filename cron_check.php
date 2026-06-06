<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
init_db();

if (in_array('--init-db', $argv, true)) {
    echo "Database initialized.\n";
    exit(0);
}

$currencyCode = env_value('DEFAULT_CURRENCY_CODE', 'USD');
$guestCountry = env_value('DEFAULT_GUEST_COUNTRY', 'US');
$guestHomeCity = env_value('DEFAULT_GUEST_HOME_CITY', 'LAX');
$guestCount = env_value('DEFAULT_GUEST_COUNT', '2');
$includeMisc = env_value('DEFAULT_INCLUDE_MISC', '1');

$cruiseIds = active_watch_cruise_ids();
if (!$cruiseIds) {
    $defaultCruise = env_value('DEFAULT_CRUISE_ID', 'H630');
    $cruiseIds = $defaultCruise ? [$defaultCruise] : [];
}

foreach ($cruiseIds as $cruiseId) {
    echo '[' . date('c') . '] Checking ' . $cruiseId . "\n";
    try {
        $rows = run_princess_check([
            'cruise_id' => $cruiseId,
            'currency_code' => $currencyCode,
            'guest_country' => $guestCountry,
            'guest_home_city' => $guestHomeCity,
            'guest_count' => $guestCount,
            'include_misc' => $includeMisc,
        ], 'cron');
        foreach ($rows as $row) {
            echo sprintf(
                "%s %s %s %s fare_pp=%s total_pp=%s\n",
                $row['cruise_id'],
                $row['cabin_name'],
                $row['status'],
                $row['currency'] ?? '',
                $row['fare_per_person'] ?? '',
                $row['total_per_person'] ?? ''
            );
        }
    } catch (Throwable $e) {
        fwrite(STDERR, '[' . date('c') . '] ERROR ' . $cruiseId . ': ' . $e->getMessage() . "\n");
    }
}
