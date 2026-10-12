<?php
/**
 * Plugin Name: GetMCP Telegram Extension
 * Plugin URI: https://github.com/uniquekaiser/getmcp-telegram
 * Update URI: https://github.com/uniquekaiser/getmcp-telegram
 * Description: Personal Telegram accounts through native GetMCP tools, without a companion application.
 * Version: 0.1.0
 * Author: Synergetic Dev
 * Author URI: https://synergetic.dev/
 * Requires PHP: 8.3
 * Requires at least: 6.2
 * Requires Plugins: getmcp, getmcp-extensions
 * License: AGPL-3.0-only
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain: getmcp-telegram
 */
if (!defined('ABSPATH')) { exit; }
define('GETMCP_TELEGRAM_PATH', __DIR__ . '/');
define('GETMCP_TELEGRAM_VERSION', '0.1.0');
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Synergetic\\Telegram\\')) {
        $file = __DIR__ . '/includes/' . substr($class, strlen('Synergetic\\Telegram\\')) . '.php';
        if (is_file($file)) { require_once $file; }
    }
});
add_action('plugins_loaded', ['Synergetic\\Telegram\\Plugin', 'boot'], 40);
add_action('plugins_loaded', ['Synergetic\\Telegram\\Updater', 'boot'], 45);
register_activation_hook(__FILE__, ['Synergetic\\Telegram\\Store', 'migrate']);
