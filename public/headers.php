<?php
require_once __DIR__ . '/_init.php';
require_admin();

$currentHeaders = null;
if (is_file(headers_file_path())) {
    $currentHeaders = json_decode((string)file_get_contents(headers_file_path()), true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $mode = $_POST['mode'] ?? 'curl';
        if ($mode === 'json') {
            $headers = json_decode((string)($_POST['headers_json'] ?? ''), true);
            if (!is_array($headers)) {
                throw new RuntimeException('Invalid JSON headers.');
            }
        } else {
            $headers = parse_curl_headers((string)($_POST['curl'] ?? ''));
            if (!$headers) {
                throw new RuntimeException('No headers found. Use DevTools > Copy as cURL.');
            }
        }
        save_headers_from_json($headers);
        flash_set('Headers saved.', 'success');
        redirect_to('headers.php');
    } catch (Throwable $e) {
        flash_set($e->getMessage(), 'error');
        redirect_to('headers.php');
    }
}

render_header('Princess API headers');
?>
<section class="card">
  <h2>Paste DevTools cURL</h2>
  <p class="muted">In the browser Network tab, right-click the Princess pricing request, copy as cURL, then paste it here. The app extracts the useful headers and stores them privately.</p>
  <form method="post">
    <input type="hidden" name="mode" value="curl">
    <textarea name="curl" rows="12" placeholder="curl 'https://gw.api.princess.com/...' -H 'accept: application/json...' ..."></textarea>
    <button type="submit">Save headers from cURL</button>
  </form>
</section>
<section class="card">
  <h2>Or paste cleaned JSON headers</h2>
  <form method="post">
    <input type="hidden" name="mode" value="json">
    <textarea name="headers_json" rows="12"><?= h($currentHeaders ? json_encode($currentHeaders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '') ?></textarea>
    <button type="submit">Save JSON headers</button>
  </form>
</section>
<section class="card">
  <h2>Current status</h2>
  <p>Headers file: <code><?= h(headers_file_path()) ?></code></p>
  <p><?= is_file(headers_file_path()) ? 'Headers file exists.' : 'Headers file does not exist yet.' ?></p>
</section>
<?php render_footer(); ?>
