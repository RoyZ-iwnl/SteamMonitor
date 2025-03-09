<?php
// config.php - 配置文件
define('STEAM_API_KEY', '你的STEAM_API_KEY'); // 从 https://steamcommunity.com/dev/apikey 获取
define('DATA_DIR', __DIR__ . '/data');
define('TIMEZONE', 'Asia/Shanghai'); // UTC+8
define('RESET_HOUR', 10); // 每天上午10点(UTC+8)重置

// 确保数据目录存在
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// 设置时区
date_default_timezone_set(TIMEZONE);

/**
 * 获取Steam用户信息
 * @param string $steamId Steam ID
 * @return array|false 用户信息或失败时返回false
 */
function getSteamUserData($steamId) {
    $url = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key=" . STEAM_API_KEY . "&steamids=" . $steamId;
    $response = file_get_contents($url);
    
    if ($response === false) {
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['response']['players'][0])) {
        return $data['response']['players'][0];
    }
    
    return false;
}

/**
 * 获取Steam用户最近游戏信息
 * @param string $steamId Steam ID
 * @return array|false 最近游戏信息或失败时返回false
 */
function getRecentGames($steamId) {
    $url = "https://api.steampowered.com/IPlayerService/GetRecentlyPlayedGames/v1/?key=" . STEAM_API_KEY . "&steamid=" . $steamId;
    $response = file_get_contents($url);
    
    if ($response === false) {
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['response']['games'])) {
        return $data['response']['games'];
    }
    
    return false;
}

/**
 * 获取游戏信息
 * @param int $appId 游戏应用ID
 * @return array|false 游戏信息或失败时返回false
 */
function getGameInfo($appId) {
    // 使用缓存避免重复请求
    $cacheFile = DATA_DIR . "/game_" . $appId . ".json";
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400 * 7)) { // 7天缓存
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    $url = "https://store.steampowered.com/api/appdetails?appids=" . $appId;
    $response = file_get_contents($url);
    
    if ($response === false) {
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data[$appId]['data'])) {
        file_put_contents($cacheFile, json_encode($data[$appId]['data']));
        return $data[$appId]['data'];
    }
    
    return false;
}

/**
 * 获取用户当天的游戏记录
 * @param string $steamId Steam ID
 * @return array 游戏记录
 */
function getUserDailyRecord($steamId) {
    $today = date('Y-m-d');
    $recordFile = DATA_DIR . "/record_" . $steamId . "_" . $today . ".json";
    
    if (file_exists($recordFile)) {
        return json_decode(file_get_contents($recordFile), true);
    }
    
    return ['records' => [], 'last_check' => null];
}

/**
 * 保存用户当天的游戏记录
 * @param string $steamId Steam ID
 * @param array $records 游戏记录
 */
function saveUserDailyRecord($steamId, $records) {
    $today = date('Y-m-d');
    $recordFile = DATA_DIR . "/record_" . $steamId . "_" . $today . ".json";
    
    file_put_contents($recordFile, json_encode($records));
}

/**
 * 检查是否需要重置记录(每天上午10点)
 * @return bool 是否已重置
 */
function checkAndResetRecord() {
    $resetFile = DATA_DIR . "/last_reset.txt";
    $today = date('Y-m-d');
    $currentHour = (int)date('H');
    
    if (file_exists($resetFile)) {
        $lastReset = trim(file_get_contents($resetFile));
        
        // 如果已经是今天且已经过了重置时间，则不需要重置
        if ($lastReset === $today && $currentHour >= RESET_HOUR) {
            return false;
        }
    }
    
    // 如果今天没重置且已经过了重置时间，则需要重置
    if ($currentHour >= RESET_HOUR) {
        file_put_contents($resetFile, $today);
        return true;
    }
    
    return false;
}

/**
 * 获取用户历史游戏记录
 * @param string $steamId Steam ID
 * @param int $days 历史天数
 * @return array 历史游戏记录
 */
function getUserHistoryRecords($steamId, $days = 7) {
    $history = [];
    $today = date('Y-m-d');
    
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $recordFile = DATA_DIR . "/record_" . $steamId . "_" . $date . ".json";
        
        if (file_exists($recordFile)) {
            $history[$date] = json_decode(file_get_contents($recordFile), true);
        }
    }
    
    return $history;
}

/**
 * 更新用户游戏状态
 * @param string $steamId Steam ID
 */
