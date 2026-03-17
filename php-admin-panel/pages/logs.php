<?php
require_once '../config.php';
requireLogin();
$page_title = 'Activity Logs';

$db = getDB();
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 30; $offset = ($page - 1) * $perPage;
$filter  = $_GET['filter'] ?? 'all';

// Redeem History
$redeems = []; $total_r = 0;
// Blacklist History
$bl_history = []; $total_bl = 0;

if ($db) {
    $total_r = $db->query("SELECT COUNT(*) FROM redeem_history")->fetchColumn();
    $stmt = $db->prepare("
        SELECT r.*, COALESCE(c.username, r.user_id) as username
        FROM redeem_history r LEFT JOIN coins c ON c.user_id=r.user_id
        ORDER BY r.redeemed_at DESC LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute();
    $redeems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_bl = $db->query("SELECT COUNT(*) FROM blacklist")->fetchColumn();
    $bl_history = $db->query("SELECT * FROM blacklist ORDER BY added_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

    // Stats
    $today_r = $db->query("SELECT COUNT(*) FROM redeem_history WHERE redeem_date=date('now')")->fetchColumn();
    $week_r  = $db->query("SELECT COUNT(*) FROM redeem_history WHERE redeem_date>=date('now','-7 days')")->fetchColumn();
}
$total_pages = max(1, ceil($total_r / $perPage));

include '../includes/header.php';
?>

<div class="page-header fade-in">
  <div>
    <div class="page-title">📋 Activity Logs</div>
    <div class="page-subtitle">Redemption history and blacklist audit log</div>
  </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
  <div class="stat-card"><div class="stat-header"><div class="stat-icon">🎁</div></div>
    <div class="stat-value"><?= $total_r ?></div><div class="stat-label">Total Redeems</div></div>
  <div class="stat-card gold"><div class="stat-header"><div class="stat-icon">📅</div></div>
    <div class="stat-value"><?= $today_r ?? 0 ?></div><div class="stat-label">Today</div></div>
  <div class="stat-card"><div class="stat-header"><div class="stat-icon">📆</div></div>
    <div class="stat-value"><?= $week_r ?? 0 ?></div><div class="stat-label">This Week</div></div>
  <div class="stat-card red"><div class="stat-header"><div class="stat-icon">🚫</div></div>
    <div class="stat-value"><?= $total_bl ?></div><div class="stat-label">Blacklist Events</div></div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;">
  <a href="?filter=redeems" class="btn <?= $filter!=='blacklist' ? 'btn-primary' : 'btn-ghost' ?>">🎁 Redeem Log</a>
  <a href="?filter=blacklist" class="btn <?= $filter==='blacklist' ? 'btn-primary' : 'btn-ghost' ?>">🚫 Blacklist Log</a>
</div>

<?php if ($filter === 'blacklist'): ?>
<!-- Blacklist Log -->
<div class="card">
  <div class="card-header"><span class="card-title">🚫 Blacklist Audit Log</span></div>
  <div class="table-wrap">
    <?php if (empty($bl_history)): ?>
      <div class="empty-state"><div class="empty-text">No blacklist entries</div></div>
    <?php else: ?>
      <table>
        <thead><tr><th>Member</th><th>Reason</th><th>Added By</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($bl_history as $b): ?>
          <tr>
            <td>
              <div class="td-user">
                <div class="td-avatar" style="background:linear-gradient(135deg,var(--red),#8b1c1e);">🚫</div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($b['username'] ?? 'Unknown') ?></div>
                  <div class="td-id"><?= $b['user_id'] ?></div>
                </div>
              </div>
            </td>
            <td style="color:var(--text2);font-size:13px;"><?= htmlspecialchars($b['reason'] ?? '—') ?></td>
            <td class="td-mono" style="font-size:12px;"><?= htmlspecialchars($b['added_by'] ?? '—') ?></td>
            <td class="td-mono" style="font-size:12px;color:var(--muted);"><?= $b['added_at'] ? timeAgo($b['added_at']) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- Redeem Log -->
<div class="card">
  <div class="card-header"><span class="card-title">🎁 Redemption Log</span><span class="badge badge-blue"><?= $total_r ?> entries</span></div>
  <div class="table-wrap">
    <?php if (empty($redeems)): ?>
      <div class="empty-state"><div class="empty-icon">🎁</div><div class="empty-text">No redemptions yet</div></div>
    <?php else: ?>
      <table>
        <thead><tr><th>Member</th><th>Key (masked)</th><th>Date</th><th>Time</th></tr></thead>
        <tbody>
          <?php foreach ($redeems as $r): ?>
          <tr>
            <td>
              <div class="td-user">
                <div class="td-avatar">🎁</div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($r['username']) ?></div>
                  <div class="td-id"><?= $r['user_id'] ?></div>
                </div>
              </div>
            </td>
            <td><span class="key-value"><?= substr($r['key_value'],0,10) ?>••••••</span></td>
            <td class="td-mono" style="font-size:12px;"><?= $r['redeem_date'] ?></td>
            <td class="td-mono" style="font-size:12px;color:var(--muted);"><?= timeAgo($r['redeemed_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <div class="card-footer-bar">
    <span>Page <?= $page ?> of <?= $total_pages ?></span>
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php for ($p = max(1,$page-2); $p <= min($total_pages,$page+2); $p++): ?>
        <a href="?page=<?= $p ?>&filter=redeems" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
