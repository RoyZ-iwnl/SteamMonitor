// config.php - 完整配置文件
<?php
// Steam API配置
define('STEAM_API_KEY', '你的STEAM_API_KEY'); // 从 https://steamcommunity.com/dev/apikey 获取
define('DATA_DIR', __DIR__ . '/data');
define('FCM_SETTINGS_DIR', DATA_DIR . '/fcm_settings');
define('FCM_LOGS_DIR', DATA_DIR . '/fcm_logs');
define('TIMEZONE', 'Asia/Shanghai'); // UTC+8

// Firebase配置
define('GOOGLE_APPLICATION_CREDENTIALS', __DIR__ . '/firebase-service-account.json');
define('FCM_API_URL', 'https://fcm.googleapis.com/v1/projects/your-project-id/messages:send');
define('FCM_DEBUG_LOG', FCM_LOGS_DIR . '/fcm_debug.log');

// 创建必要目录
$dirs = [DATA_DIR, FCM_SETTINGS_DIR, FCM_LOGS_DIR];
foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// 设置时区
date_default_timezone_set(TIMEZONE);

// 安全头信息
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

/**
 * 记录调试信息
 * @param string $message 日志信息
 * @param array $context 上下文数据
 */
function logDebug($message, $context = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context
    ];
    file_put_contents(
        FCM_DEBUG_LOG, 
        json_encode($logEntry, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . PHP_EOL, 
        FILE_APPEND
    );
}


/**
 * 发送FCM测试推送（完整调试版）
 * @param string $steamId Steam ID
 * @return bool 是否发送成功
 */
function sendFMCTestNotification($steamId) {
    logDebug('=== 开始FCM测试推送 ===', ['steam_id' => $steamId]);

    // 1. 获取FCM设置
    $fcmSettings = getFCMSettings($steamId);
    logDebug('读取FCM设置', [
        'enabled' => $fcmSettings['enabled'],
        'tokens_count' => count($fcmSettings['tokens']),
        'settings_file' => FCM_SETTINGS_DIR . "/{$steamId}.json"
    ]);

    // 2. 获取用户数据
    $userData = getSteamUserData($steamId);
    logDebug('获取Steam用户数据', [
        'success' => $userData !== false,
        'personaname' => $userData['personaname'] ?? null
    ]);

    // 3. 验证条件
    if (!$fcmSettings['enabled']) {
        logDebug('测试终止: FCM未启用');
        return false;
    }

    if (empty($fcmSettings['tokens'])) {
        logDebug('测试终止: 无设备令牌');
        return false;
    }

    if (!$userData) {
        logDebug('测试终止: 无法获取用户数据');
        return false;
    }

    // 4. 发送测试通知
    $userName = $userData['personaname'] ?? $steamId;
    $result = true;
    $sentCount = 0;

    logDebug('准备发送测试通知', [
        'tokens_count' => count($fcmSettings['tokens']),
        'user_name' => $userName
    ]);

    foreach ($fcmSettings['tokens'] as $index => $token) {
        logDebug("处理设备令牌 #{$index}", [
            'token_short' => substr($token, -6)
        ]);

        $success = sendFCMNotification(
            $token,
            "测试推送 - {$userName}",
            "这是一条测试推送消息 (" . date('H:i:s') . ")",
            [
                'type' => 'test',
                'steam_id' => $steamId,
                'timestamp' => time()
            ]
        );

        if ($success) {
            $sentCount++;
            logDebug("通知发送成功 #{$index}", ['token_short' => substr($token, -6)]);
        } else {
            $result = false;
            logDebug("通知发送失败 #{$index}", ['token_short' => substr($token, -6)]);
        }
    }

    logDebug('测试推送完成', [
        'total_tokens' => count($fcmSettings['tokens']),
        'success_count' => $sentCount,
        'final_result' => $result
    ]);

    return $result;
}

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

