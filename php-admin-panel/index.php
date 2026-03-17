<?php
require_once 'config.php';
requireLogin();
$page_title = 'Dashboard';

$db = getDB();
$stats = [];

if ($db) {
    $stats['total_coins']   = $db->query("SELECT COALESCE(SUM(coins),0) FROM coins")->fetchColumn();
    $stats['total_members'] = $db->query("SELECT COUNT(*) FROM coins")->fetchColumn();
    $stats['keys_avail']    = $db->query("SELECT COUNT(*) FROM keys WHERE is_used=0")->fetchColumn();
    $stats['keys_used']     = $db->query("SELECT COUNT(*) FROM keys WHERE is_used=1")->fetchColumn();
    $stats['blacklisted']   = $db->query("SELECT COUNT(*) FROM blacklist")->fetchColumn();
    $stats['today_redeems'] = $db->query("SELECT COUNT(*) FROM redeem_history WHERE redeem_date=date('now')")->fetchColumn();
    $stats['vc_active']     = $db->query("SELECT COUNT(*) FROM vc_sessions")->fetchColumn();
    $stats['bot_paused']    = $db->query("SELECT value FROM bot_settings WHERE key='bot_paused'")->fetchColumn();
    $stats['start_time']    = $db->query("SELECT value FROM bot_settings WHERE key='start_time'")->fetchColumn();

    // Recent redeems
    $recent_redeems = $db->query("
        SELECT r.user_id, r.key_value, r.redeemed_at,
               COALESCE(c.username, r.user_id) as username
        FROM redeem_history r
        LEFT JOIN coins c ON c.user_id = r.user_id
        ORDER BY r.redeemed_at DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Top earners
    $top_earners = $db->query("
        SELECT user_id, username, coins, total_earned
        FROM coins ORDER BY coins DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Redeem counts per day (last 7 days)
    $daily_redeems = $db->query("
        SELECT redeem_date, COUNT(*) as count
        FROM redeem_history
        WHERE redeem_date >= date('now','-7 days')
        GROUP BY redeem_date ORDER BY redeem_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $recent_redeems = []; $top_earners = []; $daily_redeems = [];
    $stats = array_fill_keys(['total_coins','total_members','keys_avail','keys_used','blacklisted','today_redeems','vc_active','bot_paused','start_time'], 0);
}

// Uptime
$uptime = '—';
if (!empty($stats['start_time'])) {
    $start = strtotime($stats['start_time']);
    $diff  = time() - $start;
    $d = floor($diff/86400); $h = floor(($diff%86400)/3600); $m = floor(($diff%3600)/60);
    $uptime = ($d > 0 ? "{$d}d " : '') . ($h > 0 ? "{$h}h " : '') . "{$m}m";
}

// Chart data
$chartLabels = array_column($daily_redeems, 'redeem_date');
$chartValues = array_column($daily_redeems, 'count');

include 'includes/header.php';
?>

<div class="page-header fade-in">
  <div class="page-title-wrap">
    <div class="page-title">📊 Dashboard</div>
    <div class="page-subtitle">Overview of your VC Coin Earner bot</div>
  </div>
  <div style="display:flex;gap:10px;align-items:center;">
    <div class="badge <?= $stats['bot_paused'] ? 'badge-red' : 'badge-green' ?>" style="font-size:13px;padding:6px 14px;">
      <?= $stats['bot_paused'] ? '⏸️ Bot Paused' : '🟢 Bot Active' ?>
    </div>
    <a href="/pages/bot.php" class="btn btn-primary btn-sm">⚙️ Bot Control</a>
  </div>
</div>

<!-- STAT CARDS -->
<div class="stats-grid">
  <div class="stat-card gold">
    <div class="stat-header">
      <div class="stat-icon">💰</div>
      <span class="stat-change neu">All Time</span>
    </div>
    <div class="stat-value"><?= formatCoins($stats['total_coins']) ?></div>
    <div class="stat-label">Total Coins in Circulation</div>
  </div>

  <div class="stat-card">
    <div class="stat-header">
      <div class="stat-icon">👥</div>
      <span class="stat-change neu">Members</span>
    </div>
    <div class="stat-value"><?= number_format($stats['total_members']) ?></div>
    <div class="stat-label">Tracked Members</div>
  </div>

  <div class="stat-card green">
    <div class="stat-header">
      <div class="stat-icon">🔑</div>
      <span class="stat-change up">+<?= $stats['keys_avail'] ?> avail</span>
    </div>
    <div class="stat-value"><?= number_format($stats['keys_used']) ?></div>
    <div class="stat-label">Keys Redeemed</div>
  </div>

  <div class="stat-card pink">
    <div class="stat-header">
      <div class="stat-icon">📅</div>
      <span class="stat-change neu">Today</span>
    </div>
    <div class="stat-value"><?= $stats['today_redeems'] ?></div>
    <div class="stat-label">Redeems Today</div>
  </div>

  <div class="stat-card red">
    <div class="stat-header">
      <div class="stat-icon">🚫</div>
      <span class="stat-change neu">Total</span>
    </div>
    <div class="stat-value"><?= $stats['blacklisted'] ?></div>
    <div class="stat-label">Blacklisted Members</div>
  </div>

  <div class="stat-card">
    <div class="stat-header">
      <div class="stat-icon">🎙️</div>
      <span class="stat-change up">Live</span>
    </div>
    <div class="stat-value"><?= $stats['vc_active'] ?></div>
    <div class="stat-label">Active in VC Now</div>
  </div>

  <div class="stat-card">
    <div class="stat-header">
      <div class="stat-icon">📦</div>
      <span class="stat-change <?= $stats['keys_avail'] > 10 ? 'up' : 'down' ?>">
        <?= $stats['keys_avail'] > 0 ? 'In Stock' : 'Empty' ?>
      </span>
    </div>
    <div class="stat-value"><?= $stats['keys_avail'] ?></div>
    <div class="stat-label">Keys Available</div>
  </div>

  <div class="stat-card">
    <div class="stat-header">
      <div class="stat-icon">⏱️</div>
      <span class="stat-change neu">Since Start</span>
    </div>
    <div class="stat-value" style="font-size:22px;"><?= $uptime ?></div>
    <div class="stat-label">Bot Uptime</div>
  </div>
</div>

<!-- KEY STOCK PROGRESS -->
<?php
$total_keys = $stats['keys_avail'] + $stats['keys_used'];
$used_pct   = $total_keys > 0 ? round(($stats['keys_used'] / $total_keys) * 100) : 0;
?>
<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <span class="card-title">🔑 Key Stock Overview</span>
    <a href="/pages/keys.php" class="btn btn-ghost btn-sm">Manage Keys →</a>
  </div>
  <div class="card-body">
    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
      <span style="font-size:13px;color:var(--text2);">Used: <strong style="color:var(--text);"><?= $stats['keys_used'] ?></strong></span>
      <span style="font-family:'DM Mono',monospace;font-size:13px;color:var(--muted);"><?= $used_pct ?>% used</span>
      <span style="font-size:13px;color:var(--text2);">Available: <strong style="color:var(--green);"><?= $stats['keys_avail'] ?></strong></span>
    </div>
    <div class="progress-bar" style="height:10px;">
      <div class="progress-fill" style="width:<?= $used_pct ?>%;"></div>
    </div>
    <div style="margin-top:8px;font-size:12px;color:var(--muted);">Total keys: <?= $total_keys ?></div>
  </div>
</div>

<!-- CHARTS + TABLES ROW -->
<div class="grid-2" style="margin-bottom:24px;">

  <!-- Redeem Chart -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📈 Daily Redeems (7 days)</span>
    </div>
    <div class="card-body">
      <canvas id="redeemChart" width="400" height="160" style="width:100%;"></canvas>
      <?php if (empty($daily_redeems)): ?>
        <div class="empty-state" style="padding:24px 0;">
          <div class="empty-text">No redeem data yet</div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Top Earners -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🏆 Top Earners</span>
      <a href="/pages/coins.php" class="btn btn-ghost btn-sm">See All →</a>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($top_earners)): ?>
        <div class="empty-state"><div class="empty-text">No data yet</div></div>
      <?php else: ?>
        <table style="width:100%;">
          <thead>
            <tr>
              <th style="padding:12px 16px;">#</th>
              <th style="padding:12px 16px;">Member</th>
              <th style="padding:12px 16px;text-align:right;">Coins</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($top_earners as $i => $m): ?>
            <tr>
              <td style="padding:12px 16px;">
                <?php $medals = ['🥇','🥈','🥉','4️⃣','5️⃣']; echo $medals[$i] ?? ($i+1); ?>
              </td>
              <td style="padding:12px 16px;">
                <div class="td-user">
                  <div class="td-avatar">👤</div>
                  <div>
                    <div class="td-name"><?= htmlspecialchars($m['username'] ?? 'Unknown') ?></div>
                    <div class="td-id"><?= $m['user_id'] ?></div>
                  </div>
                </div>
              </td>
              <td style="padding:12px 16px;text-align:right;" class="coin-value">
                <?= formatCoins($m['coins']) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent Redeems -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <span class="card-title">🎁 Recent Redemptions</span>
    <a href="/pages/keys.php" class="btn btn-ghost btn-sm">View All →</a>
  </div>
  <div class="table-wrap">
    <?php if (empty($recent_redeems)): ?>
      <div class="empty-state"><div class="empty-text">No redemptions yet</div></div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <th>Key (masked)</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent_redeems as $r): ?>
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
            <td><span class="key-value"><?= substr($r['key_value'],0,8) ?>••••</span></td>
            <td><span style="font-family:'DM Mono',monospace;font-size:12px;color:var(--muted);"><?= timeAgo($r['redeemed_at']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <div class="card-footer-bar">
    <span>Programmed by <strong>SUBHAN</strong></span>
    <span>Last updated: <?= date('H:i:s') ?> UTC</span>
  </div>
</div>

<script>
// Draw redeem chart
const labels = <?= json_encode($chartLabels) ?>;
const values = <?= json_encode(array_map('intval', $chartValues)) ?>;

window.addEventListener('load', () => {
  const canvas = document.getElementById('redeemChart');
  if (!canvas || !values.length) return;
  canvas.width  = canvas.offsetWidth;
  canvas.height = 160;
  drawBarChart('redeemChart', labels, values, '#5865f2');
});

// Auto refresh stats every 30s
setTimeout(() => location.reload(), 30000);
</script>

<?php include 'includes/footer.php'; ?>
