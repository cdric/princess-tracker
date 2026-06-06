<?php
require_once __DIR__ . '/_init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'delete' && !empty($_POST['price_check_id'])) {
            delete_price_check((int)$_POST['price_check_id']);
            delete_orphaned_raw_api_responses();
            flash_set('History row deleted.', 'success');
        }
    } catch (Throwable $e) {
        flash_set($e->getMessage(), 'error');
    }
    $redirectCruise = trim($_POST['redirect_cruise_id'] ?? '');
    redirect_to('history.php' . ($redirectCruise !== '' ? '?cruise_id=' . urlencode($redirectCruise) : ''));
}

$cruiseIds = distinct_cruise_ids();
$fallbackCruise = $cruiseIds[0] ?? env_value('DEFAULT_CRUISE_ID', 'H630');
$selectedCruise = trim($_GET['cruise_id'] ?? $fallbackCruise);
if ($cruiseIds && !in_array($selectedCruise, $cruiseIds, true)) {
    $selectedCruise = $fallbackCruise;
}
$rows = latest_history($selectedCruise ?: null, 500);

render_header('Price history');
?>
<section class="card">
  <form method="get" class="inline-form">
    <label>Cruise code</label>
    <select name="cruise_id" required>
      <?php foreach ($cruiseIds ?: [$selectedCruise] as $id): ?>
        <option value="<?= h($id) ?>" <?= $id === $selectedCruise ? 'selected' : '' ?>><?= h($id) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Load history</button>
  </form>
</section>
<?= render_history_graph($rows, $selectedCruise) ?>
<section class="card">
  <h2>History table</h2>
  <table>
    <thead>
      <tr>
        <th>Checked at</th><th>Source</th><th>Cruise</th><th>Cabin</th><th>Status</th><th>Category</th><th>Currency</th>
        <th>Fare pp</th><th>Taxes/fees pp</th><th>Total pp</th><th>Total for 2</th><th>Available cabins</th>
        <?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h($r['checked_at']) ?></td>
          <?php $source = check_source_label($r['check_source'] ?? null); ?>
          <?php $sourceClass = $source === 'Cron job' ? 'source-cron' : ($source === 'Web' ? 'source-web' : ''); ?>
          <td><span class="pill <?= h($sourceClass) ?>"><?= h($source) ?></span></td>
          <td><?= h($r['cruise_id']) ?></td>
          <td><?= h($r['cabin_name']) ?></td>
          <td><span class="pill <?= $r['status'] === 'Available' ? 'ok' : 'sold' ?>"><?= h($r['status']) ?></span></td>
          <td><?= h($r['category_id'] ?? '') ?></td>
          <td><?= h($r['currency'] ?? '') ?></td>
          <td><?= h(money_value($r['fare_per_person'])) ?></td>
          <td><?= h(money_value($r['taxes_fees_per_person'])) ?></td>
          <td><?= h(money_value($r['total_per_person'])) ?></td>
          <td><?= h(money_value($r['total_for_two'])) ?></td>
          <td><?= h($r['available_cabins'] ?? '') ?></td>
          <?php if (is_admin()): ?>
          <td class="actions">
            <form method="post">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="price_check_id" value="<?= h($r['id']) ?>">
              <input type="hidden" name="redirect_cruise_id" value="<?= h($selectedCruise) ?>">
              <button class="danger" type="submit" onclick="return confirm('Delete this history row?')">Delete</button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php render_footer(); ?>
