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
                trim($_POST['email_to'] ?? '')
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
render_header('Admin watches');
?>
<section class="card">
  <h2>Add price-drop watch</h2>
  <form method="post" class="grid-form">
    <input type="hidden" name="action" value="add">
    <div>
      <label>Cruise code</label>
      <input name="cruise_id" value="<?= h(env_value('DEFAULT_CRUISE_ID', 'H630')) ?>" required>
    </div>
    <div>
      <label>Cabin type</label>
      <select name="cabin_code">
        <?php foreach (CABINS as $code => $name): ?>
          <option value="<?= h($code) ?>"><?= h($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Target fare per person</label>
      <input type="number" step="1" name="target_price_per_person" required>
    </div>
    <div>
      <label>Email to</label>
      <input type="email" name="email_to" required>
    </div>
    <div class="full-row"><button type="submit">Add watch</button></div>
  </form>
</section>
<section class="card">
  <h2>Configured watches</h2>
  <table>
    <thead>
      <tr><th>ID</th><th>Cruise</th><th>Cabin</th><th>Target</th><th>Email</th><th>Status</th><th>Last alert</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($watches as $w): ?>
        <tr>
          <td><?= h($w['id']) ?></td>
          <td><?= h($w['cruise_id']) ?></td>
          <td><?= h(CABINS[$w['cabin_code']] ?? $w['cabin_code']) ?></td>
          <td><?= h(money_value($w['target_price_per_person'])) ?></td>
          <td><?= h($w['email_to']) ?></td>
          <td><?= (int)$w['enabled'] === 1 ? '<span class="pill ok">Enabled</span>' : '<span class="pill sold">Disabled</span>' ?></td>
          <td><?= h($w['last_alert_price'] ? money_value($w['last_alert_price']) . ' at ' . $w['last_alert_at'] : '') ?></td>
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
<?php render_footer(); ?>
