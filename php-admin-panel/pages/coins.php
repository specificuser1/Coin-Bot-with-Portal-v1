<?php
require_once '../config.php';
requireLogin();
$page_title = 'Coin Manager';

$db = getDB();
$message = '';
$error   = '';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf']) && verifyCsrf($_POST['csrf'])) {
    $action  = $_POST['action'] ?? '';
    $user_id = trim($_POST['user_id'] ?? '');
    $amount  = floatval($_POST['amount'] ?? 0);

    if (!$db) { $error = 'Database not connected.'; }
    elseif (empty($user_id)) { $error = 'User ID is required.'; }
    else {
        try {
            if ($action === 'add') {
                $db->prepare("INSERT INTO coins (user_id,username,coins,total_earned,last_updated) VALUES (?,?,?,?,datetime('now')) ON CONFLICT(user_id) DO UPDATE SET coins=coins+?,total_earned=total_earned+?,last_updated=datetime('now')")
                   ->execute([$user_id,'Unknown',$amount,$amount,$amount,$amount]);
                $message = "Added {$amount} coins to user {$user_id}.";

            } elseif ($action === 'remove') {
                $row = $db->prepare("SELECT coins FROM coins WHERE user_id=?");
                $row->execute([$user_id]);
                $cur = $row->fetchColumn();
                if ($cur === false) { $error = 'User not found.'; }
                elseif ($cur < $amount) { $error = "User only has {$cur} coins."; }
                else {
                    $db->prepare("UPDATE coins SET coins=coins-?,last_updated=datetime('now') WHERE user_id=?")->execute([$amount,$user_id]);
                    $message = "Removed {$amount} coins from user {$user_id}.";
                }

            } elseif ($action === 'set') {
                $db->prepare("INSERT INTO coins (user_id,username,coins,total_earned,last_updated) VALUES (?,?,?,?,datetime('now')) ON CONFLICT(user_id) DO UPDATE SET coins=?,last_updated=datetime('now')")
                   ->execute([$user_id,'Unknown',$amount,$amount,$amount]);
                $message = "Set coins for user {$user_id} to {$amount}.";

            } elseif ($action === 'reset') {
                $db->prepare("UPDATE coins SET coins=0,last_updated=datetime('now') WHERE user_id=?")->execute([$user_id]);
                $message = "Reset coins for user {$user_id} to 0.";

            } elseif ($action === 'delete') {
                $db->prepare("DELETE FROM coins WHERE user_id=?")->execute([$user_id]);
                $message = "Deleted coin record for user {$user_id}.";
            }
        } catch (Exception $e) { $error = 'DB error: ' . $e->getMessage(); }
    }
}

// Fetch coins data
$search  = trim($_GET['q'] ?? '');
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$members = []; $total_rows = 0;
if ($db) {
    $where = $search ? "WHERE user_id LIKE ? OR username LIKE ?" : "";
    $params = $search ? ["%$search%", "%$search%"] : [];

    $total_rows = $db->prepare("SELECT COUNT(*) FROM coins $where")->execute($params) ? 0 : 0;
    $stmt = $db->prepare("SELECT COUNT(*) FROM coins $where");
    $stmt->execute($params);
    $total_rows = $stmt->fetchColumn();

    $stmt2 = $db->prepare("SELECT user_id, username, coins, total_earned, last_updated FROM coins $where ORDER BY coins DESC LIMIT $perPage OFFSET $offset");
    $stmt2->execute($params);
    $members = $stmt2->fetchAll(PDO::FETCH_ASSOC);
}
$total_pages = max(1, ceil($total_rows / $perPage));

include '../includes/header.php';
?>

