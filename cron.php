<?php
// cron.php - 后台定时任务脚本
require_once 'config.php';

// 获取要监控的Steam ID
$steamIds = [];
$configFile = DATA_DIR . "/monitored_users.json";

if (file_exists($configFile)) {
    $steamIds = json_decode(file_get_contents($configFile), true);
}

// 更新所有用户的游戏状态
foreach ($steamIds as $steamId) {
    updateUserGameStatus($steamId);
}

echo "Cron job executed at " . date('Y-m-d H:i:s') . "\n";
echo "Updated " . count($steamIds) . " users\n";
?>