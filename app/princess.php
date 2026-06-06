<?php

declare(strict_types=1);

const PRINCESS_API_URL = 'https://gw.api.princess.com/pcl-web/internal/caps/pc/pricing/v1/cruises';

const CABINS = [
    'S' => 'Suites',
    'M' => 'Mini-Suites',
    'B' => 'Balcony',
    'O' => 'Oceanview',
    'I' => 'Interior',
];

const ALERT_TYPES = [
    'price_drop' => 'Price drop',
    'availability' => 'Back in stock',
];

function normalize_alert_type(string $alertType): string
{
    $alertType = trim(strtolower($alertType));
    return array_key_exists($alertType, ALERT_TYPES) ? $alertType : 'price_drop';
}

function alert_type_label(string $alertType): string
{
    $alertType = normalize_alert_type($alertType);
    return ALERT_TYPES[$alertType];
}

function check_source_label(?string $source): string
{
    return match (strtolower((string)$source)) {
        'cron' => 'Cron job',
        'manual' => 'Web',
        default => 'Unknown',
    };
}

function default_booking_agency(string $currencyCode): array
{
    return [
        'id' => env_value('DEFAULT_AGENCY_ID', 'DIRECTSG'),
        'address' => [
            'stateId' => env_value('DEFAULT_AGENCY_STATE_ID', 'SINGAPORE'),
            'countryId' => env_value('DEFAULT_AGENCY_COUNTRY_ID', 'SG'),
        ],
        'phones' => [
            ['phoneTypeId' => 'W', 'number' => '6569226788'],
            ['phoneTypeId' => 'F', 'number' => '6569226787'],
        ],
        'gsaDefaultHomeCity' => env_value('DEFAULT_AGENCY_HOME_CITY', 'SIN'),
        'countryCanBooks' => ['MY', 'IN', 'ID', 'SG'],
        'borderCountries' => ['MY', 'ID', 'VN', 'BN'],
        'currencies' => [['id' => $currencyCode]],
        'collectDirectInfoFlag' => 'N',
        'dsms' => [
            ['year' => '2026', 'region' => '05', 'district' => '8T', 'dsmManager' => 'SHIRLENE'],
            ['year' => '2027', 'region' => '05', 'district' => '8T', 'dsmManager' => 'SHIRLENE'],
        ],
        'gdsName' => 'DISABLEXMLPARTNER',
        'commissions' => [
            ['year' => '2026', 'associationCode' => '1SEDIRCT', 'association' => 'DIRECT', 'salesProgram' => 'DB', 'adhocPricing' => 'AZ', 'typeFlag' => 'DIR'],
            ['year' => '2027', 'associationCode' => '1SEDIRCT', 'association' => 'DIRECT', 'salesProgram' => 'DB', 'adhocPricing' => 'AZ', 'typeFlag' => 'DIR'],
            ['year' => '2028', 'associationCode' => '1SEDIRCT', 'association' => 'DIRECT', 'salesProgram' => 'DB', 'adhocPricing' => 'AZ', 'typeFlag' => 'DIR'],
        ],
        'confirmationMethod' => 'E',
        'confirmationEmail' => env_value('DEFAULT_AGENCY_EMAIL', 'JYEO@CARNIVAL-SG.COM'),
        'edocsFlag' => 'N',
    ];
}

function build_princess_payload(array $params): array
{
    $cruiseId = trim((string)($params['cruise_id'] ?? env_value('DEFAULT_CRUISE_ID', 'H630')));
    $currencyCode = trim((string)($params['currency_code'] ?? env_value('DEFAULT_CURRENCY_CODE', 'USD')));
    $guestCountry = trim((string)($params['guest_country'] ?? env_value('DEFAULT_GUEST_COUNTRY', 'US')));
    $guestHomeCity = trim((string)($params['guest_home_city'] ?? env_value('DEFAULT_GUEST_HOME_CITY', 'LAX')));
    $guestCount = max(1, min(5, (int)($params['guest_count'] ?? env_value('DEFAULT_GUEST_COUNT', '2'))));
    $includeMisc = filter_var($params['include_misc'] ?? env_value('DEFAULT_INCLUDE_MISC', '1'), FILTER_VALIDATE_BOOLEAN);

    $guests = [];
    for ($i = 0; $i < $guestCount; $i++) {
        $guests[] = [
            'country' => $guestCountry,
            'homeCity' => $guestHomeCity,
        ];
    }

    return [
        'booking' => [
            'bookingAgency' => default_booking_agency($currencyCode),
            'currencyCode' => $currencyCode,
            'guests' => $guests,
            'couponCodes' => [],
        ],
        'filters' => [
            'cruises' => [$cruiseId],
        ],
        'leadInBy' => 'voyages',
        'retrieveFlags' => [
            'additionalGuestFare' => true,
            'averageFare' => false,
            'averageBrochureFare' => false,
            'includeMisc' => $includeMisc,
            'fareType' => 'BESTFARE',
            'roundUpFare' => true,
            'brochureFare' => true,
        ],
    ];
}