/*
 * 检查是否需要重置记录(每天上午10点)
 * @return bool 是否已重置

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
*/

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
 * 更新用户游戏状态 [修改后的版本，包含FCM推送]
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
    
    // FCM推送处理
    $fcmSettings = getFCMSettings($steamId);
    
    // 如果启用了FCM通知
    if ($fcmSettings['enabled'] && !empty($fcmSettings['tokens'])) {
        $userName = $userData['personaname'] ?? $steamId;
        
        // 检查在线状态变化
        if (isset($records['user_status']) && $records['user_status'] != $currentState) {
            if ($fcmSettings['notify_online']) {
                $status = '';
                switch ($currentState) {
                    case 0:
                        $status = '离线';
                        break;
                    case 1:
                        $status = '在线';
                        break;
                    case 3:
                        $status = '离开';
                        break;
                    default:
                        $status = '状态改变';
                }
                
                foreach ($fcmSettings['tokens'] as $token) {
                    sendFCMNotification(
                        $token,
                        "Steam状态更新",
                        "{$userName} 现在{$status}",
                        [
                            'type' => 'status_change',
                            'steam_id' => $steamId,
                            'status' => $currentState
                        ]
                    );
                }
            }
        }
        
        // 检查游戏状态变化
        if ($fcmSettings['notify_gaming']) {
            if ($currentGame && (!isset($lastRecord['game_id']) || $lastRecord['game_id'] != $currentGame['id'])) {
                // 开始新游戏
                foreach ($fcmSettings['tokens'] as $token) {
                    sendFCMNotification(
                        $token,
                        "开始游戏",
                        "{$userName} 开始玩 {$currentGame['name']}",
                        [
                            'type' => 'game_start',
                            'steam_id' => $steamId,
                            'game_id' => $currentGame['id'],
                            'game_name' => $currentGame['name']
                        ]
                    );
                }
            } elseif (!$currentGame && isset($lastRecord['game_id']) && $lastRecord['end'] === null) {
                // 结束游戏
                foreach ($fcmSettings['tokens'] as $token) {
                    sendFCMNotification(
                        $token,
                        "结束游戏",
                        "{$userName} 结束了 {$lastRecord['game_name']}",
                        [
                            'type' => 'game_end',
                            'steam_id' => $steamId,
                            'game_id' => $lastRecord['game_id'],
                            'game_name' => $lastRecord['game_name']
                        ]
                    );
                }
            }
        }
    }
    
    // 处理"离开"状态记录
    if (!isset($records['away_records'])) {
        $records['away_records'] = [];
    }

    $lastAway = end($records['away_records']);
    $awayPushType = null;

    if ($lastAway === false || $lastAway['end'] !== null) {
        if ($currentState == 3) { // 进入"离开"状态
            $records['away_records'][] = [
                'start' => $now,
                'end' => null,
                'status' => 'away'
            ];
            $awayPushType = 'away_start';
        }
    } else {
        if ($currentState != 3) { // 退出"离开"状态
            $records['away_records'][count($records['away_records']) - 1]['end'] = $now;
            $awayPushType = 'away_end';
        }
    }

