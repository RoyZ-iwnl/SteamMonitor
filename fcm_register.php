<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $steamId = $_POST['steam_id'] ?? '';
    $token = $_POST['token'] ?? '';
    if ($steamId && $token) {
        $settings = getFCMSettings($steamId);
        if (!in_array($token, $settings['tokens'])) $settings['tokens'][] = $token;
        $settings['enabled'] = true;
        saveFCMSettings($steamId, $settings);
        echo "OK";
    } else {
        http_response_code(400);
        echo "参数错误";
    }
}
?>