function headers_file_path(): string
{
    return private_path(env_value('PRINCESS_HEADERS_PATH', 'storage/headers.json') ?? 'storage/headers.json');
}

function load_princess_headers(): array
{
    $path = headers_file_path();
    if (!is_file($path)) {
        throw new RuntimeException('Missing headers file: ' . $path . '. Paste DevTools cURL on the Headers page first.');
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Headers file is not valid JSON: ' . $path);
    }

    $forbidden = [':authority', ':method', ':path', ':scheme', 'content-length', 'accept-encoding'];
    $headers = [];
    foreach ($decoded as $name => $value) {
        $lower = strtolower((string)$name);
        if (in_array($lower, $forbidden, true)) {
            continue;
        }
        if ($value === null || $value === '') {
            continue;
        }
        $headers[$name] = (string)$value;
    }
    return $headers;
}

function save_headers_from_json(array $headers): void
{
    $path = headers_file_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    file_put_contents($path, json_encode($headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    @chmod($path, 0600);
}

function parse_curl_headers(string $curl): array
{
    $headers = [];

    // Handles lines like: -H 'name: value' or --header "name: value".
    if (preg_match_all('/(?:-H|--header)\s+(?:\'([^\']*)\'|"([^"]*)")/m', $curl, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $headerLine = $match[1] !== '' ? $match[1] : $match[2];
            if (!str_contains($headerLine, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $headerLine, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name !== '') {
                $headers[$name] = $value;
            }
        }
    }

    // Handles curl -b 'cookie=value; other=value'.
    if (preg_match('/(?:-b|--cookie)\s+(?:\'([^\']*)\'|"([^"]*)")/m', $curl, $cookieMatch)) {
        $cookie = $cookieMatch[1] !== '' ? $cookieMatch[1] : $cookieMatch[2];
        if ($cookie !== '') {
            $headers['cookie'] = $cookie;
        }
    }

    if (!isset($headers['accept'])) {
        $headers['accept'] = 'application/json, text/plain, */*';
    }
    if (!isset($headers['content-type'])) {
        $headers['content-type'] = 'application/json';
    }
    if (!isset($headers['origin'])) {
        $headers['origin'] = 'https://www.princess.com';
    }
    if (!isset($headers['referer'])) {
        $headers['referer'] = 'https://www.princess.com/';
    }

    $forbidden = [':authority', ':method', ':path', ':scheme', 'content-length', 'accept-encoding'];
    foreach (array_keys($headers) as $name) {
        if (in_array(strtolower($name), $forbidden, true)) {
            unset($headers[$name]);
        }
    }

    return $headers;
}

function call_princess_api(array $payload): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required. Enable cURL in Bluehost PHP settings.');
    }

    $headers = load_princess_headers();
    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    $ch = curl_init(PRINCESS_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headerLines,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 45,
        CURLOPT_ENCODING => '',
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Princess API cURL error: ' . $error);
    }

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Princess API returned HTTP ' . $status . ': ' . substr((string)$body, 0, 1000));
    }

    $decoded = json_decode((string)$body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Princess API returned non-JSON response: ' . substr((string)$body, 0, 500));
    }

    return $decoded;
}

