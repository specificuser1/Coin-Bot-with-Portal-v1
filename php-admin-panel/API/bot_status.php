<?php
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

$db = getDB();
$response = [
    'online'    => false,
    'paused'    => false,
    'latency'   => null,
    'uptime'    => null,
    'keys_avail' => 0,
    'vc_active'  => 0,
    'total_coins' => 0,
];

if ($db) {
    try {
        $paused = $db->query("SELECT value FROM bot_settings WHERE key='bot_paused'")->fetchColumn();
        $start  = $db->query("SELECT value FROM bot_settings WHERE key='start_time'")->fetchColumn();

        $response['online']     = true;
        $response['paused']     = ($paused === '1');
        $response['keys_avail'] = (int)$db->query("SELECT COUNT(*) FROM keys WHERE is_used=0")->fetchColumn();
        $response['vc_active']  = (int)$db->query("SELECT COUNT(*) FROM vc_sessions")->fetchColumn();
        $response['total_coins']= (float)$db->query("SELECT COALESCE(SUM(coins),0) FROM coins")->fetchColumn();

        if ($start) {
            $diff = time() - strtotime($start);
            $h = floor($diff/3600); $m = floor(($diff%3600)/60); $s = $diff%60;
            $response['uptime'] = sprintf('%02d:%02d:%02d', $h, $m, $s);
        }
    } catch(Exception $e) {
        $response['online'] = false;
        $response['error']  = $e->getMessage();
    }
}

echo json_encode($response);