// FCM notify_away 推送（只在状态变化时推送）
    if (
        $fcmSettings['notify_away'] &&
        !empty($fcmSettings['tokens']) &&
        $awayPushType
    ) {
        $userName = $userData['personaname'] ?? $steamId;
        $msg = $awayPushType === 'away_start' ?
            "{$userName} 现在处于离开状态" :
            "{$userName} 已从离开状态恢复";

        foreach ($fcmSettings['tokens'] as $token) {
            sendFCMNotification(
                $token,
                "Steam状态更新",
                $msg,
                [
                    'type' => $awayPushType,
                    'steam_id' => $steamId
                ]
            );
        }
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
 * 获取用户愿望单
 * @param string $steamId Steam ID
 * @return array|false 愿望单信息或失败时返回false
 */
function getUserWishlist($steamId) {
    $url = "https://store.steampowered.com/wishlist/profiles/" . $steamId . "/wishlistdata/?p=0";
    $response = file_get_contents($url);

    if ($response === false) {
        return false;
    }

    $data = json_decode($response, true);

    if (is_array($data)) {
        // 读取已有缓存，合并新老 fetch_time
        $cacheFile = DATA_DIR . "/wishlist_" . $steamId . ".json";
        $old = [];
        if (file_exists($cacheFile)) {
            $old = json_decode(file_get_contents($cacheFile), true);
        }
        foreach ($data as $appid => &$item) {
            if (isset($old[$appid]['fetch_time'])) {
                $item['fetch_time'] = $old[$appid]['fetch_time'];
            } else {
                $item['fetch_time'] = time(); // 新增的才打时间戳
            }
        }
        unset($item);

        file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
        return $data;
    }
    return false;
}

/**
 * 清除旧记录
 * @param int $daysToKeep 保留多少天的记录
 */
function cleanOldRecords($daysToKeep = 30) {
    $files = glob(DATA_DIR . "/record_*_*.json");
    $now = time();
    
    foreach ($files as $file) {
        $fileDate = preg_replace('/.*record_.*_(\d{4}-\d{2}-\d{2})\.json/', '$1', $file);
        $fileTime = strtotime($fileDate);
        
        if ($fileTime && ($now - $fileTime) > ($daysToKeep * 86400)) {
            unlink($file);
        }
    }
}

/**
 * 分析用户使用习惯
 * @param string $steamId Steam ID
 * @param int $days 分析天数
 * @return array 使用习惯分析结果
 */
function analyzeUserHabits($steamId, $days = 7) {
    $history = getUserHistoryRecords($steamId, $days);
    $analysis = [
        'daily_stats' => [],
        'weekly_stats' => [
            'monday' => ['total' => 0, 'count' => 0],
            'tuesday' => ['total' => 0, 'count' => 0],
            'wednesday' => ['total' => 0, 'count' => 0],
            'thursday' => ['total' => 0, 'count' => 0],
            'friday' => ['total' => 0, 'count' => 0],
            'saturday' => ['total' => 0, 'count' => 0],
            'sunday' => ['total' => 0, 'count' => 0]
        ],
        'most_played_games' => [],
        'average_session' => 0,
        'total_gaming_time' => 0,
        'peak_hours' => array_fill(0, 24, 0)
    ];
    
    $totalSessions = 0;
    $totalTime = 0;
    $gameTimes = [];
    
    foreach ($history as $date => $dayRecord) {
        $dayStats = [
            'total_time' => 0,
            'sessions' => 0,
            'games' => []
        ];
        
        $dayOfWeek = strtolower(date('l', strtotime($date)));
        
        foreach ($dayRecord['records'] as $record) {
            $startTime = $record['start'];
            $endTime = $record['end'] ?? time();
            $duration = $endTime - $startTime;
            
            // 更新每日统计
            $dayStats['total_time'] += $duration;
            $dayStats['sessions']++;
            
            // 更新游戏时间统计
            if (!isset($gameTimes[$record['game_id']])) {
                $gameTimes[$record['game_id']] = [
                    'name' => $record['game_name'],
                    'total_time' => 0
                ];
            }
            $gameTimes[$record['game_id']]['total_time'] += $duration;
            
            // 更新峰值时间统计
            $hour = (int)date('G', $startTime);
            $analysis['peak_hours'][$hour]++;
            
            // 更新每周统计
            $analysis['weekly_stats'][$dayOfWeek]['total'] += $duration;
            $analysis['weekly_stats'][$dayOfWeek]['count']++;
        }
        
        $analysis['daily_stats'][$date] = $dayStats;
        $totalSessions += $dayStats['sessions'];
        $totalTime += $dayStats['total_time'];
    }
    
    // 计算平均会话时长
    $analysis['average_session'] = $totalSessions > 0 ? $totalTime / $totalSessions : 0;
    $analysis['total_gaming_time'] = $totalTime;
    
    // 排序游戏时间
    arsort($gameTimes);
    $analysis['most_played_games'] = array_slice($gameTimes, 0, 5, true);
    
    return $analysis;
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

/**
 * 获取FCM访问令牌
 * @return string|false 访问令牌或失败时返回false
 */
function getFCMAccessToken() {
    static $accessToken = null;
    static $tokenExpiry = 0;
    
    // 如果令牌未过期，直接返回缓存的令牌
    if ($accessToken && time() < $tokenExpiry) {
        return $accessToken;
    }
    
    // 读取服务账号密钥文件
    $serviceAccount = json_decode(file_get_contents(GOOGLE_APPLICATION_CREDENTIALS), true);
    if (!$serviceAccount) {
        error_log('Failed to load Firebase service account credentials');
        return false;
    }
    
    // 构造JWT
    $now = time();
    $jwt = [
        'iss' => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ];
    
    // 签名JWT
    $key = openssl_pkey_get_private($serviceAccount['private_key']);
    $segments = [];
    $segments[] = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $segments[] = base64url_encode(json_encode($jwt));
    $signing_input = implode('.', $segments);
    openssl_sign($signing_input, $signature, $key, 'SHA256');
    $segments[] = base64url_encode($signature);
    $assertion = implode('.', $segments);
    
    // 获取访问令牌
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $assertion
    ]));
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($status == 200) {
        $data = json_decode($response, true);
        $accessToken = $data['access_token'];
        $tokenExpiry = time() + $data['expires_in'] - 300; // 提前5分钟过期
        return $accessToken;
    }
    
    error_log('Failed to get FCM access token: ' . $response);
    return false;
}

