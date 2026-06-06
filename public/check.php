<?php
require_once __DIR__ . '/_init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('index.php');
}

try {
    $rows = run_princess_check($_POST, 'manual');
    render_header('Price results');
} catch (Throwable $e) {
    render_header('Price check failed');
    $help = is_admin()
        ? '<p><a href="headers.php">Update headers</a> or try again.</p>'
        : '<p>Please contact an administrator or try again later.</p>';
    echo '<section class="card"><div class="flash error">' . h($e->getMessage()) . '</div>' . $help . '</section>';
    render_footer();
    exit;
}
?>
<section class="card">
  <table>
    <thead>
      <tr>
        <th>Cruise</th><th>Cabin</th><th>Status</th><th>Category</th><th>Currency</th>
        <th>Fare pp</th><th>Guest 1</th><th>Guest 2</th><th>Taxes/fees pp</th>
        <th>Total pp</th><th>Total for 2</th><th>Availability</th><th>Cabins</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h($r['cruise_id']) ?></td>
          <td><?= h($r['cabin_name']) ?></td>
          <td><span class="pill <?= $r['status'] === 'Available' ? 'ok' : 'sold' ?>"><?= h($r['status']) ?></span></td>
          <td><?= h($r['category_id'] ?? '') ?></td>
          <td><?= h($r['currency'] ?? '') ?></td>
          <td><?= h(money_value($r['fare_per_person'])) ?></td>
          <td><?= h(money_value($r['fare_guest_1'])) ?></td>
          <td><?= h(money_value($r['fare_guest_2'])) ?></td>
          <td><?= h(money_value($r['taxes_fees_per_person'])) ?></td>
          <td><?= h(money_value($r['total_per_person'])) ?></td>
          <td><?= h(money_value($r['total_for_two'])) ?></td>
          <td><?= h($r['availability'] ?? '') ?></td>
          <td><?= h($r['available_cabins'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<p><a href="history.php?cruise_id=<?= h(urlencode($rows[0]['cruise_id'] ?? '')) ?>">View history graph</a></p>
<?php render_footer(); ?>
