<?php
require_once 'config.php';
require_once 'fcm_register.php';

// 获取要监控的Steam ID
$steamIds = [];
$configFile = DATA_DIR . "/monitored_users.json";

if (file_exists($configFile)) {
    $steamIds = json_decode(file_get_contents($configFile), true);
}

// 处理添加新用户
if (isset($_POST['add_user']) && !empty($_POST['steam_id'])) {
    $newId = trim($_POST['steam_id']);
    if (!in_array($newId, $steamIds)) {
        $steamIds[] = $newId;
        file_put_contents($configFile, json_encode($steamIds));
    }
}

// 处理删除用户
if (isset($_GET['remove']) && in_array($_GET['remove'], $steamIds)) {
    $steamIds = array_diff($steamIds, [$_GET['remove']]);
    file_put_contents($configFile, json_encode($steamIds));
}

// 处理清除旧记录
if (isset($_POST['clean_records']) && !empty($_POST['days_to_keep'])) {
    $daysToKeep = (int)$_POST['days_to_keep'];
    if ($daysToKeep > 0) {
        cleanOldRecords($daysToKeep);
    }
}

// 历史记录日期选择处理
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// 默认显示今天，历史日期选项为近30天
$historyDates = [];
for ($i = 0; $i < 30; $i++) {
    $historyDates[] = date('Y-m-d', strtotime("-$i days"));
}

// 更新所有用户的游戏状态（仅在查看今天时才更新）
$allRecords = [];
$allHiddenGaming = [];
$allWishlists = [];
$allWishlistNewToday = [];

foreach ($steamIds as $steamId) {
    if ($selectedDate === date('Y-m-d')) {
        updateUserGameStatus($steamId);
    }
    // 读取当天或历史的游戏记录
    $recordFile = DATA_DIR . "/record_" . $steamId . "_" . $selectedDate . ".json";
    if (file_exists($recordFile)) {
        $allRecords[$steamId] = json_decode(file_get_contents($recordFile), true);
    } else {
        $allRecords[$steamId] = ['records' => []];
    }
    // 检测隐身游戏（还是以今天为准）
    $allHiddenGaming[$steamId] = detectHiddenGaming($steamId);
    // 愿望单获取 + 新增项检测
    $wishlist = [];
    $wishlistFile = DATA_DIR . "/wishlist_" . $steamId . ".json";
    if (file_exists($wishlistFile)) {
        $wishlist = json_decode(file_get_contents($wishlistFile), true);
        if (!is_array($wishlist)) $wishlist = [];
    } else {
        $wishlist = getUserWishlist($steamId);
        if (!is_array($wishlist)) $wishlist = [];
    }
    $allWishlists[$steamId] = $wishlist;
    // 检查是否有今日新增游戏
    $hasNewToday = false;
    $today = date('Y-m-d');
    foreach ($wishlist as $appId => $item) {
        if (isset($item['fetch_time']) && date('Y-m-d', $item['fetch_time']) == $today) {
            $hasNewToday = true;
            break;
        }
    }
    $allWishlistNewToday[$steamId] = $hasNewToday;
}

// 处理FCM测试推送
if (isset($_GET['test_fcm']) && in_array($_GET['test_fcm'], $steamIds)) {
    $testResult = sendFMCTestNotification($_GET['test_fcm']);
    if ($testResult) {
        echo '<div class="alert alert-success">测试推送已发送！</div>';
    } else {
        echo '<div class="alert alert-danger">测试推送发送失败，请检查FCM设置！</div>';
    }
}

