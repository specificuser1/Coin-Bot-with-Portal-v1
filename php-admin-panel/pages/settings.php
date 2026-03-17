<?php
require_once '../config.php';
requireLogin();
$page_title = 'Settings';

$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new_pw   = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, ADMIN_PASSWORD)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_pw) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new_pw !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            // Write new hashed password to config
            $hash = password_hash($new_pw, PASSWORD_BCRYPT);
            $config_content = file_get_contents('../config.php');
            $config_content = preg_replace(
                "/define\('ADMIN_PASSWORD',\s*password_hash\([^)]+\)\);/",
                "define('ADMIN_PASSWORD', '$hash'); // Changed: " . date('Y-m-d'),
                $config_content
            );
            file_put_contents('../config.php', $config_content);
            $message = 'Password changed successfully. Please log in again.';
            session_destroy();
            header('Location: /login.php');
            exit;
        }
    }
}

// Load config.json
$bot_config = [];
$config_json_path = '../../vc-coin-bot/config.json';
if (file_exists($config_json_path)) {
    $bot_config = json_decode(file_get_contents($config_json_path), true) ?? [];
}

// Handle bot config save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf'] ?? '') && ($_POST['action'] ?? '') === 'save_bot_config') {
    if ($bot_config) {
        $bot_config['prefix'] = trim($_POST['prefix'] ?? '!') ?: '!';
        $bot_config['coins']['per_minute_vc'] = floatval($_POST['per_minute_vc'] ?? 1.0);
        $bot_config['coins']['screen_share_multiplier'] = floatval($_POST['screen_share_multiplier'] ?? 1.5);
        $bot_config['coins']['coins_per_key'] = intval($_POST['coins_per_key'] ?? 90);
        $bot_config['coins']['daily_redeem_limit'] = intval($_POST['daily_redeem_limit'] ?? 2);
        $bot_config['account_age']['minimum_weeks'] = intval($_POST['minimum_weeks'] ?? 4);
        $bot_config['anti_spam']['max_commands_per_minute'] = intval($_POST['max_commands_per_minute'] ?? 5);
        $bot_config['channels']['log_channel_id'] = trim($_POST['log_channel_id'] ?? '');
        $bot_config['channels']['public_panel_channel_id'] = trim($_POST['public_panel_channel_id'] ?? '');
        $bot_config['channels']['admin_panel_channel_id'] = trim($_POST['admin_panel_channel_id'] ?? '');

        file_put_contents($config_json_path, json_encode($bot_config, JSON_PRETTY_PRINT));
        $message = 'Bot config saved! Restart the bot to apply changes.';
    } else {
        $error = 'Bot config.json not found. Check the path in config.php.';
    }
}

include '../includes/header.php';
?>

<div class="page-header fade-in">
  <div>
    <div class="page-title">⚙️ Settings</div>
    <div class="page-subtitle">Configure the admin panel and bot settings</div>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="grid-2">
  <!-- Change Password -->
  <div class="card">
    <div class="card-header"><span class="card-title">🔒 Change Admin Password</span></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group">
          <label class="form-label">Current Password</label>
          <input type="password" name="current_password" class="form-input" placeholder="••••••••" required>
        </div>
        <div class="form-group">
          <label class="form-label">New Password</label>
          <input type="password" name="new_password" class="form-input" placeholder="Min. 8 characters" minlength="8" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-input" placeholder="Repeat new password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">🔐 Update Password</button>
      </form>
    </div>
  </div>

  <!-- Session Info -->
  <div class="card">
    <div class="card-header"><span class="card-title">🖥️ Session Info</span></div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:12px;">
        <?php $rows = [
          ['👤 Admin', $_SESSION['admin_username'] ?? 'Unknown'],
          ['🕐 Login Time', $_SESSION['login_time'] ?? '—'],
          ['⏱️ Session Expires', date('H:i:s', ($_SESSION['last_activity'] ?? time()) + SESSION_LIFETIME).' UTC'],
          ['🌐 IP Address', $_SERVER['REMOTE_ADDR']],
          ['🔢 Panel Version', PANEL_VERSION],
        ];
        foreach ($rows as [$k,$v]): ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
          <span style="font-size:13px;color:var(--text2);"><?= $k ?></span>
          <span style="font-family:'DM Mono',monospace;font-size:12px;"><?= htmlspecialchars($v) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:20px;">
        <a href="/logout.php" class="btn btn-danger" style="width:100%;">⏻ Logout</a>
      </div>
    </div>
  </div>
</div>

<!-- Bot Config Editor -->
<?php if ($bot_config): ?>
<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <span class="card-title">🤖 Bot Config Editor</span>
    <span class="badge badge-green">config.json found</span>
  </div>
  <div class="card-body">
    <div class="alert alert-warn" style="margin-bottom:20px;">⚠️ Changes here edit the bot's config.json directly. Restart the bot after saving.</div>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="save_bot_config">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Command Prefix</label>
          <input type="text" name="prefix" class="form-input" value="<?= htmlspecialchars($bot_config['prefix'] ?? '!') ?>" maxlength="5">
        </div>
        <div class="form-group">
          <label class="form-label">Min Account Age (weeks)</label>
          <input type="number" name="minimum_weeks" class="form-input" value="<?= $bot_config['account_age']['minimum_weeks'] ?? 4 ?>" min="0" max="52">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Coins per Minute (VC)</label>
          <input type="number" name="per_minute_vc" class="form-input" value="<?= $bot_config['coins']['per_minute_vc'] ?? 1 ?>" step="0.1" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Screen Share Multiplier</label>
          <input type="number" name="screen_share_multiplier" class="form-input" value="<?= $bot_config['coins']['screen_share_multiplier'] ?? 1.5 ?>" step="0.1" min="1">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Coins per Key</label>
          <input type="number" name="coins_per_key" class="form-input" value="<?= $bot_config['coins']['coins_per_key'] ?? 90 ?>" min="1">
        </div>
        <div class="form-group">
          <label class="form-label">Daily Redeem Limit</label>
          <input type="number" name="daily_redeem_limit" class="form-input" value="<?= $bot_config['coins']['daily_redeem_limit'] ?? 2 ?>" min="1">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Max Commands per Minute</label>
          <input type="number" name="max_commands_per_minute" class="form-input" value="<?= $bot_config['anti_spam']['max_commands_per_minute'] ?? 5 ?>" min="1">
        </div>
        <div class="form-group">
          <label class="form-label">Log Channel ID</label>
          <input type="text" name="log_channel_id" class="form-input" value="<?= htmlspecialchars($bot_config['channels']['log_channel_id'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Public Panel Channel ID</label>
          <input type="text" name="public_panel_channel_id" class="form-input" value="<?= htmlspecialchars($bot_config['channels']['public_panel_channel_id'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Admin Panel Channel ID</label>
          <input type="text" name="admin_panel_channel_id" class="form-input" value="<?= htmlspecialchars($bot_config['channels']['admin_panel_channel_id'] ?? '') ?>">
        </div>
      </div>
      <button type="submit" class="btn btn-success">💾 Save Bot Config</button>
    </form>
  </div>
</div>
<?php else: ?>
<div class="alert alert-warn">⚠️ Bot config.json not found at expected path. Update <code>$config_json_path</code> in settings.php.</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
