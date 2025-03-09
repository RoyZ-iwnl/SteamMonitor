# Steam游戏时间监控系统

本系统用于监控Steam用户的游戏行为，包括开始和结束游戏的时间，并尝试检测隐身游戏行为。以下是安装和配置步骤。

[English Version](README_EN.md)

## 系统需求

- PHP 7.0+
- LNMP环境 (Linux, Nginx, MySQL, PHP)
- cURL PHP扩展
- 有效的Steam API密钥

## 安装步骤

1. 首先，在您的网站根目录创建一个新文件夹，例如 `steam-monitor`。

2. 将以下文件复制到该文件夹中：
   - `config.php` - 配置文件
   - `index.php` - 主页面
   - `cron.php` - 后台定时任务脚本
   - `api.php` - JSON API接口

3. 在服务器上创建 `data` 目录并确保PHP有写入权限：
   ```bash
   mkdir data
   chmod 755 data
   ```

4. 编辑 `config.php` 文件，设置您的Steam API密钥：
   ```php
   define('STEAM_API_KEY', '您的STEAM_API_KEY'); // 从 https://steamcommunity.com/dev/apikey 获取
   ```

5. 在 `api.php` 中设置您的API密钥：
   ```php
   if ($apiKey !== '设置你的API密钥') {
   ```

## 配置后台定时任务

为了确保系统即使没有用户访问也能持续监控Steam用户的游戏状态，需要设置一个cron任务：

1. 编辑crontab：
   ```bash
   crontab -e
   ```

2. 添加以下行来每5分钟执行一次cron.php：
   ```
   */5 * * * * php /path/to/your/website/steam-monitor/cron.php
   ```

3. 保存并退出。

## Nginx配置

确保您的Nginx配置正确设置以运行PHP文件。以下是一个示例配置：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/your/website/steam-monitor;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 使用说明

1. 访问您的网站域名即可打开监控页面。
2. 使用页面顶部的表单添加Steam用户ID进行监控。
3. 系统会自动每5分钟更新一次用户状态（通过cron）。
4. 页面会每60秒自动刷新，展示最新状态。
5. 每天上午10点（UTC+8），系统会自动清零重新开始记录。

## 安全注意事项

1. 确保 `data` 目录不能从Web直接访问。
2. 保护您的API密钥。
3. 定期更新PHP和系统组件。
