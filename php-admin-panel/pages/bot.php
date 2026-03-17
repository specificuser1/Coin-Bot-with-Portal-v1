<?php
require_once '../config.php';
requireLogin();
$page_title = 'Bot Control';

$db = getDB();
$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_pause' && $db) {
        $current = $db->query("SELECT value FROM bot_settings WHERE key='bot_paused'")->fetchColumn();
        $new_val = ($current === '1') ? '0' : '1';
        $db->prepare("INSERT OR REPLACE INTO bot_settings (key,value) VALUES ('bot_paused',?)")->execute([$new_val]);
        $message = $new_val === '1' ? 'Bot has been PAUSED.' : 'Bot has been RESUMED.';

    } elseif ($action === 'clear_vc_sessions' && $db) {
        $cnt = $db->query("SELECT COUNT(*) FROM vc_sessions")->fetchColumn();
        $db->exec("DELETE FROM vc_sessions");
        $message = "Cleared {$cnt} VC sessions.";

    } elseif ($action === 'reset_start_time' && $db) {
        $db->prepare("INSERT OR REPLACE INTO bot_settings (key,value) VALUES ('start_time',datetime('now'))")->execute();
        $message = 'Bot start time reset.';

    } elseif ($action === 'clear_redeem_history' && $db) {
        $db->exec("DELETE FROM redeem_history");
        $message = 'Redeem history cleared.';

    } elseif ($action === 'reset_daily' && $db) {
        $message = 'Daily limits reset (handled automatically by the bot at midnight).';
    }
}

// Fetch stats
$bot_paused   = '0';
$start_time   = null;
$vc_sessions  = [];
$key_avail    = 0;
$key_used     = 0;
$total_coins  = 0;
$total_members = 0;
$blacklisted  = 0;
$today_redeems = 0;

if ($db) {
    $bot_paused    = $db->query("SELECT value FROM bot_settings WHERE key='bot_paused'")->fetchColumn() ?: '0';
    $start_time    = $db->query("SELECT value FROM bot_settings WHERE key='start_time'")->fetchColumn();
    $vc_sessions   = $db->query("SELECT * FROM vc_sessions")->fetchAll(PDO::FETCH_ASSOC);
    $key_avail     = $db->query("SELECT COUNT(*) FROM keys WHERE is_used=0")->fetchColumn();
    $key_used      = $db->query("SELECT COUNT(*) FROM keys WHERE is_used=1")->fetchColumn();
    $total_coins   = $db->query("SELECT COALESCE(SUM(coins),0) FROM coins")->fetchColumn();
    $total_members = $db->query("SELECT COUNT(*) FROM coins")->fetchColumn();
    $blacklisted   = $db->query("SELECT COUNT(*) FROM blacklist")->fetchColumn();
    $today_redeems = $db->query("SELECT COUNT(*) FROM redeem_history WHERE redeem_date=date('now')")->fetchColumn();
}

$uptime = '—';
if ($start_time) {
    $diff = time() - strtotime($start_time);
    $d = floor($diff/86400); $h = floor(($diff%86400)/3600); $m = floor(($diff%3600)/60); $s = $diff%60;
    $uptime = ($d>0?"{$d}d ":"") . ($h>0?"{$h}h ":"") . "{$m}m {$s}s";
}

include '../includes/header.php';
?>

<div class="page-header fade-in">
  <div>
    <div class="page-title">🤖 Bot Control</div>
    <div class="page-subtitle">Control and monitor your Discord bot</div>
  </div>
  <div class="badge <?= $bot_paused==='1' ? 'badge-red' : 'badge-green' ?>" style="font-size:14px;padding:8px 18px;">
    <?= $bot_paused==='1' ? '⏸️ Bot PAUSED' : '🟢 Bot ACTIVE' ?>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>

<!-- BOT STATUS CARDS -->
<div class="stats-grid" style="margin-bottom:24px;">
  <div class="stat-card <?= $bot_paused==='1' ? 'red' : 'green' ?>">
    <div class="stat-header"><div class="stat-icon"><?= $bot_paused==='1' ? '⏸️' : '🟢' ?></div></div>
    <div class="stat-value" style="font-size:20px;"><?= $bot_paused==='1' ? 'PAUSED' : 'ACTIVE' ?></div>
    <div class="stat-label">Bot Status</div>
  </div>
  <div class="stat-card">
    <div class="stat-header"><div class="stat-icon">⏱️</div></div>
    <div class="stat-value" style="font-size:18px;"><?= $uptime ?></div>
    <div class="stat-label">Uptime</div>
  </div>
  <div class="stat-card">
    <div class="stat-header"><div class="stat-icon">🎙️</div></div>
    <div class="stat-value"><?= count($vc_sessions) ?></div>
    <div class="stat-label">Active VC Users</div>
  </div>
  <div class="stat-card gold">
    <div class="stat-header"><div class="stat-icon">📅</div></div>
    <div class="stat-value"><?= $today_redeems ?></div>
    <div class="stat-label">Redeems Today</div>
  </div>
