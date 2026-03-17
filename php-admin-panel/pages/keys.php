<?php
require_once '../config.php';
requireLogin();
$page_title = 'Key Manager';

$db = getDB();
$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_bulk' && !empty($_POST['keys_bulk'])) {
        $lines  = explode("\n", $_POST['keys_bulk']);
        $added  = 0; $dupes = 0;
        foreach ($lines as $line) {
            $key = trim($line);
            if (!$key) continue;
            try {
                $stmt = $db->prepare("INSERT OR IGNORE INTO keys (key_value,added_at,added_by) VALUES (?,datetime('now'),?)");
                $stmt->execute([$key, $_SESSION['admin_username']]);
                if ($stmt->rowCount()) $added++; else $dupes++;
            } catch(Exception $e) {}
        }
        $message = "Added {$added} keys" . ($dupes ? " ({$dupes} duplicates skipped)" : "") . ".";

    } elseif ($action === 'delete_key' && !empty($_POST['key_value'])) {
        $db->prepare("DELETE FROM keys WHERE key_value=?")->execute([$_POST['key_value']]);
        $message = 'Key deleted.';

    } elseif ($action === 'delete_used') {
        $cnt = $db->query("SELECT COUNT(*) FROM keys WHERE is_used=1")->fetchColumn();
        $db->exec("DELETE FROM keys WHERE is_used=1");
        $message = "Deleted {$cnt} used keys.";

    } elseif ($action === 'delete_all') {
        $db->exec("DELETE FROM keys");
        $message = 'All keys deleted.';
    }
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where_parts = [];
$params = [];
if ($filter === 'available') { $where_parts[] = "is_used=0"; }
if ($filter === 'used')      { $where_parts[] = "is_used=1"; }
if ($search) {
    $where_parts[] = "(key_value LIKE ? OR used_by LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$keys = []; $total_rows = 0;
if ($db) {
    $s = $db->prepare("SELECT COUNT(*) FROM keys $where");
    $s->execute($params); $total_rows = $s->fetchColumn();

    $s2 = $db->prepare("SELECT * FROM keys $where ORDER BY added_at DESC LIMIT $perPage OFFSET $offset");
    $s2->execute($params);
    $keys = $s2->fetchAll(PDO::FETCH_ASSOC);

    $stock_avail = $db->query("SELECT COUNT(*) FROM keys WHERE is_used=0")->fetchColumn();
    $stock_used  = $db->query("SELECT COUNT(*) FROM keys WHERE is_used=1")->fetchColumn();
}
$total_pages = max(1, ceil($total_rows / $perPage));

include '../includes/header.php';
?>

<div class="page-header fade-in">
  <div>
    <div class="page-title">🔑 Key Manager</div>
    <div class="page-subtitle">Manage your redemption keys stock</div>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
  <div class="stat-card green">
    <div class="stat-header"><div class="stat-icon">✅</div></div>
    <div class="stat-value"><?= $stock_avail ?? 0 ?></div>
    <div class="stat-label">Available Keys</div>
  </div>
  <div class="stat-card red">
    <div class="stat-header"><div class="stat-icon">🔓</div></div>
    <div class="stat-value"><?= $stock_used ?? 0 ?></div>
    <div class="stat-label">Redeemed Keys</div>
  </div>
  <div class="stat-card">
    <div class="stat-header"><div class="stat-icon">📦</div></div>
    <div class="stat-value"><?= ($stock_avail ?? 0) + ($stock_used ?? 0) ?></div>
    <div class="stat-label">Total Keys</div>
  </div>
</div>

<div class="grid-2">
  <!-- Add Keys -->
  <div class="card">
    <div class="card-header"><span class="card-title">➕ Add Keys (Bulk)</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add_bulk">
        <div class="form-group">
          <label class="form-label">Keys (one per line)</label>
          <textarea name="keys_bulk" class="form-textarea" style="min-height:180px;"
            placeholder="KEY-ABCD-1234&#10;KEY-EFGH-5678&#10;KEY-IJKL-9012"></textarea>
          <div class="form-hint">Duplicate keys will be automatically skipped.</div>
        </div>
        <button type="submit" class="btn btn-success" style="width:100%;">⬆️ Upload Keys</button>
      </form>
    </div>
  </div>

  <!-- Danger Zone -->
  <div class="card">
    <div class="card-header"><span class="card-title" style="color:var(--red);">⚠️ Danger Zone</span></div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--text2);margin-bottom:20px;">These actions are irreversible. Proceed with caution.</p>
      <form method="POST" style="margin-bottom:12px;">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="delete_used">
        <button type="submit" class="btn btn-warning" style="width:100%;"
                onclick="return confirm('Delete all <?= $stock_used ?? 0 ?> used keys?')">
          🗑️ Clear Used Keys (<?= $stock_used ?? 0 ?>)
        </button>
      </form>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="delete_all">
        <button type="submit" class="btn btn-danger" style="width:100%;"
                onclick="return confirm('DELETE ALL KEYS? This cannot be undone!')">
          💀 Delete ALL Keys
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Keys Table -->
<div class="card">
  <div class="card-header">
    <span class="card-title">🗂️ Key Stock</span>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <!-- Filter tabs -->
      <div style="display:flex;gap:4px;">
        <?php foreach (['all'=>'All','available'=>'Available','used'=>'Used'] as $f=>$label): ?>
          <a href="?filter=<?= $f ?>&q=<?= urlencode($search) ?>"
             class="btn btn-sm <?= $filter===$f ? 'btn-primary' : 'btn-ghost' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
      <form method="GET" style="display:flex;gap:8px;">
        <input type="hidden" name="filter" value="<?= $filter ?>">
        <input type="text" name="q" class="form-input" placeholder="Search keys..."
               value="<?= htmlspecialchars($search) ?>" style="width:200px;">
        <button type="submit" class="btn btn-ghost btn-sm">🔍</button>
      </form>
    </div>
  </div>
  <div class="table-wrap">
    <?php if (empty($keys)): ?>
      <div class="empty-state"><div class="empty-icon">🔑</div><div class="empty-text">No keys found</div></div>
    <?php else: ?>
      <table id="keyTable">
        <thead>
          <tr>
            <th>Key</th>
            <th>Status</th>
            <th>Added By</th>
            <th>Added At</th>
            <th>Used By</th>
            <th>Used At</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($keys as $k): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <span class="key-value"><?= htmlspecialchars(substr($k['key_value'],0,16)) ?><?= strlen($k['key_value'])>16 ? '…' : '' ?></span>
                <button class="btn btn-ghost btn-sm btn-icon" title="Copy key"
                        onclick="copyText('<?= htmlspecialchars($k['key_value']) ?>','Key copied!')">📋</button>
              </div>
            </td>
            <td>
              <?php if ($k['is_used']): ?>
                <span class="badge badge-red">🔓 Used</span>
              <?php else: ?>
                <span class="badge badge-green">✅ Available</span>
              <?php endif; ?>
            </td>
            <td class="td-mono" style="font-size:12px;"><?= htmlspecialchars($k['added_by'] ?? '—') ?></td>
            <td class="td-mono" style="font-size:12px;color:var(--muted);"><?= $k['added_at'] ? date('m/d H:i', strtotime($k['added_at'])) : '—' ?></td>
            <td class="td-mono" style="font-size:12px;"><?= $k['used_by'] ? htmlspecialchars($k['used_by']) : '—' ?></td>
            <td class="td-mono" style="font-size:12px;color:var(--muted);"><?= $k['used_at'] ? timeAgo($k['used_at']) : '—' ?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="delete_key">
                <input type="hidden" name="key_value" value="<?= htmlspecialchars($k['key_value']) ?>">
                <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Delete"
                        onclick="return confirm('Delete this key?')">🗑️</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <div class="card-footer-bar">
    <span>Showing <?= count($keys) ?> of <?= $total_rows ?></span>
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php for ($p = 1; $p <= min($total_pages, 10); $p++): ?>
        <a href="?page=<?= $p ?>&filter=<?= $filter ?>&q=<?= urlencode($search) ?>"
           class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