/**
 * Base64Url编码
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * 发送FCM推送通知（完整调试版）
 * @param string $token 设备令牌
 * @param string $title 通知标题
 * @param string $body 通知内容
 * @param array $data 附加数据
 * @return bool 是否发送成功
 */
function sendFCMNotification($token, $title, $body, $data = []) {
    logDebug('FCM推送开始', [
        'token_short' => substr($token, -6), // 只记录后6位
        'title' => $title,
        'body' => $body
    ]);

    // 1. 获取访问令牌
    $accessToken = getFCMAccessToken();
    if (!$accessToken) {
        logDebug('FCM访问令牌获取失败', ['error' => '无法获取访问令牌']);
        return false;
    }
    logDebug('FCM访问令牌获取成功', ['token_short' => substr($accessToken, -6)]);

    // 2. 构造消息体
    $message = [
        'message' => [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body
            ],
            'data' => $data
        ]
    ];

    // 3. 发送请求
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => FCM_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($message),
        CURLOPT_TIMEOUT => 10
    ]);

    logDebug('准备发送FCM请求', [
        'api_url' => FCM_API_URL,
        'payload' => $message
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // 4. 处理响应
    logDebug('FCM响应', [
        'http_code' => $httpCode,
        'response' => $response,
        'curl_error' => $error ?: '无'
    ]);

    if ($error) {
        logDebug('FCM请求CURL错误', ['error' => $error]);
        return false;
    }

    if ($httpCode !== 200) {
        logDebug('FCM请求HTTP错误', [
            'http_code' => $httpCode,
            'response' => $response
        ]);
        return false;
    }

    $responseData = json_decode($response, true);
    if (isset($responseData['name'])) {
        logDebug('FCM推送成功', ['response_id' => $responseData['name']]);
        return true;
    }

    logDebug('FCM响应格式异常', ['response' => $response]);
    return false;
}


/**
 * 获取用户的FCM通知设置
 * @param string $steamId Steam ID
 * @return array 通知设置
 */
function getFCMSettings($steamId) {
    $settingsFile = FCM_SETTINGS_DIR . "/{$steamId}.json";
    $settings = [];
    
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true);
    }
    
    return isset($settings[$steamId]) ? $settings[$steamId] : [
        'enabled' => false,
        'tokens' => [],
        'notify_online' => true,
        'notify_gaming' => true,
        'notify_away' => false
    ];
}

/**
 * 保存用户的FCM通知设置
 * @param string $steamId Steam ID
 * @param array $settings 通知设置
 */
function saveFCMSettings($steamId, $settings) {
    $settingsFile = FCM_SETTINGS_DIR . "/{$steamId}.json";
    $allSettings = [];
    
    if (file_exists($settingsFile)) {
        $allSettings = json_decode(file_get_contents($settingsFile), true);
    }
    
    $allSettings[$steamId] = $settings;
    file_put_contents($settingsFile, json_encode($allSettings));
}

?>