</div>

<!-- CONTROL BUTTONS -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header"><span class="card-title">⚙️ Bot Controls</span></div>
  <div class="card-body">
    <div class="control-grid">

      <!-- Pause/Resume -->
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="toggle_pause">
        <button type="submit" class="control-btn <?= $bot_paused==='1' ? 'success' : 'danger' ?>" style="width:100%;"
                onclick="return confirm('<?= $bot_paused==='1' ? 'Resume the bot?' : 'Pause the bot?' ?>')">
          <span class="control-btn-icon"><?= $bot_paused==='1' ? '▶️' : '⏸️' ?></span>
          <span class="control-btn-label"><?= $bot_paused==='1' ? 'Resume Bot' : 'Pause Bot' ?></span>
          <span class="control-btn-desc"><?= $bot_paused==='1' ? 'Enable coin earning & keys' : 'Disable coin earning & keys' ?></span>
        </button>
      </form>

      <!-- Clear VC Sessions -->
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="clear_vc_sessions">
        <button type="submit" class="control-btn" style="width:100%;"
                onclick="return confirm('Clear all VC sessions?')">
          <span class="control-btn-icon">🔄</span>
          <span class="control-btn-label">Clear VC Sessions</span>
          <span class="control-btn-desc">Reset all active VC tracking</span>
        </button>
      </form>

      <!-- Reset Start Time -->
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="reset_start_time">
        <button type="submit" class="control-btn" style="width:100%;"
                onclick="return confirm('Reset uptime counter?')">
          <span class="control-btn-icon">⏰</span>
          <span class="control-btn-label">Reset Uptime</span>
          <span class="control-btn-desc">Reset bot start time counter</span>
        </button>
      </form>

      <!-- Clear Redeem History -->
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="clear_redeem_history">
        <button type="submit" class="control-btn danger" style="width:100%;"
                onclick="return confirm('Clear all redeem history? Daily limits will also reset.')">
          <span class="control-btn-icon">🗑️</span>
          <span class="control-btn-label">Clear Redeem History</span>
          <span class="control-btn-desc">Clears all redemption logs</span>
        </button>
      </form>

    </div>
  </div>
</div>

<!-- LIVE VC SESSIONS -->
<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <span class="card-title">🎙️ Active VC Sessions</span>
      <span class="badge badge-blue"><?= count($vc_sessions) ?> active</span>
    </div>
    <div class="table-wrap">
      <?php if (empty($vc_sessions)): ?>
        <div class="empty-state"><div class="empty-icon">🎙️</div><div class="empty-text">No active VC sessions</div></div>
      <?php else: ?>
        <table>
          <thead><tr><th>User ID</th><th>Since</th><th>Screen Share</th></tr></thead>
          <tbody>
            <?php foreach ($vc_sessions as $s): ?>
            <tr>
              <td class="td-mono" style="font-size:13px;"><?= $s['user_id'] ?></td>
              <td class="td-mono" style="font-size:12px;color:var(--muted);"><?= $s['join_time'] ? timeAgo($s['join_time']) : '—' ?></td>
              <td><?= $s['is_screen_sharing'] ? '<span class="badge badge-pink">🖥️ Sharing</span>' : '<span class="badge badge-gray">—</span>' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bot Summary -->
  <div class="card">
    <div class="card-header"><span class="card-title">📊 Bot Summary</span></div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:12px;">
        <?php
        $rows = [
          ['💰 Total Coins', formatCoins($total_coins), 'gold'],
          ['👥 Tracked Members', number_format($total_members), ''],
          ['🔑 Available Keys', $key_avail, 'green'],
          ['🔓 Used Keys', $key_used, ''],
          ['🚫 Blacklisted', $blacklisted, 'red'],
          ['📅 Today\'s Redeems', $today_redeems, ''],
        ];
        foreach ($rows as [$label, $val, $c]):
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);">
          <span style="font-size:13px;color:var(--text2);"><?= $label ?></span>
          <strong style="font-family:'DM Mono',monospace;<?= $c ? "color:var(--{$c});" : '' ?>"><?= $val ?></strong>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;">
          <span style="font-size:13px;color:var(--text2);">⏱️ Uptime</span>
          <strong style="font-family:'DM Mono',monospace;"><?= $uptime ?></strong>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Auto-refresh every 10s
setTimeout(() => location.reload(), 10000);
</script>

<?php include '../includes/footer.php'; ?>
