<?php
require_once '../config.php';
requireLogin();
$page_title = 'Blacklist';

$db = getDB();
$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf'] ?? '')) {
    $action  = $_POST['action'] ?? '';
    $user_id = trim($_POST['user_id'] ?? '');

    if ($action === 'add' && $user_id) {
        $reason   = trim($_POST['reason'] ?? '') ?: 'No reason provided';
        $username = trim($_POST['username'] ?? '') ?: 'Unknown';
        $exists   = $db->prepare("SELECT 1 FROM blacklist WHERE user_id=?");
        $exists->execute([$user_id]);
        if ($exists->fetchColumn()) {
            $error = "User {$user_id} is already blacklisted.";
        } else {
            $db->prepare("INSERT INTO blacklist (user_id,username,reason,added_by,added_at) VALUES (?,?,?,?,datetime('now'))")
               ->execute([$user_id,$username,$reason,$_SESSION['admin_username']]);
            $message = "User {$user_id} ({$username}) added to blacklist.";
        }

    } elseif ($action === 'remove' && $user_id) {
        $db->prepare("DELETE FROM blacklist WHERE user_id=?")->execute([$user_id]);
        $message = "User {$user_id} removed from blacklist.";

    } elseif ($action === 'clear_all') {
        $db->exec("DELETE FROM blacklist");
        $message = 'Blacklist cleared.';
    }
}

$search  = trim($_GET['q'] ?? '');
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 25; $offset = ($page - 1) * $perPage;

$where  = $search ? "WHERE user_id LIKE ? OR username LIKE ? OR reason LIKE ?" : '';
$params = $search ? ["%$search%","%$search%","%$search%"] : [];

$list = []; $total_rows = 0;
if ($db) {
    $s = $db->prepare("SELECT COUNT(*) FROM blacklist $where");
    $s->execute($params); $total_rows = $s->fetchColumn();
    $s2 = $db->prepare("SELECT * FROM blacklist $where ORDER BY added_at DESC LIMIT $perPage OFFSET $offset");
    $s2->execute($params);
    $list = $s2->fetchAll(PDO::FETCH_ASSOC);
}
$total_pages = max(1, ceil($total_rows / $perPage));

include '../includes/header.php';
?>

<div class="page-header fade-in">
  <div>
    <div class="page-title">🚫 Blacklist Manager</div>
    <div class="page-subtitle">Manage blacklisted Discord members</div>
  </div>
  <div class="badge badge-red" style="font-size:13px;padding:8px 16px;"><?= $total_rows ?> Blacklisted</div>
</div>

<?php if ($message): ?><div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="grid-2">
  <!-- Add to Blacklist -->
  <div class="card">
    <div class="card-header"><span class="card-title">➕ Add to Blacklist</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
          <label class="form-label">Discord User ID *</label>
          <input type="text" name="user_id" class="form-input" placeholder="123456789012345678" required pattern="\d{17,20}">
        </div>
        <div class="form-group">
          <label class="form-label">Username (optional)</label>
          <input type="text" name="username" class="form-input" placeholder="username#0000">
        </div>
        <div class="form-group">
          <label class="form-label">Reason</label>
          <input type="text" name="reason" class="form-input" placeholder="Reason for blacklisting...">
        </div>
        <button type="submit" class="btn btn-danger" style="width:100%;">🚫 Add to Blacklist</button>
      </form>
    </div>
  </div>

  <!-- Remove / Stats -->
  <div class="card">
    <div class="card-header"><span class="card-title">🗑️ Remove from Blacklist</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="remove">
        <div class="form-group">
          <label class="form-label">Discord User ID</label>
          <input type="text" name="user_id" class="form-input" placeholder="123456789012345678" required pattern="\d{17,20}">
        </div>
        <button type="submit" class="btn btn-success" style="width:100%;margin-bottom:16px;">✅ Remove from Blacklist</button>
      </form>
      <div class="divider"></div>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="clear_all">
        <button type="submit" class="btn btn-warning" style="width:100%;"
                onclick="return confirm('Clear the ENTIRE blacklist?')">
          💥 Clear All Blacklist
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Blacklist Table -->
<div class="card">
  <div class="card-header">
    <span class="card-title">📋 Blacklisted Members</span>
    <form method="GET" style="display:flex;gap:8px;">
      <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" name="q" class="form-input" placeholder="Search blacklist..."
               value="<?= htmlspecialchars($search) ?>" style="width:220px;">
      </div>
      <button type="submit" class="btn btn-ghost btn-sm">Search</button>
    </form>
  </div>
  <div class="table-wrap">
    <?php if (empty($list)): ?>
      <div class="empty-state"><div class="empty-icon">🎉</div><div class="empty-text">Blacklist is empty!</div></div>
    <?php else: ?>
      <table id="blTable">
        <thead>
          <tr>
            <th>Member</th>
            <th>Reason</th>
            <th>Added By</th>
            <th>Added At</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($list as $bl): ?>
          <tr>
            <td>
              <div class="td-user">
                <div class="td-avatar" style="background:linear-gradient(135deg,var(--red),#8b1c1e);">🚫</div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($bl['username'] ?? 'Unknown') ?></div>
                  <div class="td-id" onclick="copyText('<?= $bl['user_id'] ?>','ID copied!')" style="cursor:pointer;"><?= $bl['user_id'] ?> 📋</div>
                </div>
              </div>
            </td>
            <td style="max-width:220px;">
              <span style="font-size:13px;color:var(--text2);"><?= htmlspecialchars($bl['reason'] ?? '—') ?></span>
            </td>
            <td class="td-mono" style="font-size:12px;"><?= htmlspecialchars($bl['added_by'] ?? '—') ?></td>
            <td class="td-mono" style="font-size:12px;color:var(--muted);"><?= $bl['added_at'] ? timeAgo($bl['added_at']) : '—' ?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="user_id" value="<?= $bl['user_id'] ?>">
                <button type="submit" class="btn btn-success btn-sm">✅ Unban</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <div class="card-footer-bar">
    <span>Showing <?= count($list) ?> of <?= $total_rows ?></span>
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>"
           class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
