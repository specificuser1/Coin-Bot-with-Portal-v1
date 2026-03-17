<?php
require_once '../config.php';
requireLogin();
$page_title = 'Members';

$db = getDB();
$search  = trim($_GET['q'] ?? '');
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 25; $offset = ($page - 1) * $perPage;

$where  = $search ? "WHERE c.user_id LIKE ? OR c.username LIKE ?" : '';
$params = $search ? ["%$search%","%$search%"] : [];

$members = []; $total_rows = 0;
if ($db) {
    $s = $db->prepare("SELECT COUNT(*) FROM coins c $where");
    $s->execute($params); $total_rows = $s->fetchColumn();

    $s2 = $db->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM redeem_history r WHERE r.user_id=c.user_id) as total_redeems,
               (SELECT COUNT(*) FROM redeem_history r WHERE r.user_id=c.user_id AND r.redeem_date=date('now')) as today_redeems,
               (SELECT COUNT(*) FROM blacklist b WHERE b.user_id=c.user_id) as is_blacklisted,
               (SELECT v.is_screen_sharing FROM vc_sessions v WHERE v.user_id=c.user_id LIMIT 1) as in_vc
        FROM coins c $where
        ORDER BY c.coins DESC LIMIT $perPage OFFSET $offset
    ");
    $s2->execute($params);
    $members = $s2->fetchAll(PDO::FETCH_ASSOC);
}
$total_pages = max(1, ceil($total_rows / $perPage));

include '../includes/header.php';
?>
<div class="page-header fade-in">
  <div>
    <div class="page-title">👥 Members</div>
    <div class="page-subtitle">All tracked members and their stats</div>
  </div>
  <div class="badge badge-blue" style="font-size:13px;padding:8px 16px;"><?= $total_rows ?> Members</div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">👥 Member List</span>
    <form method="GET" style="display:flex;gap:8px;">
      <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" name="q" class="form-input" placeholder="Search by ID or username..."
               value="<?= htmlspecialchars($search) ?>" style="width:260px;">
      </div>
      <button type="submit" class="btn btn-ghost btn-sm">Search</button>
      <?php if ($search): ?>
        <a href="?" class="btn btn-ghost btn-sm">✕ Clear</a>
      <?php endif; ?>
    </form>
  </div>
  <div class="table-wrap">
    <?php if (empty($members)): ?>
      <div class="empty-state"><div class="empty-icon">👥</div><div class="empty-text">No members found</div></div>
    <?php else: ?>
      <table id="memberTable">
        <thead>
          <tr>
            <th>Rank</th><th>Member</th><th>Coins</th>
            <th>Total Earned</th><th>Redeems</th><th>Status</th><th>Last Active</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $i => $m): ?>
          <tr>
            <td class="td-mono" style="color:var(--muted);"><?= $offset+$i+1 ?></td>
            <td>
              <div class="td-user">
                <div class="td-avatar" style="background:<?= $m['is_blacklisted'] ? 'linear-gradient(135deg,var(--red),#8b1c1e)' : 'linear-gradient(135deg,var(--accent),var(--accent2))' ?>;">
                  <?= $m['is_blacklisted'] ? '🚫' : '👤' ?>
                </div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($m['username'] ?? 'Unknown') ?></div>
                  <div class="td-id" onclick="copyText('<?= $m['user_id'] ?>','Copied!')" style="cursor:pointer;"><?= $m['user_id'] ?> 📋</div>
                </div>
              </div>
            </td>
            <td><span class="coin-value" style="font-weight:700;"><?= formatCoins($m['coins']) ?></span></td>
            <td><span class="badge badge-green"><?= formatCoins($m['total_earned']) ?></span></td>
            <td>
              <span class="badge badge-blue"><?= $m['total_redeems'] ?> total</span>
              <?php if ($m['today_redeems'] > 0): ?>
                <span class="badge badge-gold" style="margin-left:4px;"><?= $m['today_redeems'] ?> today</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($m['is_blacklisted']): ?>
                <span class="badge badge-red">🚫 Banned</span>
              <?php elseif ($m['in_vc'] !== null): ?>
                <span class="badge badge-green">🎙️ In VC</span>
              <?php else: ?>
                <span class="badge badge-gray">Offline</span>
              <?php endif; ?>
            </td>
            <td class="td-mono" style="font-size:12px;color:var(--muted);">
              <?= $m['last_updated'] ? timeAgo($m['last_updated']) : '—' ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <div class="card-footer-bar">
    <span>Showing <?= count($members) ?> of <?= $total_rows ?></span>
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php for ($p = max(1,$page-3); $p <= min($total_pages,$page+3); $p++): ?>
        <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>"
           class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