// 处理FCM设置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fcm_settings'])) {
    $steamId = $_POST['steam_id'];
    if (in_array($steamId, $steamIds)) {
        $settings = getFCMSettings($steamId);
        $settings['enabled'] = isset($_POST['fcm_enabled']);
        $settings['notify_online'] = isset($_POST['notify_online']);
        $settings['notify_gaming'] = isset($_POST['notify_gaming']);
        $settings['notify_away'] = isset($_POST['notify_away']);
        saveFCMSettings($steamId, $settings);
    }
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// HTML输出
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Steam游戏时间监控</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #1b2838;
            color: #c7d5e0;
        }
        .card {
            background-color: #2a475e;
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #171a21;
            color: #ffffff;
        }
        .timeline {
            position: relative;
            margin: 0 0 20px 0;
            padding: 0;
            list-style: none;
        }
        .timeline:before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #1b2838;
            left: 31px;
            margin: 0;
            border-radius: 2px;
        }
        .timeline > li {
            position: relative;
            margin-bottom: 15px;
            margin-right: 10px;
        }
        .timeline > li:before,
        .timeline > li:after {
            content: " ";
            display: table;
        }
        .timeline > li:after {
            clear: both;
        }
        .timeline > li > .timeline-item {
            margin-left: 60px;
            margin-right: 15px;
            background: #1b2838;
            color: #c7d5e0;
            padding: 10px;
            position: relative;
            border-radius: 3px;
        }
        .timeline > li > .fa {
            width: 30px;
            height: 30px;
            font-size: 16px;
            line-height: 30px;
            position: absolute;
            color: #fff;
            background: #66c0f4;
            border-radius: 50%;
            text-align: center;
            left: 18px;
            top: 0;
        }
        .user-offline {
            color: #898989;
        }
        .user-online {
            color: #66c0f4;
        }
        .user-ingame {
            color: #90ba3c;
        }
        .hidden-gaming {
            background-color: #76448A;
            padding: 5px;
            border-radius: 3px;
            color: #fff;
        }
        .btn-steam {
            background-color: #66c0f4;
            color: #fff;
        }
        .btn-steam:hover {
            background-color: #1b2838;
            color: #66c0f4;
        }
        .refresh-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        .wishlist-title {
            cursor: pointer;
            user-select: none;
        }
        .wishlist-list {
            margin-bottom: 0;
        }
        .wishlist-new-today {
            color: #fff;
            background: #27ae60;
            border: none;
            font-weight: bold;
        }
        .wishlist-toggle {
            transition: all 0.2s;
        }
        .wishlist-collapsed .wishlist-toggle {
            transform: rotate(0deg);
        }
        .wishlist-expanded .wishlist-toggle {
            transform: rotate(90deg);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h1 class="mb-4"><i class="fab fa-steam"></i> Steam游戏时间监控</h1>
                
                <div class="card mb-4">
                    <div class="card-header">
                        添加监控用户
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="steam_id" placeholder="输入Steam ID或自定义URL ID" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" name="add_user" class="btn btn-steam w-100">添加</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header">
                        记录管理
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <div class="col-md-10">
                                <input type="number" class="form-control" name="days_to_keep" placeholder="保留天数" required min="1">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" name="clean_records" class="btn btn-warning w-100" onclick="return confirm('确定要清除旧记录吗？此操作不可恢复！');">
                                    清除旧记录
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header">
                        历史记录查看
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-10">
                                <select class="form-select" name="date" onchange="this.form.submit()">
                                    <?php foreach ($historyDates as $date): ?>
                                        <option value="<?php echo $date; ?>" <?php if ($selectedDate === $date) echo 'selected'; ?>>
                                            <?php echo $date . ($date === date('Y-m-d') ? ' (今天)' : ''); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <noscript>
                                    <button type="submit" class="btn btn-info w-100">查看</button>
                                </noscript>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php foreach ($steamIds as $steamId): ?>
                    <?php 
                    $userData = getSteamUserData($steamId);
                    $records = $allRecords[$steamId] ?? ['records' => []];
                    $hiddenGaming = $allHiddenGaming[$steamId] ?? [];
                    $wishlist = $allWishlists[$steamId] ?? [];
                    $wishlistNewToday = $allWishlistNewToday[$steamId] ?? false;

                    if (!$userData) continue;

                    // 用户状态类
                    $statusClass = 'user-offline';
                    if (isset($userData['gameextrainfo'])) {
                        $statusClass = 'user-ingame';
                    } elseif ($userData['personastate'] > 0) {
                        $statusClass = 'user-online';
                    }

                    // 愿望单按添加时间排序（降序）
                    uasort($wishlist, function($a, $b) {
                        return ($b['fetch_time'] ?? 0) <=> ($a['fetch_time'] ?? 0);
                    });
                    ?>
                    
                    <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <a href="https://steamcommunity.com/profiles/<?php echo $steamId; ?>" target="_blank">
                                <img src="<?php echo $userData['avatarmedium']; ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32">
                                <span class="<?php echo $statusClass; ?>"><?php echo $userData['personaname']; ?></span>
                            </a>
                            <?php if (isset($userData['gameextrainfo'])): ?>
                                <span class="ms-2 badge bg-success">正在游戏: <?php echo $userData['gameextrainfo']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <a href="analysis.php?id=<?php echo $steamId; ?>" class="btn btn-sm btn-outline-info me-2">
                                <i class="fas fa-chart-bar"></i> 分析
                            </a>
                            <?php if ($selectedDate === date('Y-m-d')): ?>
                            <a href="?refresh=<?php echo $steamId; ?>" class="btn btn-sm btn-outline-info me-2">
                                <i class="fas fa-sync-alt"></i> 刷新
                            </a>
                            <?php endif; ?>
                            <a href="?test_fcm=<?php echo $steamId; ?>" class="btn btn-sm btn-outline-warning me-2">
                                <i class="fas fa-bell"></i> 测试推送
                            </a>
                            <a href="?remove=<?php echo $steamId; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('确定要删除这个用户吗?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                        <div class="card-body">
                            <h5><?php echo $selectedDate === date('Y-m-d') ? "今日" : $selectedDate; ?>游戏记录 (UTC+8)</h5>
                            
                            <?php if (empty($records['records'])): ?>
                                <p>本日没有游戏记录。</p>
                            <?php else: ?>
                                <ul class="timeline">
                                    <?php foreach ($records['records'] as $record): ?>
                                        <li>
                                            <i class="fa fa-gamepad"></i>
                                            <div class="timeline-item">
                                                <h3 class="timeline-header">
                                                    <?php echo $record['game_name']; ?>
                                                </h3>
                                                <div class="timeline-body">
                                                    开始时间: <?php echo date('H:i:s', $record['start']); ?><br>
                                                    <?php if ($record['end']): ?>
                                                        结束时间: <?php echo date('H:i:s', $record['end']); ?><br>
                                                        游戏时长: <?php echo gmdate('H:i:s', $record['end'] - $record['start']); ?>
                                                    <?php else: ?>
                                                        状态: <span class="text-success">正在游戏中</span><br>
                                                        已游戏时长: <?php echo gmdate('H:i:s', time() - $record['start']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($records['away_records'])): ?>
                                <h5>离开状态记录</h5>
                                <ul class="timeline">
                                    <?php foreach ($records['away_records'] as $away): ?>
                                        <li>
                                            <i class="fa fa-moon"></i>
                                            <div class="timeline-item">
                                                <h3 class="timeline-header">离开状态</h3>
                                                <div class="timeline-body">
                                                    开始时间: <?php echo date('H:i:s', $away['start']); ?><br>
                                                    <?php if ($away['end']): ?>
                                                        结束时间: <?php echo date('H:i:s', $away['end']); ?><br>
                                                        离开时长: <?php echo gmdate('H:i:s', $away['end'] - $away['start']); ?>
                                                    <?php else: ?>
                                                        <span class="text-warning">当前仍处于离开状态</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($records['online_records'])): ?>
                                <h5>上下线记录</h5>
                                <ul class="timeline">
                                    <?php foreach ($records['online_records'] as $online): ?>
                                        <li>
                                            <i class="fa fa-power-off"></i>
                                            <div class="timeline-item">
                                                <h3 class="timeline-header"><?php echo $online['status'] == 'online' ? '上线' : '下线'; ?></h3>
                                                <div class="timeline-body">
                                                    开始时间: <?php echo date('H:i:s', $online['start']); ?><br>
                                                    <?php if ($online['end']): ?>
                                                        结束时间: <?php echo date('H:i:s', $online['end']); ?><br>
                                                        时长: <?php echo gmdate('H:i:s', $online['end'] - $online['start']); ?>
                                                    <?php else: ?>
                                                        <span class="text-success">当前仍处于<?php echo $online['status'] == 'online' ? '在线' : '离线'; ?>状态</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($hiddenGaming)): ?>
                                <div class="mt-3 hidden-gaming">
                                    <h5><i class="fas fa-user-ninja"></i> 可能的隐身游戏</h5>
                                    <ul>
                                        <?php foreach ($hiddenGaming as $hidden): ?>
                                            <li>
                                                <?php echo $hidden['game_name']; ?> - 
                                                可能隐身时长: <?php echo gmdate('H:i:s', $hidden['possible_hidden_minutes'] * 60); ?>
                                                (Steam: <?php echo $hidden['steam_minutes']; ?>分钟, 记录: <?php echo round($hidden['recorded_minutes']); ?>分钟)
                                                <!--<span class="ms-2">最后运行: <?php //echo date('Y-m-d H:i:s', $hidden['last_played']); ?></span>-->
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- 愿望单折叠面板 -->
                            <div class="mt-3">
                                <div class="wishlist-title d-flex align-items-center <?php echo $wishlistNewToday ? 'wishlist-expanded' : 'wishlist-collapsed'; ?>" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#wishlist-<?php echo $steamId; ?>"
                                    aria-expanded="<?php echo $wishlistNewToday ? 'true' : 'false'; ?>"
                                    aria-controls="wishlist-<?php echo $steamId; ?>">
                                    <i class="fas fa-caret-right wishlist-toggle me-2"></i>
                                    <span>当前愿望单（<?php echo count($wishlist); ?>）</span>
                                    <?php if ($wishlistNewToday): ?>
                                        <span class="badge wishlist-new-today ms-2">今日有新增</span>
                                    <?php endif; ?>
                                </div>
                                <div class="collapse <?php echo $wishlistNewToday ? 'show' : ''; ?>" id="wishlist-<?php echo $steamId; ?>">
                                    <?php if (!empty($wishlist)): ?>
                                        <ul class="list-group wishlist-list mt-2">
                                            <?php foreach ($wishlist as $appId => $item): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center" style="background:#212c3d;color:#c7d5e0;">
                                                    <a href="https://store.steampowered.com/app/<?php echo $appId; ?>" target="_blank" style="color:#66c0f4;text-decoration:none;">
                                                        <?php echo htmlspecialchars($item['name'] ?? $appId); ?>
                                                    </a>
                                                    <span class="ms-2 text-muted" style="font-size:0.9em;">
                                                        添加时间: <?php echo isset($item['fetch_time']) ? date('Y-m-d H:i:s', $item['fetch_time']) : '未知'; ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <div class="text-muted mt-2">暂无愿望单数据。</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- FCM设置面板 -->
                            <div class="mt-4">
                                <h5><i class="fas fa-bell"></i> 推送通知设置</h5>
                                <?php 
                                $fcmSettings = getFCMSettings($steamId);
                                $isPublicProfile = ($records['visibility'] ?? 0) == 3;
                                ?>
                                <form method="post" class="fcm-settings-form">
                                    <input type="hidden" name="fcm_settings" value="1">
                                    <input type="hidden" name="steam_id" value="<?= htmlspecialchars($steamId) ?>">
                                    
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" name="fcm_enabled" 
                                            id="fcm-enabled-<?= $steamId ?>" 
                                            <?= $fcmSettings['enabled'] ? 'checked' : '' ?>
                                            <?= !$isPublicProfile ? 'disabled' : '' ?>>
                                        <label class="form-check-label" for="fcm-enabled-<?= $steamId ?>">
                                            启用推送通知
                                            <?php if (!$isPublicProfile): ?>
                                                <small class="text-warning">（需要公开Steam资料）</small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    
                                    <div class="ms-4">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="notify_online" 
                                                id="notify-online-<?= $steamId ?>" 
                                                <?= $fcmSettings['notify_online'] ? 'checked' : '' ?>
                                                <?= !$fcmSettings['enabled'] ? 'disabled' : '' ?>>
                                            <label class="form-check-label" for="notify-online-<?= $steamId ?>">
                                                通知在线状态变化
                                            </label>
                                        </div>
                                        
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="notify_gaming" 
                                                id="notify-gaming-<?= $steamId ?>" 
                                                <?= $fcmSettings['notify_gaming'] ? 'checked' : '' ?>
                                                <?= !$fcmSettings['enabled'] ? 'disabled' : '' ?>>
                                            <label class="form-check-label" for="notify-gaming-<?= $steamId ?>">
                                                通知游戏开始/结束
                                            </label>
                                        </div>
                                        
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="notify_away" 
                                                id="notify-away-<?= $steamId ?>" 
                                                <?= $fcmSettings['notify_away'] ? 'checked' : '' ?>
                                                <?= !$fcmSettings['enabled'] ? 'disabled' : '' ?>>
                                            <label class="form-check-label" for="notify-away-<?= $steamId ?>">
                                                通知离开状态变化
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-sm btn-primary mt-2">保存设置</button>
                                </form>
                                
                                <!-- 显示已注册设备 -->
                                <div class="mt-3">
                                    <h6>已注册设备 (<?= count($fcmSettings['tokens'] ?? []) ?>)</h6>
                                    <ul class="list-group">
                                        <?php foreach ($fcmSettings['tokens'] ?? [] as $token): ?>
                                            <li class="list-group-item py-1">
                                                <small class="font-monospace"><?= substr($token, 0, 6) ?>...<?= substr($token, -6) ?></small>
                                                <button class="btn btn-sm btn-danger float-end" 
                                                        onclick="if(confirm('确定要删除这个设备吗？')) { location.href='fcm_unregister.php?steam_id=<?= $steamId ?>&token=<?= urlencode($token) ?>'; }">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <!-- 添加JavaScript处理表单状态 -->
                        <script>
                        document.querySelectorAll('.fcm-settings-form input[type="checkbox"]').forEach(checkbox => {
                            // 主开关的联动控制
                            if (checkbox.name === 'fcm_enabled') {
                                checkbox.addEventListener('change', function() {
                                    const subCheckboxes = this.closest('form').querySelectorAll('input[type="checkbox"]:not([name="fcm_enabled"])');
                                    subCheckboxes.forEach(cb => {
                                        cb.disabled = !this.checked;
                                        if (!this.checked) cb.checked = false;
                                    });
                                });
                            }
                        });
                        </script>
                        </div>                        
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($steamIds)): ?>
                    <div class="alert alert-info mt-4">
                        请添加Steam用户ID来开始监控。
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <a href="?refresh=all" class="btn btn-steam btn-lg rounded-circle refresh-btn">
        <i class="fas fa-sync-alt"></i>
    </a>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // 每60秒自动刷新页面（仅查看今日时自动刷新）
        <?php if ($selectedDate === date('Y-m-d')): ?>
        setTimeout(function() {
            window.location.reload();
        }, 60000);
        <?php endif; ?>

        // 愿望单折叠动画
        document.querySelectorAll('.wishlist-title').forEach(function(title){
            title.addEventListener('click', function(){
                title.classList.toggle('wishlist-expanded');
                title.classList.toggle('wishlist-collapsed');
            });
        });
    </script>
</body>
</html>