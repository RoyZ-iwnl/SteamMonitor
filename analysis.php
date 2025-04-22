<?php
require_once 'config.php';

$steamId = $_GET['id'] ?? '';
if (empty($steamId)) {
    header('Location: index.php');
    exit;
}

$userData = getSteamUserData($steamId);
if (!$userData) {
    header('Location: index.php');
    exit;
}

$analysis = analyzeUserHabits($steamId, 30); // 分析最近30天的数据
$wishlist = getUserWishlist($steamId);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户分析 - <?php echo $userData['personaname']; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h1 class="mb-4">
                    <a href="index.php" class="btn btn-outline-light me-2"><i class="fas fa-arrow-left"></i></a>
                    用户分析 - <?php echo $userData['personaname']; ?>
                </h1>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                每周游戏时间分布
                            </div>
                            <div class="card-body">
                                <canvas id="weeklyChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                每日峰值时间
                            </div>
                            <div class="card-body">
                                <canvas id="peakHoursChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        游戏统计
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>最常玩的游戏</h5>
                                <ul class="list-group">
                                    <?php foreach ($analysis['most_played_games'] as $gameId => $game): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo $game['name']; ?>
                                            <span class="badge bg-primary rounded-pill">
                                                <?php echo gmdate('H:i:s', $game['total_time']); ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h5>基本统计</h5>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        总游戏时间
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo gmdate('H:i:s', $analysis['total_gaming_time']); ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        平均每次游戏时长
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo gmdate('H:i:s', $analysis['average_session']); ?>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($wishlist): ?>
                <div class="card">
                    <div class="card-header">
                        愿望单
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-dark table-striped">
                                <thead>
                                    <tr>
                                        <th>游戏名称</th>
                                        <th>添加时间</th>
                                        <th>价格</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wishlist as $item): ?>
                                        <tr>
                                            <td><?php echo $item['name']; ?></td>
                                            <td><?php echo date('Y-m-d', $item['added']); ?></td>
                                            <td><?php echo isset($item['subs'][0]['price']) ? 
                                                number_format($item['subs'][0]['price'] / 100, 2) . ' ' . 
                                                ($item['subs'][0]['currency'] ?? 'USD') : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // 周使用时间分布图表
        const weeklyData = <?php echo json_encode(array_map(function($day) {
            return $day['total'] > 0 ? round($day['total'] / 3600, 1) : 0;
        }, $analysis['weekly_stats'])); ?>;
        
        new Chart(document.getElementById('weeklyChart'), {
            type: 'bar',
            data: {
                labels: ['周一', '周二', '周三', '周四', '周五', '周六', '周日'],
                datasets: [{
                    label: '游戏时间（小时）',
                    data: Object.values(weeklyData),
                    backgroundColor: '#66c0f4'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // 峰值时间图表
        new Chart(document.getElementById('peakHoursChart'), {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, i) => `${i}:00`),
                datasets: [{
                    label: '活动次数',
                    data: <?php echo json_encode($analysis['peak_hours']); ?>,
                    borderColor: '#66c0f4',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>