<?php
require_once 'config.php';
require_once 'fcm_register.php';

if (isset($_GET['steam_id']) && isset($_GET['token'])) {
    $steamId = $_GET['steam_id'];
    $token = $_GET['token'];
    
    $settings = getFCMSettings($steamId);
    $settings['tokens'] = array_diff($settings['tokens'], [$token]);
    saveFCMSettings($steamId, $settings);
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));