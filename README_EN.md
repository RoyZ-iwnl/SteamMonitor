# Steam Game Time Monitoring System

This system is used to monitor Steam users' gaming activity, including start and end times, and attempts to detect invisible gaming behavior. Below are the installation and configuration steps.

### Only Zh-CN language for now.

[中文版本](README.md)

## System Requirements

- PHP 7.0+
- LNMP environment (Linux, Nginx, MySQL, PHP)
- cURL PHP extension
- A valid Steam API key

## Installation Steps

1. First, create a new folder in your website root directory, e.g., `steam-monitor`.

2. Copy the following files into the folder:
   - `config.php` - Configuration file
   - `index.php` - Main page
   - `cron.php` - Background scheduled task script
   - `api.php` - JSON API interface

3. Create a `data` directory on the server and ensure PHP has write permissions:
   ```bash
   mkdir data
   chmod 755 data
   ```

4. Edit the `config.php` file to set your Steam API key:
   ```php
   define('STEAM_API_KEY', 'Your_STEAM_API_KEY'); // Obtain from https://steamcommunity.com/dev/apikey
   ```

5. Set your API key in `api.php`:
   ```php
   if ($apiKey !== 'Set_Your_API_Key') {
   ```

## Configure Scheduled Task (Cron)

To ensure the system continuously monitors Steam users' game status even without user access, set up a cron job:

1. Edit crontab:
   ```bash
   crontab -e
   ```

2. Add the following line to execute cron.php every 5 minutes:
   ```
   */5 * * * * php /path/to/your/website/steam-monitor/cron.php
   ```

3. Save and exit.

## Nginx Configuration

Ensure your Nginx configuration is set up correctly to run PHP files. Below is an example configuration:

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
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;  # Adjust based on your PHP version
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Usage Instructions

1. Access your website domain to open the monitoring page.
2. Use the form at the top of the page to add Steam user IDs for monitoring.
3. The system updates user status every 5 minutes (via cron).
4. The page refreshes automatically every 60 seconds to display the latest status.
5. At 10 AM UTC+8 daily, the system resets and starts recording anew.

## Obtain Steam ID

You can obtain your Steam ID through the following methods:

1. Open your Steam profile page.
2. Copy the ID part from the URL. For example:
   - From `https://steamcommunity.com/id/customURL/`, the ID is `customURL`.
   - From `https://steamcommunity.com/profiles/76561198012345678/`, the ID is `76561198012345678`.

## Security Considerations

1. Ensure the `data` directory is not directly accessible from the web, which can be restricted via Nginx configuration or .htaccess.
2. Protect your API key and do not share it with others.
3. Regularly update PHP and system components to fix security vulnerabilities.

## Possible Issues and Solutions

1. **Issue**: The page is blank
   **Solution**: Check PHP error logs, ensure PHP is configured correctly, and the `data` directory has write permissions.

2. **Issue**: Unable to retrieve Steam user data
   **Solution**: Verify that your Steam API key is valid and that the network can access the Steam API.

3. **Issue**: Cron job is not executing
   **Solution**: Check the cron logs.