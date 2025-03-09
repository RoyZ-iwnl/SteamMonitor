<?php
// api.php - 提供JSON API
require_once 'config.php';

header('Content-Type: application/json');

// 简单的API密钥验证
$apiKey = $_GET['key'] ?? '';
if ($apiKey !== '设置你的API密钥') {
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_users':
        $configFile = DATA_DIR . "/monitored_users.json";
        if (file_exists($configFile)) {
            $steamIds = json_decode(file_get_contents($configFile), true);
            echo json_encode(['users' => $steamIds]);
        } else {
            echo json_encode(['users' => []]);
        }
        break;
        
    case 'get_user_data':
        $steamId = $_GET['steam_id'] ?? '';
        if (empty($steamId)) {
            echo json_encode(['error' => 'Steam ID is required']);
            break;
        }
        
        $userData = getSteamUserData($steamId);
        $records = getUserDailyRecord($steamId);
        $hiddenGaming = detectHiddenGaming($steamId);
        
        echo json_encode([
            'user' => $userData,
            'records' => $records,
            'hidden_gaming' => $hiddenGaming
        ]);
        break;
        
    case 'update_user':
        $steamId = $_GET['steam_id'] ?? '';
        if (empty($steamId)) {
            echo json_encode(['error' => 'Steam ID is required']);
            break;
        }
        
        $result = updateUserGameStatus($steamId);
        echo json_encode(['success' => true, 'data' => $result]);
        break;
        
    default:
        echo json_encode(['error' => 'Unknown action']);
}
?>