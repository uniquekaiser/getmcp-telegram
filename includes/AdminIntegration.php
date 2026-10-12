<?php
namespace Synergetic\Telegram;
/** Public WordPress adapters: no vendor bundle replacement or registry reflection. */
final class AdminIntegration {
    public static function boot(): void {
        add_action('admin_enqueue_scripts', static function (): void {
            $page = sanitize_key($_GET['page'] ?? '');
            if (!current_user_can('manage_options') || !str_starts_with($page, 'getmcp')) { return; }
            $file = 'assets/integration.js';
            wp_enqueue_script('getmcp-telegram-integration', plugins_url($file, GETMCP_TELEGRAM_PATH . 'getmcp-telegram.php'), [], substr(hash_file('sha256', GETMCP_TELEGRAM_PATH . $file), 0, 16), true);
            wp_add_inline_script('getmcp-telegram-integration', 'window.getmcpTelegramIntegration=' . wp_json_encode(['page' => $page, 'url' => admin_url('admin.php?page=getmcp-telegram'), 'version' => GETMCP_TELEGRAM_VERSION, 'count' => count(Catalog::all())]) . ';', 'before');
        }, 100);
        add_filter('rest_request_after_callbacks', [self::class, 'templates'], 30, 3);
    }
    public static function template(): array {
        return ['slug' => 'synergetic-telegram-personal', 'name' => 'Telegram · Personal account', 'description' => 'Connect your own Telegram account through GetMCP Telegram Extension. Configure and verify in Telegram Accounts before publishing; write tools start disabled.', 'category' => 'Productivity', 'icon' => 'MessageCircle', 'auth_type' => 'none', 'website' => 'https://synergetic.dev/', 'tags' => ['telegram', 'personal', 'synergetic'], 'tool_count' => count(Catalog::all()), 'setup_url' => admin_url('admin.php?page=getmcp-telegram'), 'tools' => array_values(Catalog::all())];
    }
    public static function templates($response, $handler, $request) {
        if (!current_user_can('manage_options')) { return $response; }
        $route = $request->get_route();
        if ($route === '/getmcp/v1/templates' && $request->get_method() === 'GET' && $response instanceof \WP_REST_Response && $response->get_status() === 200) {
            $items = $response->get_data(); $template = self::template(); unset($template['tools']);
            if (!in_array($template['slug'], array_column($items, 'slug'), true)) { $items[] = $template; }
            $response->set_data($items);
        } elseif (is_wp_error($response) && $response->get_error_code() === 'template_not_found' && $route === '/getmcp/v1/templates/synergetic-telegram-personal' && $request->get_method() === 'GET') {
            return rest_ensure_response(self::template());
        } elseif (is_wp_error($response) && $response->get_error_code() === 'template_not_found' && $route === '/getmcp/v1/templates/synergetic-telegram-personal/install') {
            return new \WP_Error('telegram_account_setup_required', 'Open GetMCP → Telegram Accounts, connect and verify your personal account, then synchronize its tools. This template does not create placeholder accounts.', ['status' => 409, 'setup_url' => admin_url('admin.php?page=getmcp-telegram')]);
        }
        return $response;
    }
}
