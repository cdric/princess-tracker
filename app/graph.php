<?php

declare(strict_types=1);

function render_history_graph(array $rows, string $cruiseId): string
{
    $pointsByCabin = [];
    $timestamps = [];
    $values = [];

    foreach (array_reverse($rows) as $row) {
        if (($row['cruise_id'] ?? '') !== $cruiseId) {
            continue;
        }
        if ($row['fare_per_person'] === null || $row['fare_per_person'] === '') {
            continue;
        }
        $ts = strtotime((string)$row['checked_at']);
        if ($ts === false) {
            continue;
        }
        $cabin = $row['cabin_name'] ?: $row['cabin_code'];
        $pointsByCabin[$cabin][] = [
            'ts' => $ts,
            'label' => date('M j H:i', $ts),
            'value' => (float)$row['fare_per_person'],
            'currency' => $row['currency'] ?? '',
        ];
        $timestamps[] = $ts;
        $values[] = (float)$row['fare_per_person'];
    }

    if (!$pointsByCabin || !$timestamps || !$values) {
        return '<div class="empty-state">No fare history to graph yet for cruise ' . h($cruiseId) . '.</div>';
    }

    $width = 980;
    $height = 420;
    $left = 78;
    $right = 30;
    $top = 34;
    $bottom = 82;
    $plotW = $width - $left - $right;
    $plotH = $height - $top - $bottom;

    $minTs = min($timestamps);
    $maxTs = max($timestamps);
    if ($minTs === $maxTs) {
        $minTs -= 3600;
        $maxTs += 3600;
    }

    $minVal = min($values);
    $maxVal = max($values);
    if ($minVal === $maxVal) {
        $minVal -= 100;
        $maxVal += 100;
    }
    $pad = max(25, ($maxVal - $minVal) * 0.12);
    $minVal = max(0, $minVal - $pad);
    $maxVal += $pad;

    $colors = ['#0f766e', '#ea580c', '#2563eb', '#7c3aed', '#be123c', '#475569'];

    $x = function (int $ts) use ($minTs, $maxTs, $left, $plotW): float {
        return $left + (($ts - $minTs) / max(1, $maxTs - $minTs)) * $plotW;
    };
    $y = function (float $value) use ($minVal, $maxVal, $top, $plotH): float {
        return $top + ($maxVal - $value) / max(1, $maxVal - $minVal) * $plotH;
    };

    $svg = [];
    $svg[] = '<div class="chart-card">';
    $svg[] = '<h2>Fare history for ' . h($cruiseId) . '</h2>';
    $svg[] = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Fare history graph">';
    $svg[] = '<rect x="0" y="0" width="' . $width . '" height="' . $height . '" rx="16" fill="#ffffff" />';

    for ($i = 0; $i <= 4; $i++) {
        $value = $minVal + (($maxVal - $minVal) * $i / 4);
        $gy = $y($value);
        $svg[] = '<line x1="' . $left . '" y1="' . $gy . '" x2="' . ($width - $right) . '" y2="' . $gy . '" stroke="#e5e7eb" stroke-width="1" />';
        $svg[] = '<text x="' . ($left - 12) . '" y="' . ($gy + 4) . '" text-anchor="end" font-size="12" fill="#64748b">' . h(number_format($value, 0)) . '</text>';
    }

    $tickCount = min(5, max(2, count(array_unique($timestamps))));
    for ($i = 0; $i < $tickCount; $i++) {
        $ts = (int)round($minTs + (($maxTs - $minTs) * $i / max(1, $tickCount - 1)));
        $gx = $x($ts);
        $svg[] = '<line x1="' . $gx . '" y1="' . $top . '" x2="' . $gx . '" y2="' . ($height - $bottom) . '" stroke="#f1f5f9" stroke-width="1" />';
        $svg[] = '<text x="' . $gx . '" y="' . ($height - $bottom + 24) . '" text-anchor="middle" font-size="12" fill="#64748b">' . h(date('M j', $ts)) . '</text>';
    }

    $svg[] = '<line x1="' . $left . '" y1="' . ($height - $bottom) . '" x2="' . ($width - $right) . '" y2="' . ($height - $bottom) . '" stroke="#94a3b8" stroke-width="1" />';
    $svg[] = '<line x1="' . $left . '" y1="' . $top . '" x2="' . $left . '" y2="' . ($height - $bottom) . '" stroke="#94a3b8" stroke-width="1" />';
    $svg[] = '<text x="18" y="' . ($top + $plotH / 2) . '" transform="rotate(-90 18 ' . ($top + $plotH / 2) . ')" text-anchor="middle" font-size="13" fill="#334155">Fare per person</text>';

    $legendX = $left;
    $legendY = $height - 28;
    $idx = 0;
    foreach ($pointsByCabin as $cabin => $points) {
        $color = $colors[$idx % count($colors)];
        $path = '';
        foreach ($points as $j => $point) {
            $cmd = $j === 0 ? 'M' : 'L';
            $path .= $cmd . ' ' . round($x($point['ts']), 2) . ' ' . round($y($point['value']), 2) . ' ';
        }
        if (count($points) === 1) {
            $p = $points[0];
            $cx = $x($p['ts']);
            $cy = $y($p['value']);
            $svg[] = '<circle cx="' . $cx . '" cy="' . $cy . '" r="5" fill="' . $color . '" />';
        } else {
            $svg[] = '<path d="' . trim($path) . '" fill="none" stroke="' . $color . '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />';
        }
        foreach ($points as $point) {
            $svg[] = '<circle cx="' . round($x($point['ts']), 2) . '" cy="' . round($y($point['value']), 2) . '" r="4" fill="#fff" stroke="' . $color . '" stroke-width="2"><title>' . h($cabin . ': ' . ($point['currency'] ? $point['currency'] . ' ' : '') . number_format($point['value'], 0) . ' on ' . $point['label']) . '</title></circle>';
        }

        $svg[] = '<rect x="' . $legendX . '" y="' . ($legendY - 10) . '" width="14" height="4" rx="2" fill="' . $color . '" />';
        $svg[] = '<text x="' . ($legendX + 22) . '" y="' . ($legendY - 4) . '" font-size="13" fill="#334155">' . h($cabin) . '</text>';
        $legendX += 130;
        $idx++;
    }

    $svg[] = '</svg>';
    $svg[] = '<p class="muted">Lines use fare per person. Taxes and fees are shown in the table below when available.</p>';
    $svg[] = '</div>';

    return implode("\n", $svg);
}
