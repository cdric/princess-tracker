<?php

declare(strict_types=1);

function render_history_graph(array $rows, string $cruiseId): string
{
    $fareSeries = [];
    $fareTimestamps = [];
    $fareValues = [];
    $availabilitySnapshots = [];

    foreach (array_reverse($rows) as $row) {
        if (($row['cruise_id'] ?? '') !== $cruiseId) {
            continue;
        }

        $ts = strtotime((string)$row['checked_at']);
        if ($ts === false) {
            continue;
        }

        $cabin = $row['cabin_name'] ?: $row['cabin_code'];
        $label = date('M j H:i', $ts);

        if ($row['fare_per_person'] !== null && $row['fare_per_person'] !== '') {
            $fareSeries[$cabin][] = [
                'ts' => $ts,
                'label' => $label,
                'value' => (float)$row['fare_per_person'],
                'currency' => $row['currency'] ?? '',
            ];
            $fareTimestamps[] = $ts;
            $fareValues[] = (float)$row['fare_per_person'];
        }

        $availableCabins = parse_available_cabins_value($row['available_cabins'] ?? null);
        if ($availableCabins === null && ($row['status'] ?? '') === 'Sold out') {
            $availableCabins = 0;
        }
        if ($availableCabins === null) {
            continue;
        }

        $availabilitySnapshots[$cabin][] = [
            'ts' => $ts,
            'label' => $label,
            'value' => $availableCabins,
            'status' => $row['status'] ?? '',
        ];
    }

    $availabilityTimestamps = [];
    $availabilityValues = [];
    foreach ($availabilitySnapshots as $points) {
        foreach ($points as $point) {
            $availabilityTimestamps[] = $point['ts'];
            $availabilityValues[] = (float)$point['value'];
        }
    }

    $sections = [];
    $sections[] = render_line_chart_card(
        'Fare history for ' . $cruiseId,
        'Fare history graph',
        $fareSeries,
        $fareTimestamps,
        $fareValues,
        'Fare per person',
        static function (array $point, string $cabin): string {
            $prefix = ($point['currency'] ?? '') !== '' ? $point['currency'] . ' ' : '';
            return $cabin . ': ' . $prefix . number_format((float)$point['value'], 0) . ' on ' . $point['label'];
        },
        'Lines use fare per person. Taxes and fees are shown in the table below when available.',
        'No fare history to graph yet for cruise ' . h($cruiseId) . '.'
    );

    $sections[] = render_line_chart_card(
        'Available cabins for ' . $cruiseId,
        'Available cabins graph',
        $availabilitySnapshots,
        $availabilityTimestamps,
        $availabilityValues,
        'Available cabins',
        static function (array $point, string $cabin): string {
            return $cabin . ': ' . number_format((float)$point['value'], 0) . ' available cabins on ' . $point['label'] . ' (' . ($point['status'] ?? 'Unknown') . ')';
        },
        'Lines show the available cabin count per cabin type from the stored Princess history.',
        'No cabin availability history is available yet for an available-cabins graph.'
    );

    $sections[] = render_chart_tooltip_script();

    return implode("\n", $sections);
}

