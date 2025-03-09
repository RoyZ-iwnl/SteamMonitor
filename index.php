<?php
// index.php - 主页面
require_once 'config.php';

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

// 检查是否需要重置记录
checkAndResetRecord();

// 更新所有用户的游戏状态
$allRecords = [];
$allHiddenGaming = [];

foreach ($steamIds as $steamId) {
    updateUserGameStatus($steamId);
    $allRecords[$steamId] = getUserDailyRecord($steamId);
    $allHiddenGaming[$steamId] = detectHiddenGaming($steamId);
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
                
                <?php foreach ($steamIds as $steamId): ?>
                    <?php 
                    $userData = getSteamUserData($steamId);
                    $records = $allRecords[$steamId] ?? ['records' => []];
                    $hiddenGaming = $allHiddenGaming[$steamId] ?? [];
                    
                    if (!$userData) continue;
                    
                    // 用户状态类
                    $statusClass = 'user-offline';
                    if (isset($userData['gameextrainfo'])) {
                        $statusClass = 'user-ingame';
                    } elseif ($userData['personastate'] > 0) {
                        $statusClass = 'user-online';
                    }
                    ?>
                    
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <img src="<?php echo $userData['avatarmedium']; ?>" alt="Avatar" class="rounded-circle me-2" width="32" height="32">
                                <span class="<?php echo $statusClass; ?>"><?php echo $userData['personaname']; ?></span>
                                <?php if (isset($userData['gameextrainfo'])): ?>
                                    <span class="ms-2 badge bg-success">正在游戏: <?php echo $userData['gameextrainfo']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="?refresh=<?php echo $steamId; ?>" class="btn btn-sm btn-outline-info me-2"><i class="fas fa-sync-alt"></i> 刷新</a>
                                <a href="?remove=<?php echo $steamId; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('确定要删除这个用户吗?');"><i class="fas fa-trash"></i> 删除</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5>今日游戏记录 (UTC+8)</h5>
                            
                            <?php if (empty($records['records'])): ?>
                                <p>今天还没有游戏记录。</p>
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
                            
                            <?php if (!empty($hiddenGaming)): ?>
                                <div class="mt-3 hidden-gaming">
                                    <h5><i class="fas fa-user-ninja"></i> 可能的隐身游戏</h5>
                                    <ul>
                                        <?php foreach ($hiddenGaming as $hidden): ?>
                                            <li>
                                                <?php echo $hidden['game_name']; ?> - 
                                                可能隐身时长: <?php echo gmdate('H:i:s', $hidden['possible_hidden_minutes'] * 60); ?>
                                                (Steam: <?php echo $hidden['steam_minutes']; ?>分钟, 记录: <?php echo round($hidden['recorded_minutes']); ?>分钟)
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
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
        // 每60秒自动刷新页面
        setTimeout(function() {
            window.location.reload();
        }, 60000);
    </script>
</body>
</html>