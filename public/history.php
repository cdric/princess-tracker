<?php
require_once __DIR__ . '/_init.php';
require_login();

$cruiseIds = distinct_cruise_ids();
$selectedCruise = trim($_GET['cruise_id'] ?? env_value('DEFAULT_CRUISE_ID', 'H630'));
$rows = latest_history($selectedCruise ?: null, 500);

render_header('Price history');
?>
<section class="card">
  <form method="get" class="inline-form">
    <label>Cruise code</label>
    <input name="cruise_id" value="<?= h($selectedCruise) ?>" list="cruise_ids" required>
    <datalist id="cruise_ids">
      <?php foreach ($cruiseIds as $id): ?><option value="<?= h($id) ?>"><?php endforeach; ?>
    </datalist>
    <button type="submit">Load history</button>
  </form>
</section>
<?= render_history_graph($rows, $selectedCruise) ?>
<section class="card">
  <h2>History table</h2>
  <table>
    <thead>
      <tr>
        <th>Checked at</th><th>Cruise</th><th>Cabin</th><th>Status</th><th>Category</th><th>Currency</th>
        <th>Fare pp</th><th>Taxes/fees pp</th><th>Total pp</th><th>Total for 2</th><th>Available cabins</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h($r['checked_at']) ?></td>
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
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php render_footer(); ?>
