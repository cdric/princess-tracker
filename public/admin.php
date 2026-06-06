<?php
require_once __DIR__ . '/_init.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            add_watch(
                trim($_POST['cruise_id'] ?? ''),
                trim($_POST['cabin_code'] ?? ''),
                (float)($_POST['target_price_per_person'] ?? 0),
                trim($_POST['email_to'] ?? ''),
                trim($_POST['alert_type'] ?? 'price_drop')
            );
            flash_set('Watch added.', 'success');
        } elseif ($action === 'enable') {
            set_watch_enabled((int)$_POST['watch_id'], true);
            flash_set('Watch enabled.', 'success');
        } elseif ($action === 'disable') {
            set_watch_enabled((int)$_POST['watch_id'], false);
            flash_set('Watch disabled.', 'success');
        } elseif ($action === 'delete') {
            delete_watch((int)$_POST['watch_id']);
            flash_set('Watch deleted.', 'success');
        }
    } catch (Throwable $e) {
        flash_set($e->getMessage(), 'error');
    }
    redirect_to('admin.php');
}

$watches = get_watches();
$snapshotRows = latest_price_rows_by_cruise();
$snapshotMap = [];
$cruiseIds = [];
foreach ($snapshotRows as $row) {
    $cruiseIds[] = $row['cruise_id'];
    $snapshotMap[$row['cruise_id']][$row['cabin_code']] = $row;
}
$cruiseIds = array_values(array_unique($cruiseIds));
$fallbackCruise = $cruiseIds[0] ?? env_value('DEFAULT_CRUISE_ID', 'H630');
$selectedCruise = trim($_POST['cruise_id'] ?? $_GET['cruise_id'] ?? $fallbackCruise);
if ($cruiseIds && !in_array($selectedCruise, $cruiseIds, true)) {
    $selectedCruise = $fallbackCruise;
}
$cabinsForSelectedCruise = array_keys($snapshotMap[$selectedCruise] ?? []);
$selectedCabin = trim($_POST['cabin_code'] ?? $_GET['cabin_code'] ?? ($cabinsForSelectedCruise[0] ?? array_key_first(CABINS)));
$selectedAlertType = normalize_alert_type(trim($_POST['alert_type'] ?? $_GET['alert_type'] ?? 'price_drop'));
$latestSelectedSnapshot = $snapshotMap[$selectedCruise][$selectedCabin] ?? null;
render_header('Admin watches');
?>
<section class="card">
  <h2>Add watch</h2>
  <form method="post" class="grid-form">
    <input type="hidden" name="action" value="add">
    <div>
      <label>Cruise code</label>
      <select name="cruise_id" id="watch_cruise_id" required>
        <?php foreach ($cruiseIds ?: [$selectedCruise] as $id): ?>
          <option value="<?= h($id) ?>" <?= $id === $selectedCruise ? 'selected' : '' ?>><?= h($id) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Cabin type</label>
      <select name="cabin_code" id="watch_cabin_code" required>
        <?php foreach (($cabinsForSelectedCruise ?: array_keys(CABINS)) as $code): ?>
          <option value="<?= h($code) ?>" <?= $code === $selectedCabin ? 'selected' : '' ?>><?= h(CABINS[$code] ?? $code) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Alert type</label>
      <select name="alert_type" id="watch_alert_type">
        <?php foreach (ALERT_TYPES as $code => $name): ?>
          <option value="<?= h($code) ?>" <?= $code === $selectedAlertType ? 'selected' : '' ?>><?= h($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div id="target_price_group">
      <label>Target fare per person</label>
      <input type="number" step="1" name="target_price_per_person" id="target_price_per_person" required>
    </div>
    <div>
      <label>Email to</label>
      <input type="email" name="email_to" required>
    </div>
    <div class="full-row">
      <div class="info-panel" id="watch_latest_summary">
        <?php if ($latestSelectedSnapshot): ?>
          Latest known fare for <?= h($latestSelectedSnapshot['cruise_id']) ?> <?= h(CABINS[$latestSelectedSnapshot['cabin_code']] ?? $latestSelectedSnapshot['cabin_code']) ?>:
          <strong><?= h(money_value($latestSelectedSnapshot['fare_per_person'], $latestSelectedSnapshot['currency'] ?? '')) ?></strong>.
          This is the latest price known in history, checked via <?= h(check_source_label($latestSelectedSnapshot['check_source'] ?? null)) ?> on <?= h($latestSelectedSnapshot['checked_at']) ?>.
        <?php else: ?>
          No history is available yet for this cruise and cabin combination.
        <?php endif; ?>
      </div>
      <p class="muted" id="watch_target_hint">Enter the target fare per person that should trigger the alert.</p>
      <button type="submit">Add watch</button>
    </div>
  </form>
</section>
<section class="card">
  <h2>Configured watches</h2>
  <table>
    <thead>
      <tr><th>ID</th><th>Cruise</th><th>Cabin</th><th>Type</th><th>Latest known</th><th>Target</th><th>Email</th><th>Status</th><th>Last alert</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($watches as $w): ?>
        <?php $latestRow = $snapshotMap[$w['cruise_id']][$w['cabin_code']] ?? null; ?>
        <tr>
          <td><?= h($w['id']) ?></td>
          <td><?= h($w['cruise_id']) ?></td>
          <td><?= h(CABINS[$w['cabin_code']] ?? $w['cabin_code']) ?></td>
          <td><?= h(alert_type_label($w['alert_type'] ?? 'price_drop')) ?></td>
          <td>
            <?php if ($latestRow): ?>
              <?= h(money_value($latestRow['fare_per_person'], $latestRow['currency'] ?? '')) ?><br>
              <span class="muted">Latest price known on <?= h($latestRow['checked_at']) ?></span>
            <?php else: ?>
              <span class="muted">No history yet</span>
            <?php endif; ?>
          </td>
          <td><?= h(($w['alert_type'] ?? 'price_drop') === 'price_drop' ? money_value($w['target_price_per_person']) : 'Not used') ?></td>
          <td><?= h($w['email_to']) ?></td>
          <td><?= (int)$w['enabled'] === 1 ? '<span class="pill ok">Enabled</span>' : '<span class="pill sold">Disabled</span>' ?></td>
          <td>
            <?php if (($w['alert_type'] ?? 'price_drop') === 'availability'): ?>
              <?= h($w['last_alert_at'] ? 'Sent at ' . $w['last_alert_at'] : '') ?>
            <?php else: ?>
              <?= h($w['last_alert_price'] ? money_value($w['last_alert_price']) . ' at ' . $w['last_alert_at'] : '') ?>
            <?php endif; ?>
          </td>
          <td class="actions">
            <form method="post">
              <input type="hidden" name="watch_id" value="<?= h($w['id']) ?>">
              <?php if ((int)$w['enabled'] === 1): ?>
                <button class="secondary" name="action" value="disable">Disable</button>
              <?php else: ?>
                <button class="secondary" name="action" value="enable">Enable</button>
              <?php endif; ?>
              <button class="danger" name="action" value="delete" onclick="return confirm('Delete this watch?')">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<script>
const watchSnapshots = <?= json_encode($snapshotMap, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const cabinLabels = <?= json_encode(CABINS, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const cruiseSelect = document.getElementById('watch_cruise_id');
const cabinSelect = document.getElementById('watch_cabin_code');
const alertTypeSelect = document.getElementById('watch_alert_type');
const targetInput = document.getElementById('target_price_per_person');
const targetGroup = document.getElementById('target_price_group');
const latestSummary = document.getElementById('watch_latest_summary');
const targetHint = document.getElementById('watch_target_hint');

function renderCabinOptions() {
  const cruiseId = cruiseSelect.value;
  const cabins = Object.keys(watchSnapshots[cruiseId] || {});
  const selected = cabinSelect.value;
  cabinSelect.innerHTML = '';
  const optionValues = cabins.length ? cabins : Object.keys(cabinLabels);
  optionValues.forEach((code) => {
    const option = document.createElement('option');
    option.value = code;
    option.textContent = cabinLabels[code] || code;
    if (code === selected) {
      option.selected = true;
    }
    cabinSelect.appendChild(option);
  });
}

function formatMoney(value, currency) {
  if (value === null || value === undefined || value === '') {
    return 'Not available';
  }
  const prefix = currency ? `${currency} ` : '';
  return `${prefix}${Number(value).toLocaleString()}`;
}

function updateWatchSummary() {
  const cruiseId = cruiseSelect.value;
  const cabinCode = cabinSelect.value;
  const alertType = alertTypeSelect.value;
  const snapshot = (watchSnapshots[cruiseId] || {})[cabinCode];
  const latestPriceText = snapshot
    ? `Latest known fare for ${cruiseId} ${cabinLabels[cabinCode] || cabinCode}: ${formatMoney(snapshot.fare_per_person, snapshot.currency)}. This is the latest price known in history, checked via ${snapshot.check_source === 'cron' ? 'Cron job' : 'Web'} on ${snapshot.checked_at}.`
    : 'No history is available yet for this cruise and cabin combination.';
  latestSummary.textContent = latestPriceText;

  const isPriceDrop = alertType === 'price_drop';
  targetInput.required = isPriceDrop;
  targetInput.disabled = !isPriceDrop;
  targetGroup.classList.toggle('is-disabled', !isPriceDrop);
  const targetValue = targetInput.value ? formatMoney(targetInput.value, snapshot ? snapshot.currency : '') : 'not set yet';
  targetHint.textContent = isPriceDrop
    ? `Target fare per person: ${targetValue}. Enter the price that should trigger the alert.`
    : 'Back in stock alerts fire when this cabin moves from sold out to available. Target fare per person is not used for this alert type.';
}

cruiseSelect.addEventListener('change', () => {
  renderCabinOptions();
  updateWatchSummary();
});
cabinSelect.addEventListener('change', updateWatchSummary);
alertTypeSelect.addEventListener('change', updateWatchSummary);
targetInput.addEventListener('input', updateWatchSummary);
renderCabinOptions();
updateWatchSummary();
</script>
<?php render_footer(); ?>