function parse_princess_prices(array $data): array
{
    $rows = [];

    foreach (($data['products'] ?? []) as $product) {
        $productId = $product['id'] ?? null;

        foreach (($product['cruises'] ?? []) as $cruise) {
            $cruiseId = $cruise['id'] ?? '';
            $pricing = $cruise['pricing'] ?? [];
            $currency = $pricing['fareCurrency'] ?? null;
            $tfpe = $pricing['tfpe'] ?? [];
            $taxesFees = $tfpe['lowerBth'] ?? null;

            foreach (($pricing['fares'] ?? []) as $fareBlock) {
                $categories = [];
                foreach (($fareBlock['categories'] ?? []) as $category) {
                    if (!empty($category['id'])) {
                        $categories[$category['id']] = $category;
                    }
                }

                foreach (($fareBlock['metas'] ?? []) as $meta) {
                    $cabinCode = $meta['id'] ?? '';
                    $cabinName = CABINS[$cabinCode] ?? ($cabinCode ?: 'Unknown');
                    $metaStatus = $meta['status'] ?? null;
                    $categoryId = $meta['bestCategory'] ?? null;
                    $categoryId = $categoryId !== '' ? $categoryId : null;
                    $category = $categoryId && isset($categories[$categoryId]) ? $categories[$categoryId] : null;

                    $fareGuest1 = null;
                    $fareGuest2 = null;
                    $farePerPerson = null;
                    $taxesFeesPerPerson = null;
                    $totalPerPerson = null;
                    $totalForTwo = null;

                    if ($category) {
                        $guests = [];
                        foreach (($category['guests'] ?? []) as $guest) {
                            if (isset($guest['id'])) {
                                $guests[(int)$guest['id']] = $guest;
                            }
                        }
                        $fareGuest1 = $guests[1]['fare'] ?? null;
                        $fareGuest2 = $guests[2]['fare'] ?? null;
                        $farePerPerson = $fareGuest1;

                        if ($farePerPerson !== null && $taxesFees !== null) {
                            $taxesFeesPerPerson = $taxesFees;
                            $totalPerPerson = (float)$farePerPerson + (float)$taxesFees;
                        }
                        if ($fareGuest1 !== null && $fareGuest2 !== null) {
                            $totalForTwo = (float)$fareGuest1 + (float)$fareGuest2;
                            if ($taxesFees !== null) {
                                $totalForTwo += ((float)$taxesFees * 2);
                            }
                        }
                    }

                    $rows[] = [
                        'cruise_id' => $cruiseId,
                        'product_id' => $productId,
                        'cabin_code' => $cabinCode,
                        'cabin_name' => $cabinName,
                        'status' => $metaStatus === 'A' ? 'Available' : 'Sold out',
                        'status_message' => $meta['statusMessage'] ?? null,
                        'category_id' => $categoryId,
                        'category_status' => $category['status'] ?? null,
                        'availability' => $category['availability'] ?? null,
                        'available_cabins' => $category['availableCabins'] ?? null,
                        'currency' => $currency,
                        'fare_guest_1' => $fareGuest1,
                        'fare_guest_2' => $fareGuest2,
                        'fare_per_person' => $farePerPerson,
                        'taxes_fees_per_person' => $taxesFeesPerPerson,
                        'total_per_person' => $totalPerPerson,
                        'total_for_two' => $totalForTwo,
                        'raw_json' => json_encode([
                            'meta' => $meta,
                            'category' => $category,
                            'pricing_tfpe' => $tfpe,
                        ], JSON_UNESCAPED_SLASHES),
                    ];
                }
            }
        }
    }

    return $rows;
}

function run_princess_check(array $params, string $source = 'manual'): array
{
    $payload = build_princess_payload($params);
    $cruiseId = $payload['filters']['cruises'][0] ?? 'UNKNOWN';
    $checkedAt = now_iso();
    $response = call_princess_api($payload);
    $rawId = insert_raw_response($source, $cruiseId, $payload, $response, $checkedAt);
    $rows = parse_princess_prices($response);
    save_price_rows($rows, $checkedAt, $rawId);
    check_price_alerts($rows);
    return $rows;
}

function check_price_alerts(array $rows): void
{
    $watches = get_watches();

    foreach ($watches as $watch) {
        if ((int)$watch['enabled'] !== 1) {
            continue;
        }

        foreach ($rows as $row) {
            if (($row['cruise_id'] ?? '') !== $watch['cruise_id']) {
                continue;
            }
            if (($row['cabin_code'] ?? '') !== $watch['cabin_code']) {
                continue;
            }
            process_watch_alert($watch, $row);
        }
    }
}

function process_watch_alert(array $watch, array $row): void
{
    $alertType = normalize_alert_type((string)($watch['alert_type'] ?? 'price_drop'));
    $lastSeenStatus = $watch['last_seen_status'] ?? null;
    $currentStatus = (string)($row['status'] ?? '');

    if ($alertType === 'availability') {
        $shouldNotify = $currentStatus === 'Available' && $lastSeenStatus === 'Sold out';
        if ($shouldNotify) {
            $message = build_watch_alert_message($watch, $row, $alertType);
            send_tracker_email($watch['email_to'], $message['subject'], $message['text'], $message['html']);
            update_watch_alert_state($watch['id'], null, now_iso(), $currentStatus);
            return;
        }

        update_watch_alert_state(
            (int)$watch['id'],
            $watch['last_alert_price'] !== null ? (float)$watch['last_alert_price'] : null,
            $watch['last_alert_at'] ?: null,
            $currentStatus
        );
        return;
    }

    if ($row['fare_per_person'] === null) {
        update_watch_alert_state(
            (int)$watch['id'],
            $watch['last_alert_price'] !== null ? (float)$watch['last_alert_price'] : null,
            $watch['last_alert_at'] ?: null,
            $currentStatus
        );
        return;
    }

    $current = (float)$row['fare_per_person'];
    $target = (float)$watch['target_price_per_person'];
    if ($current > $target) {
        update_watch_alert_state(
            (int)$watch['id'],
            $watch['last_alert_price'] !== null ? (float)$watch['last_alert_price'] : null,
            $watch['last_alert_at'] ?: null,
            $currentStatus
        );
        return;
    }
    if ($watch['last_alert_price'] !== null && $current >= (float)$watch['last_alert_price']) {
        update_watch_alert_state(
            (int)$watch['id'],
            (float)$watch['last_alert_price'],
            $watch['last_alert_at'] ?: null,
            $currentStatus
        );
        return;
    }

    $message = build_watch_alert_message($watch, $row, $alertType);
    send_tracker_email($watch['email_to'], $message['subject'], $message['text'], $message['html']);
    update_watch_alert_state((int)$watch['id'], $current, now_iso(), $currentStatus);
}