<div class="page-header fade-in">
  <div>
    <div class="page-title">💰 Coin Manager</div>
    <div class="page-subtitle">View and manage member coin balances</div>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="grid-2">
  <!-- Coin Action Form -->
  <div class="card">
    <div class="card-header"><span class="card-title">⚙️ Modify Coins</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <div class="form-group">
          <label class="form-label">Discord User ID</label>
          <input type="text" name="user_id" class="form-input" placeholder="e.g. 123456789012345678" required
                 pattern="\d{17,20}" title="Enter a valid Discord User ID">
        </div>
        <div class="form-group">
          <label class="form-label">Amount (for add/remove/set)</label>
          <input type="number" name="amount" class="form-input" placeholder="0" min="0" step="0.1">
        </div>
        <div class="form-group">
          <label class="form-label">Action</label>
          <select name="action" class="form-select">
            <option value="add">➕ Add Coins</option>
            <option value="remove">➖ Remove Coins</option>
            <option value="set">⚙️ Set Coins</option>
            <option value="reset">🔄 Reset to 0</option>
            <option value="delete">🗑️ Delete Record</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Execute Action</button>
      </form>
    </div>
  </div>

  <!-- Quick Stats -->
  <div class="card">
    <div class="card-header"><span class="card-title">📊 Quick Stats</span></div>
    <div class="card-body">
      <?php if ($db):
        $total_c = $db->query("SELECT COALESCE(SUM(coins),0) FROM coins")->fetchColumn();
        $total_e = $db->query("SELECT COALESCE(SUM(total_earned),0) FROM coins")->fetchColumn();
        $avg_c   = $db->query("SELECT COALESCE(AVG(coins),0) FROM coins")->fetchColumn();
        $max_c   = $db->query("SELECT COALESCE(MAX(coins),0) FROM coins")->fetchColumn();
      ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          <div style="background:var(--surface2);border-radius:10px;padding:14px;border:1px solid var(--border);">
            <div style="font-size:11px;color:var(--muted);margin-bottom:4px;">TOTAL COINS</div>
            <div style="font-size:20px;font-weight:800;color:var(--gold);"><?= formatCoins($total_c) ?></div>
          </div>
          <div style="background:var(--surface2);border-radius:10px;padding:14px;border:1px solid var(--border);">
            <div style="font-size:11px;color:var(--muted);margin-bottom:4px;">TOTAL EARNED</div>
            <div style="font-size:20px;font-weight:800;color:var(--green);"><?= formatCoins($total_e) ?></div>
          </div>
          <div style="background:var(--surface2);border-radius:10px;padding:14px;border:1px solid var(--border);">
            <div style="font-size:11px;color:var(--muted);margin-bottom:4px;">AVERAGE</div>
            <div style="font-size:20px;font-weight:800;"><?= formatCoins($avg_c) ?></div>
          </div>
          <div style="background:var(--surface2);border-radius:10px;padding:14px;border:1px solid var(--border);">
            <div style="font-size:11px;color:var(--muted);margin-bottom:4px;">HIGHEST</div>
            <div style="font-size:20px;font-weight:800;color:var(--accent);"><?= formatCoins($max_c) ?></div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Members Table -->
<div class="card">
  <div class="card-header">
    <span class="card-title">👥 Member Balances</span>
    <div style="display:flex;gap:10px;align-items:center;">
      <form method="GET" style="display:flex;gap:8px;">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" class="form-input" placeholder="Search members..."
                 value="<?= htmlspecialchars($search) ?>" style="width:220px;">
        </div>
        <button type="submit" class="btn btn-ghost btn-sm">Search</button>
      </form>
      <span class="badge badge-gray"><?= $total_rows ?> total</span>
    </div>
  </div>
  <div class="table-wrap">
    <?php if (empty($members)): ?>
      <div class="empty-state"><div class="empty-icon">💰</div><div class="empty-text">No members found</div></div>
    <?php else: ?>
      <table id="coinTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Member</th>
            <th>Coins</th>
            <th>Total Earned</th>
            <th>Last Updated</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $i => $m): ?>
          <tr>
            <td class="td-mono" style="color:var(--muted);"><?= $offset + $i + 1 ?></td>
            <td>
              <div class="td-user">
                <div class="td-avatar">👤</div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($m['username'] ?? 'Unknown') ?></div>
                  <div class="td-id" onclick="copyText('<?= $m['user_id'] ?>','ID copied!')" style="cursor:pointer;"><?= $m['user_id'] ?> 📋</div>
                </div>
              </div>
            </td>
            <td><span class="coin-value" style="font-size:16px;font-weight:700;"><?= formatCoins($m['coins']) ?></span></td>
            <td><span class="badge badge-green"><?= formatCoins($m['total_earned']) ?></span></td>
            <td class="td-mono" style="font-size:12px;color:var(--muted);"><?= $m['last_updated'] ? timeAgo($m['last_updated']) : '—' ?></td>
            <td>
              <div style="display:flex;gap:6px;">
                <button class="btn btn-ghost btn-sm btn-icon" title="Reset coins"
                        onclick="quickReset('<?= $m['user_id'] ?>')">🔄</button>
                <button class="btn btn-danger btn-sm btn-icon" title="Delete record"
                        onclick="quickDelete('<?= $m['user_id'] ?>', '<?= htmlspecialchars($m['username'] ?? $m['user_id']) ?>')">🗑️</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <div class="card-footer-bar">
    <span>Showing <?= count($members) ?> of <?= $total_rows ?> members</span>
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>"
           class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function quickReset(uid) {
  openModal('🔄 Reset Coins', `Reset all coins for user <code>${uid}</code> to 0?`, 'Reset', true, () => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
      <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="reset">
      <input type="hidden" name="user_id" value="${uid}">
      <input type="hidden" name="amount" value="0">
    `;
    document.body.appendChild(form);
    form.submit();
  });
}
function quickDelete(uid, name) {
  confirmDelete(name, () => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
      <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="user_id" value="${uid}">
      <input type="hidden" name="amount" value="0">
    `;
    document.body.appendChild(form);
    form.submit();
  });
}
</script>

<?php include '../includes/footer.php'; ?>