function updateUserGameStatus($steamId) {
    $userData = getSteamUserData($steamId);
    
    if (!$userData) {
        return false;
    }
    
    $records = getUserDailyRecord($steamId);
    $now = time();
    $currentGame = null;
    $currentState = $userData['personastate']; // 记录在线状态
    
    // 获取当前游戏状态
    if (isset($userData['gameextrainfo']) && isset($userData['gameid'])) {
        $currentGame = [
            'id' => $userData['gameid'],
            'name' => $userData['gameextrainfo']
        ];
    }
    
    // 检查上次记录状态
    $lastRecord = end($records['records']);
    
    if ($lastRecord === false) {
        // 没有记录，创建新记录
        if ($currentGame) {
            $records['records'][] = [
                'start' => $now,
                'end' => null,
                'game_id' => $currentGame['id'],
                'game_name' => $currentGame['name']
            ];
        }
    } else {
        if ($lastRecord['end'] === null) {
            // 上次游戏还未结束
            if (!$currentGame) {
                // 现在不在游戏中，结束上次游戏
                $records['records'][count($records['records']) - 1]['end'] = $now;
            } elseif ($lastRecord['game_id'] != $currentGame['id']) {
                // 游戏变更，结束上次游戏并开始新游戏
                $records['records'][count($records['records']) - 1]['end'] = $now;
                $records['records'][] = [
                    'start' => $now,
                    'end' => null,
                    'game_id' => $currentGame['id'],
                    'game_name' => $currentGame['name']
                ];
            }
            // 如果还在玩同一个游戏，不做任何改变
        } else {
            // 上次游戏已结束
            if ($currentGame) {
                // 现在在玩游戏，创建新记录
                $records['records'][] = [
                    'start' => $now,
                    'end' => null,
                    'game_id' => $currentGame['id'],
                    'game_name' => $currentGame['name']
                ];
            }
        }
    }
    
    // 处理"离开"状态记录
    if (!isset($records['away_records'])) {
        $records['away_records'] = [];
    }

    $lastAway = end($records['away_records']);
    if ($lastAway === false || $lastAway['end'] !== null) {
        if ($currentState == 3) { // 进入"离开"状态
            $records['away_records'][] = [
                'start' => $now,
                'end' => null,
                'status' => 'away'
            ];
        }
    } else {
        if ($currentState != 3) { // 退出"离开"状态
            $records['away_records'][count($records['away_records']) - 1]['end'] = $now;
        }
    }
    
    // 处理用户上下线记录
    if (!isset($records['online_records'])) {
        $records['online_records'] = [];
    }

    $lastOnline = end($records['online_records']);
    if ($lastOnline === false || $lastOnline['end'] !== null) {
        if ($currentState != 0) { // 用户上线
            $records['online_records'][] = [
                'start' => $now,
                'end' => null,
                'status' => 'online'
            ];
        }
    } else {
        if ($currentState == 0) { // 用户下线
            $records['online_records'][count($records['online_records']) - 1]['end'] = $now;
        }
    }
    
    $records['last_check'] = $now;
    $records['user_status'] = $userData['personastate'];
    $records['visibility'] = isset($userData['communityvisibilitystate']) ? $userData['communityvisibilitystate'] : 0;
    
    // 保存记录
    saveUserDailyRecord($steamId, $records);
    
    return $records;
}


/**
 * 检测可能的隐身游戏
 * @param string $steamId Steam ID
 * @return array 可能的隐身游戏信息
 */
function detectHiddenGaming($steamId) {
    $recentGames = getRecentGames($steamId);
    $userData = getSteamUserData($steamId);
    $history = getUserHistoryRecords($steamId);
    $hiddenGaming = [];
    
    if (!$recentGames || !$userData) {
        return $hiddenGaming;
    }
    
    // 用户状态显示为离线但最近游戏时间有更新
    if ($userData['personastate'] == 0) { // 0 = 离线
        foreach ($recentGames as $game) {
            $lastPlayedTime = $game['last_played'];
            $playedMinutesLast2Weeks = $game['playtime_2weeks'];
            
            // 计算记录中这个游戏的总时长(分钟)
            $recordedMinutes = 0;
            foreach ($history as $date => $dayRecord) {
                foreach ($dayRecord['records'] as $record) {
                    if ($record['game_id'] == $game['appid']) {
                        $startTime = $record['start'];
                        $endTime = $record['end'] ? $record['end'] : time();
                        $recordedMinutes += ($endTime - $startTime) / 60;
                    }
                }
            }
            
            // 如果Steam报告的时间比我们记录的长，可能存在隐身游戏
            if ($playedMinutesLast2Weeks > $recordedMinutes) {
                $hiddenGaming[] = [
                    'game_id' => $game['appid'],
                    'game_name' => $game['name'],
                    'last_played' => $lastPlayedTime,
                    'steam_minutes' => $playedMinutesLast2Weeks,
                    'recorded_minutes' => $recordedMinutes,
                    'possible_hidden_minutes' => $playedMinutesLast2Weeks - $recordedMinutes
                ];
            }
        }
    }
    
    return $hiddenGaming;
}
?>