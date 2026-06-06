<?php
require_once __DIR__ . '/_init.php';
require_login();

render_header('Check cruise price');
?>
<section class="card">
  <form method="post" action="check.php" class="grid-form">
    <div>
      <label>Cruise code</label>
      <input name="cruise_id" value="<?= h(env_value('DEFAULT_CRUISE_ID', 'H630')) ?>" required>
    </div>
    <div>
      <label>Guest country</label>
      <input name="guest_country" value="<?= h(env_value('DEFAULT_GUEST_COUNTRY', 'US')) ?>" required>
    </div>
    <div>
      <label>Guest count</label>
      <input type="number" min="1" max="5" name="guest_count" value="<?= h(env_value('DEFAULT_GUEST_COUNT', '2')) ?>" required>
    </div>
    <?php if (is_admin()): ?>
    <div>
      <label>Currency code</label>
      <input name="currency_code" value="<?= h(env_value('DEFAULT_CURRENCY_CODE', 'USD')) ?>" required>
    </div>
    <div>
      <label>Guest home city</label>
      <input name="guest_home_city" value="<?= h(env_value('DEFAULT_GUEST_HOME_CITY', 'LAX')) ?>" required>
    </div>
    <?php else: ?>
      <input type="hidden" name="currency_code" value="<?= h(env_value('DEFAULT_CURRENCY_CODE', 'USD')) ?>">
      <input type="hidden" name="guest_home_city" value="<?= h(env_value('DEFAULT_GUEST_HOME_CITY', 'LAX')) ?>">
    <?php endif; ?>
    <div class="full-row">
      <button type="submit">Check price now</button>
    </div>
  </form>
</section>
<?php if (is_admin()): ?>
<section class="card">
  <h2>Operational notes</h2>
  <ul>
    <li>If the API starts returning 401, 403, or HTML, refresh the Princess web session and paste a new cURL request on the Headers page.</li>
    <li>Displayed currency comes from the API response, not the requested currency field.</li>
    <li>The graph uses fare per person. Taxes and fees are stored separately when returned.</li>
  </ul>
</section>
<?php endif; ?>
<?php render_footer(); ?>