function build_watch_alert_message(array $watch, array $row, string $alertType): array
{
    $currency = (string)($row['currency'] ?? '');
    $currencyPrefix = $currency !== '' ? $currency . ' ' : '';
    $checkedAt = now_iso();
    $alertLabel = alert_type_label($alertType);
    $status = (string)($row['status'] ?? '');
    $subject = $alertType === 'availability'
        ? sprintf('Princess cabin available: %s %s', $row['cruise_id'], $row['cabin_name'])
        : sprintf('Princess price drop: %s %s %s%s', $row['cruise_id'], $row['cabin_name'], $currencyPrefix, number_format((float)$row['fare_per_person'], 0));

    $details = [
        'Cruise' => (string)$row['cruise_id'],
        'Cabin' => (string)$row['cabin_name'] . ' (' . (string)$row['cabin_code'] . ')',
        'Alert type' => $alertLabel,
        'Status' => $status,
        'Category' => (string)($row['category_id'] ?? ''),
        'Current fare per person' => $row['fare_per_person'] !== null ? $currencyPrefix . number_format((float)$row['fare_per_person'], 0) : 'Not available',
        'Target fare per person' => $alertType === 'price_drop' ? $currencyPrefix . number_format((float)$watch['target_price_per_person'], 0) : 'Not used for this alert',
        'Estimated taxes/fees per person' => $row['taxes_fees_per_person'] !== null ? $currencyPrefix . number_format((float)$row['taxes_fees_per_person'], 0) : '',
        'Estimated total per person' => $row['total_per_person'] !== null ? $currencyPrefix . number_format((float)$row['total_per_person'], 0) : '',
        'Estimated total for two' => $row['total_for_two'] !== null ? $currencyPrefix . number_format((float)$row['total_for_two'], 0) : '',
        'Availability' => (string)($row['availability'] ?? ''),
        'Available cabins' => (string)($row['available_cabins'] ?? ''),
        'Checked at' => $checkedAt,
    ];

    $textLines = [];
    foreach ($details as $label => $value) {
        if ($value === '') {
            continue;
        }
        $textLines[] = $label . ': ' . $value;
    }

    $htmlRows = [];
    foreach ($details as $label => $value) {
        if ($value === '') {
            continue;
        }
        $htmlRows[] = '<tr>'
            . '<th style="padding:10px 12px; text-align:left; color:#475569; border-bottom:1px solid #e2e8f0; width:38%;">' . h($label) . '</th>'
            . '<td style="padding:10px 12px; color:#0f172a; border-bottom:1px solid #e2e8f0;">' . h($value) . '</td>'
            . '</tr>';
    }

    $headline = $alertType === 'availability'
        ? 'A sold out cabin is available again.'
        : 'A tracked fare dropped to your target.';

    $html = '<!doctype html><html lang="en"><body style="margin:0; padding:24px; background:#f8fafc; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif; color:#0f172a;">'
        . '<div style="max-width:680px; margin:0 auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:20px; overflow:hidden;">'
        . '<div style="padding:24px 28px; background:linear-gradient(135deg,#0f766e,#134e4a); color:#ffffff;">'
        . '<p style="margin:0 0 8px; font-size:12px; letter-spacing:0.08em; text-transform:uppercase; opacity:0.85;">Princess Tracker Alert</p>'
        . '<h1 style="margin:0; font-size:28px; line-height:1.2;">' . h($row['cruise_id'] . ' ' . $row['cabin_name']) . '</h1>'
        . '<p style="margin:12px 0 0; font-size:15px; opacity:0.92;">' . h($headline) . '</p>'
        . '</div>'
        . '<div style="padding:24px 28px;">'
        . '<table style="width:100%; border-collapse:collapse; font-size:14px;">' . implode('', $htmlRows) . '</table>'
        . '<p style="margin:20px 0 0; color:#64748b; font-size:13px;">Generated by the Princess cruise price tracker.</p>'
        . '</div></div></body></html>';

    return [
        'subject' => $subject,
        'text' => implode("\n", $textLines),
        'html' => $html,
    ];
}