function render_line_chart_card(
    string $title,
    string $ariaLabel,
    array $seriesByLabel,
    array $timestamps,
    array $values,
    string $yAxisLabel,
    callable $titleFormatter,
    string $footer,
    string $emptyMessage
): string {
    if (!$seriesByLabel || !$timestamps || !$values) {
        return '<div class="empty-state">' . $emptyMessage . '</div>';
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
        $minVal = max(0, $minVal - 1);
        $maxVal += 1;
    }
    $pad = max(1, ($maxVal - $minVal) * 0.12);
    $minVal = max(0, $minVal - $pad);
    $maxVal += $pad;

    $colors = ['#0f766e', '#ea580c', '#2563eb', '#7c3aed', '#be123c', '#475569'];

    $x = static function (int $ts) use ($minTs, $maxTs, $left, $plotW): float {
        return $left + (($ts - $minTs) / max(1, $maxTs - $minTs)) * $plotW;
    };
    $y = static function (float $value) use ($minVal, $maxVal, $top, $plotH): float {
        return $top + ($maxVal - $value) / max(1, $maxVal - $minVal) * $plotH;
    };

    $svg = [];
    $svg[] = '<div class="chart-card">';
    $svg[] = '<h2>' . h($title) . '</h2>';
    $svg[] = '<div class="chart-wrap">';
    $svg[] = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="' . h($ariaLabel) . '">';
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
    $svg[] = '<text x="18" y="' . ($top + $plotH / 2) . '" transform="rotate(-90 18 ' . ($top + $plotH / 2) . ')" text-anchor="middle" font-size="13" fill="#334155">' . h($yAxisLabel) . '</text>';

    $legendX = $left;
    $legendY = $height - 28;
    $idx = 0;
    foreach ($seriesByLabel as $seriesLabel => $points) {
        $color = $colors[$idx % count($colors)];
        $path = '';
        foreach ($points as $j => $point) {
            $cmd = $j === 0 ? 'M' : 'L';
            $path .= $cmd . ' ' . round($x($point['ts']), 2) . ' ' . round($y((float)$point['value']), 2) . ' ';
        }
        if (count($points) === 1) {
            $p = $points[0];
            $svg[] = '<circle cx="' . $x($p['ts']) . '" cy="' . $y((float)$p['value']) . '" r="5" fill="' . $color . '" />';
        } else {
            $svg[] = '<path d="' . trim($path) . '" fill="none" stroke="' . $color . '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />';
        }

        foreach ($points as $point) {
            $cx = round($x($point['ts']), 2);
            $cy = round($y((float)$point['value']), 2);
            $title = h($titleFormatter($point, $seriesLabel));
            $svg[] = '<g>';
            $svg[] = '<circle class="chart-point-hit" cx="' . $cx . '" cy="' . $cy . '" r="12" fill="#ffffff" fill-opacity="0.01" data-tooltip="' . $title . '" style="cursor:pointer;" />';
            $svg[] = '<circle class="chart-point-hit" cx="' . $cx . '" cy="' . $cy . '" r="4" fill="#fff" stroke="' . $color . '" stroke-width="2" data-tooltip="' . $title . '" style="cursor:pointer;" />';
            $svg[] = '</g>';
        }

        $svg[] = '<rect x="' . $legendX . '" y="' . ($legendY - 10) . '" width="14" height="4" rx="2" fill="' . $color . '" />';
        $svg[] = '<text x="' . ($legendX + 22) . '" y="' . ($legendY - 4) . '" font-size="13" fill="#334155">' . h($seriesLabel) . '</text>';
        $legendX += 130;
        $idx++;
    }

    $svg[] = '</svg>';
    $svg[] = '<div class="chart-tooltip" hidden></div>';
    $svg[] = '</div>';
    $svg[] = '<p class="muted">' . h($footer) . '</p>';
    $svg[] = '</div>';

    return implode("\n", $svg);
}

function render_chart_tooltip_script(): string
{
    static $rendered = false;
    if ($rendered) {
        return '';
    }
    $rendered = true;

    return <<<HTML
<script>
document.addEventListener('mouseover', (event) => {
  const point = event.target.closest('.chart-point-hit');
  if (!point) {
    return;
  }
  const chartWrap = point.closest('.chart-wrap');
  const tooltip = chartWrap ? chartWrap.querySelector('.chart-tooltip') : null;
  const text = point.getAttribute('data-tooltip');
  if (!chartWrap || !tooltip || !text) {
    return;
  }
  tooltip.textContent = text;
  tooltip.hidden = false;
});

document.addEventListener('mousemove', (event) => {
  const point = event.target.closest('.chart-point-hit');
  if (!point) {
    return;
  }
  const chartWrap = point.closest('.chart-wrap');
  const tooltip = chartWrap ? chartWrap.querySelector('.chart-tooltip') : null;
  if (!chartWrap || !tooltip) {
    return;
  }
  const wrapRect = chartWrap.getBoundingClientRect();
  const offsetX = event.clientX - wrapRect.left + 14;
  const offsetY = event.clientY - wrapRect.top - 14;
  tooltip.style.left = offsetX + 'px';
  tooltip.style.top = offsetY + 'px';
});

document.addEventListener('mouseout', (event) => {
  const point = event.target.closest('.chart-point-hit');
  if (!point) {
    return;
  }
  const chartWrap = point.closest('.chart-wrap');
  const tooltip = chartWrap ? chartWrap.querySelector('.chart-tooltip') : null;
  if (tooltip) {
    tooltip.hidden = true;
  }
});
</script>
HTML;
}

function parse_available_cabins_value($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        return max(0, (int)$value);
    }

    if (preg_match('/(\d+)/', (string)$value, $matches)) {
        return max(0, (int)$matches[1]);
    }

    return null;
}
