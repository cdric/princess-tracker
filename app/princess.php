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
    $includeMisc = filter_var($params['include_misc'] ?? env_value('DEFAULT_INCLUDE_MISC', '1'), FILTER_VALIDATE_BOOL);

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
            if ($row['fare_per_person'] === null) {
                continue;
            }

            $current = (float)$row['fare_per_person'];
            $target = (float)$watch['target_price_per_person'];
            if ($current > $target) {
                continue;
            }
            if ($watch['last_alert_price'] !== null && $current >= (float)$watch['last_alert_price']) {
                continue;
            }

            $currency = $row['currency'] ?? '';
            $subject = sprintf('Princess price drop: %s %s %s %s', $row['cruise_id'], $row['cabin_name'], $currency, number_format($current, 0));
            $body = "Cruise: {$row['cruise_id']}\n";
            $body .= "Cabin: {$row['cabin_name']} ({$row['cabin_code']})\n";
            $body .= "Category: " . ($row['category_id'] ?? '') . "\n";
            $body .= "Current fare per person: {$currency} " . number_format($current, 0) . "\n";
            $body .= "Target fare per person: {$currency} " . number_format($target, 0) . "\n";
            $body .= "Estimated taxes/fees per person: " . ($row['taxes_fees_per_person'] ?? '') . "\n";
            $body .= "Estimated total per person: " . ($row['total_per_person'] ?? '') . "\n";
            $body .= "Estimated total for two: " . ($row['total_for_two'] ?? '') . "\n";
            $body .= "Availability: " . ($row['availability'] ?? '') . "\n";
            $body .= "Available cabins: " . ($row['available_cabins'] ?? '') . "\n";
            $body .= "Checked at: " . now_iso() . "\n";

            send_tracker_email($watch['email_to'], $subject, $body);

            $stmt = db()->prepare('UPDATE watches SET last_alert_price = ?, last_alert_at = ? WHERE id = ?');
            $stmt->execute([$current, now_iso(), $watch['id']]);
        }
    }
}
