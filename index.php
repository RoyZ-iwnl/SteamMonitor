<?php
require_once 'config.php';

// 获取监控的Steam用户列表
$configFile = DATA_DIR . "/monitored_users.json";
$steamIds = [];
if (file_exists($configFile)) {
    $steamIds = json_decode(file_get_contents($configFile), true);
}

// 处理日期范围选择
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// 获取选定的Steam ID
$selectedId = $_GET['steam_id'] ?? ($steamIds[0] ?? '');

$userData = null;
$gameRecords = null;
$hiddenGaming = null;
$wishlistData = null;
$historicalData = null;

if ($selectedId) {
    $userData = getSteamUserData($selectedId);
    if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $historicalData = getHistoryRecordsByDateRange($selectedId, $startDate, $endDate);
    } else {
        $gameRecords = getUserDailyRecord($selectedId);
    }
    $hiddenGaming = detectHiddenGaming($selectedId);
    $wishlistData = getWishlistChanges($selectedId);
}

// HTML头部
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Steam 监控系统</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <style>
        .hidden-gaming-alert {
            border-left: 4px solid #dc3545;
            background-color: #fff;
            padding: 15px;
            margin-bottom: 15px;
        }
        .wishlist-item {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .wishlist-new {
            border-left: 4px solid #28a745;
        }
        .wishlist-header {
            cursor: pointer;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .history-controls {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <h1>Steam 监控系统</h1>
    
    <!-- 用户选择 -->
    <div class="mb-4">
        <form class="row g-3">
            <div class="col-auto">
                <select name="steam_id" class="form-select" onchange="this.form.submit()">
                    <option value="">选择用户...</option>
                    <?php foreach ($steamIds as $id): ?>
                        <?php $name = getSteamUserData($id)['personaname'] ?? $id; ?>
                        <option value="<?= htmlspecialchars($id) ?>" <?= $id === $selectedId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- 日期范围选择 -->
            <div class="col-auto">
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="col-auto">
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">查看历史记录</button>
            </div>
        </form>
    </div>

    <?php if ($selectedId && $userData): ?>
        <!-- 用户信息 -->
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="card-title">
                    <img src="<?= htmlspecialchars($userData['avatarmedium']) ?>" alt="avatar" class="rounded me-2">
                    <?= htmlspecialchars($userData['personaname']) ?>
                </h2>
                <p class="card-text">
                    状态: <?= getPersonaState($userData['personastate']) ?>
                    <?php if (isset($userData['gameextrainfo'])): ?>
                        | 当前游戏: <?= htmlspecialchars($userData['gameextrainfo']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- 隐身游戏检测 -->
        <?php if (!empty($hiddenGaming)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>可能的隐身游戏行为</h3>
                </div>
                <div class="card-body">
                    <?php foreach ($hiddenGaming as $game): ?>
                        <div class="hidden-gaming-alert">
                            <h4><?= htmlspecialchars($game['game_name']) ?></h4>
                            <p>最后运行时间: <?= htmlspecialchars($game['last_played_date']) ?></p>
                            <p>Steam记录时长: <?= round($game['steam_minutes'], 1) ?>分钟</p>
                            <p>本地记录时长: <?= round($game['recorded_minutes'], 1) ?>分钟</p>
                            <p>可能的隐身时长: <?= round($game['possible_hidden_minutes'], 1) ?>分钟</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 愿望单监控 -->
        <?php if ($wishlistData): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>愿望单监控 
                        <?php if (!empty($wishlistData['changes']['new_items'])): ?>
                            <span class="badge bg-success">新增 <?= count($wishlistData['changes']['new_items']) ?></span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="wishlist-header" onclick="toggleWishlist()">
                        <h4>
                            愿望单游戏 (<?= count($wishlistData['wishlist']) ?>)
                            <span class="float-end" id="wishlist-toggle">▼</span>
                        </h4>
                    </div>
                    <div id="wishlist-content" style="display: <?= !empty($wishlistData['changes']['new_items']) ? 'block' : 'none' ?>;">
                        <?php foreach ($wishlistData['wishlist'] as $appId => $game): ?>
                            <div class="wishlist-item <?= in_array($game, $wishlistData['changes']['new_items']) ? 'wishlist-new' : '' ?>">
                                <h5><?= htmlspecialchars($game['name']) ?></h5>
                                <p>价格: ¥<?= number_format($game['subs'][0]['price'] / 100, 2) ?></p>
                                <?php if (in_array($game, $wishlistData['changes']['new_items'])): ?>
                                    <span class="badge bg-success">新增</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 游戏记录 -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><?= isset($_GET['start_date']) ? '历史记录' : '今日记录' ?></h3>
            </div>
            <div class="card-body">
                <?php
                $records = $historicalData ?? [$gameRecords];
                foreach ($records as $date => $dayRecord):
                    if (empty($dayRecord['records'])) continue;
                ?>
                    <h4><?= $date ?></h4>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>游戏</th>
                                <th>开始时间</th>
                                <th>结束时间</th>
                                <th>持续时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dayRecord['records'] as $record): ?>
                                <tr>
                                    <td><?= htmlspecialchars($record['game_name']) ?></td>
                                    <td><?= date('H:i:s', $record['start']) ?></td>
                                    <td><?= $record['end'] ? date('H:i:s', $record['end']) : '进行中' ?></td>
                                    <td>
                                        <?php
                                        $duration = ($record['end'] ?? time()) - $record['start'];
                                        echo floor($duration / 3600) . '小时 ' . floor(($duration % 3600) / 60) . '分钟';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleWishlist() {
    const content = document.getElementById('wishlist-content');
    const toggle = document.getElementById('wishlist-toggle');
    if (content.style.display === 'none') {
        content.style.display = 'block';
        toggle.textContent = '▼';
    } else {
        content.style.display = 'none';
        toggle.textContent = '▶';
    }
}

// 添加辅助函数
function getPersonaState(state) {
    const states = {
        0: '离线',
        1: '在线',
        2: '忙碌',
        3: '离开',
        4: '打盾',
        5: '查找交易',
        6: '查找组队'
    };
    return states[state] || '未知';
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>