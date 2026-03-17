<?php
// ═══════════════════════════════════════════════════════════
//   VC COIN EARNER — PHP ADMIN PANEL CONFIG
//   Programmed by SUBHAN
// ═══════════════════════════════════════════════════════════

define('PANEL_VERSION', '2.0.0');
define('PANEL_NAME', 'VC Coin Earner');
define('PANEL_DEVELOPER', 'SUBHAN');

// ─── ADMIN LOGIN CREDENTIALS ────────────────────────────────
// Change these before deploying!
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', password_hash('admin123', PASSWORD_BCRYPT)); // Change password!
define('SESSION_LIFETIME', 3600); // 1 hour

// ─── DATABASE PATH ──────────────────────────────────────────
// Point this to your bot's SQLite database file
define('DB_PATH', __DIR__ . '/../vc-coin-bot/data/bot_data.db');
// Alternative: absolute path on Railway/server
// define('DB_PATH', '/app/vc-coin-bot/data/bot_data.db');

// ─── DISCORD BOT API ────────────────────────────────────────
// Internal bot REST API (runs alongside the bot)
define('BOT_API_URL', 'http://localhost:5000');
define('BOT_API_KEY', 'your-secret-api-key-here'); // Must match bot's api_key in config.json

// ─── DISCORD CONFIG ─────────────────────────────────────────
define('DISCORD_BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');
define('DISCORD_GUILD_ID', 'YOUR_GUILD_ID_HERE');

// ─── PANEL SETTINGS ─────────────────────────────────────────
define('TIMEZONE', 'UTC');
define('DATE_FORMAT', 'Y-m-d H:i:s');
define('MAX_LOG_ENTRIES', 100);

// ─── SECURITY ───────────────────────────────────────────────
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

date_default_timezone_set(TIMEZONE);
session_start();

// Auto-redirect if not logged in
function requireLogin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: /login.php');
        exit;
    }
    if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
        session_destroy();
        header('Location: /login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// Get SQLite DB connection
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            return null;
        }
    }
    return $db;
}

// Make API call to bot
function botAPI($endpoint, $method = 'GET', $data = []) {
    $url = BOT_API_URL . $endpoint;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . BOT_API_KEY
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false) return ['error' => 'Bot API unreachable', 'online' => false];
    return json_decode($response, true) ?? ['error' => 'Invalid response'];
}

// Format numbers
function formatCoins($n) {
    if ($n >= 1000000) return number_format($n/1000000, 1) . 'M';
    if ($n >= 1000) return number_format($n/1000, 1) . 'K';
    return number_format($n, 1);
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}

// CSRF token
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
