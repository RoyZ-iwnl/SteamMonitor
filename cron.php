<?php
// cron.php - 后台定时任务脚本
require_once 'config.php';

// 获取要监控的Steam ID
$steamIds = [];
$configFile = DATA_DIR . "/monitored_users.json";

if (file_exists($configFile)) {
    $steamIds = json_decode(file_get_contents($configFile), true);
}

// 获取设备令牌配置
$deviceTokensFile = DATA_DIR . "/device_tokens.json";
$deviceTokens = [];
if (file_exists($deviceTokensFile)) {
    $deviceTokens = json_decode(file_get_contents($deviceTokensFile), true);
}

// 获取要监控的Steam ID
$steamIds = [];
$configFile = DATA_DIR . "/monitored_users.json";

if (file_exists($configFile)) {
    $steamIds = json_decode(file_get_contents($configFile), true);
}

// 更新所有用户的游戏状态并发送通知
foreach ($steamIds as $steamId) {
    $status = updateUserGameStatus($steamId);
    $hiddenGaming = detectHiddenGaming($steamId);
    $wishlistChanges = getWishlistChanges($steamId);

    // 检查是否需要发送通知
    // 检查是否需要发送通知
    /*if (!empty($hiddenGaming) || !empty($wishlistChanges['changes']['new_items'])) {
        foreach ($deviceTokens as $token) {
            sendFirebaseNotification($token, [
                'title' => '游戏状态更新',
                'message' => '检测到新的游戏活动或愿望单变化',
                'steam_id' => $steamId,
                'hidden_gaming' => $hiddenGaming,
                'wishlist_changes' => $wishlistChanges['changes']
            ]);
        }
    }*/

    if (!empty($wishlistData['changes']['new_items']) || 
        !empty($wishlistData['changes']['removed_items']) || 
        !empty($wishlistData['changes']['price_changes'])) {
        
        $logFile = DATA_DIR . "/wishlist_changes_" . date('Y-m-d') . ".log";
        $logEntry = date('Y-m-d H:i:s') . " - User $steamId wishlist changes: " . 
                   json_encode($wishlistData['changes']) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

echo "Cron job executed at " . date('Y-m-d H:i:s') . "\n";
echo "Updated " . count($steamIds) . " users\n";

?>