<?php
namespace Synergetic\Telegram;
final class Plugin {
    private static ?int $executingTool = null;
    private static array $pendingBodies = [];
    public static function sourceUrl(): string { return plugins_url('source/getmcp-telegram-' . GETMCP_TELEGRAM_VERSION . '-source.zip', GETMCP_TELEGRAM_PATH . 'getmcp-telegram.php'); }
    public static function boot(): void {
        AdminIntegration::boot();
        if ((int)get_option('getmcp_telegram_schema', 0) !== 1) { Store::migrate(); }
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('admin_menu', static function (): void { add_submenu_page('getmcp', 'Telegram Accounts', 'Telegram Accounts', 'read', 'getmcp-telegram', [self::class, 'page']); });
        add_filter('rest_post_dispatch', static function ($response, $server, $request) {
            if (str_starts_with($request->get_route(), '/getmcp-telegram/v1/') && $response instanceof \WP_REST_Response) { $response->header('Cache-Control', 'private, no-store, max-age=0'); $response->header('Pragma', 'no-cache'); }
            return $response;
        }, 10, 3);
        add_action('delete_user', [self::class, 'removeUser']);
        add_action('getmcp_before_tool_call', [self::class, 'beforeTool'], 999, 1);
        $clear = static function ($tool): void { if (self::$executingTool === (int)$tool->id) { self::$executingTool = null; } };
        add_action('getmcp_after_tool_call', $clear, -999, 1);
        add_action('getmcp_tool_call_failed', $clear, -999, 1);
        $noCache = static function ($settings) {
            if (self::$executingTool !== null) { $settings = is_array($settings) ? $settings : []; $settings['default_cache_ttl'] = 0; }
            return $settings;
        };
        add_filter('option_getmcp_settings', $noCache, PHP_INT_MAX);
        add_filter('default_option_getmcp_settings', $noCache, PHP_INT_MAX);
        add_filter('getmcp_tool_endpoint_url', static function ($url, $tool, $arguments) {
            $server = (new \GetMCP\Core\ServerManager())->get((int)$tool->server_id);
            $settings = $server ? json_decode($server->settings ?? '{}', true) : [];
            if (!empty($settings['telegram_connection_id']) && isset(Catalog::all()[$tool->name]) && $tool->endpoint_url === rest_url('getmcp-telegram/v1/bridge/' . (int)$settings['telegram_connection_id'] . '/' . $tool->name)) {
                // Native ParameterResolver parses strings starting with {/[ as JSON.
                // Preserve exact Telegram message text, whitespace and declared types.
                self::$pendingBodies[$url] = wp_json_encode($arguments ?: (object)[]);
            }
            return $url;
        }, PHP_INT_MAX, 3);
        add_filter('http_request_args', static function ($args, $url) {
            if (isset(self::$pendingBodies[$url])) {
                $args['body'] = self::$pendingBodies[$url]; unset(self::$pendingBodies[$url]);
                $args['headers']['Content-Type'] = 'application/json';
            }
            return $args;
        }, PHP_INT_MAX, 2);
        add_filter('getmcp_outbound_request_headers', static function ($headers, $tool) {
            $server = (new \GetMCP\Core\ServerManager())->get((int)$tool->server_id);
            $settings = $server ? json_decode($server->settings ?? '{}', true) : [];
            if (empty($settings['telegram_connection_id'])) { return $headers; }
            $id = (int)$settings['telegram_connection_id'];
            if ((int)Store::get($id)['server_id'] !== (int)$server->id) { throw new Fault('This Telegram connection is linked to another GetMCP server. Create a separate connection instead of cloning its account credentials.', 403); }
            if (!isset(Catalog::all()[$tool->name]) || $tool->endpoint_url !== rest_url('getmcp-telegram/v1/bridge/' . $id . '/' . $tool->name)) { return $headers; }
            // The native inspector uses test credentials; first-party OAuth deliberately
            // has none. Attach this bridge's stored outbound key to its exact local URL.
            $credentials = json_decode(\GetMCP\Utils\Encryption::decrypt_strict($server->outbound_auth_credentials), true);
            if (!is_array($credentials) || !preg_match('/^[a-f0-9]{64}$/D', $credentials['token'] ?? '')) { throw new Fault('Synchronize this Telegram server to restore its bridge credential.', 409); }
            $headers['Authorization'] = 'Bearer ' . $credentials['token'];
            return $headers;
        }, 999, 2);
        add_filter('getmcp_gateway_servers', static function ($servers) {
            // Original /mcp publication is deliberate, including when its mode is "all".
            $selected = \GetMCP\Gateway\McpGateway::selected_server_ids();
            return array_values(array_filter($servers, static function ($s) use ($selected) {
                $settings = json_decode($s->settings ?? '{}', true) ?: [];
                return empty($settings['telegram_connection_id']) || (\GetMCP\Gateway\McpGateway::mode() === 'selected' && in_array((int)$s->id, $selected, true));
            }));
        }, PHP_INT_MAX);
    }
    private static function respond(callable $fn): mixed {
        try { return rest_ensure_response($fn()); }
        catch (\Throwable $e) { $error = Normalize::error($e); return new \WP_Error($error->reason, $error->getMessage(), ['status' => $error->status]); }
    }
    public static function beforeTool($tool): void {
        self::$executingTool = null;
        $server = (new \GetMCP\Core\ServerManager())->get((int)$tool->server_id);
        $settings = $server ? json_decode($server->settings ?? '{}', true) : [];
        if (empty($settings['telegram_connection_id'])) { return; }
        $row = Store::get((int)$settings['telegram_connection_id']);
        if ((int)$row['server_id'] !== (int)$tool->server_id) { throw new Fault('This Telegram connection is linked to another GetMCP server.', 403); }
        self::assertBridge($row, (string)$tool->name);
        // GetMCP interprets cache_ttl=0 as "inherit". Suppress the global default
        // only during this tool call; stored site settings remain untouched.
        $tool->cache_ttl = 0; $tool->retry_count = 0;
        self::$executingTool = (int)$tool->id;
    }
    public static function routes(): void {
        $user = static fn() => is_user_logged_in() && current_user_can('read');
        $manage = static fn() => is_user_logged_in() && current_user_can('getmcp_manage_servers') && current_user_can('getmcp_manage_tools');
        register_rest_route('getmcp-telegram/v1', '/status', ['methods' => 'GET', 'permission_callback' => $user, 'callback' => fn() => self::respond(fn() => Diagnostics::inspect() + ['synthetic_driver' => (bool)apply_filters('getmcp_telegram_test_driver_active', false), 'connections' => Store::listing(), 'can_create' => $manage(), 'tools' => array_map(static fn($d) => ['name' => $d['name'], 'description' => $d['description'], 'write' => $d['write']], array_values(Catalog::all()))])]);
        register_rest_route('getmcp-telegram/v1', '/connections', ['methods' => 'POST', 'permission_callback' => $manage, 'callback' => fn($r) => self::respond(fn() => Store::present(Store::create($r->get_json_params() ?: [])))]);
        register_rest_route('getmcp-telegram/v1', '/connections/(?P<id>\d+)/(?P<action>login|verify|disconnect|publish|writes)', ['methods' => 'POST', 'permission_callback' => $user, 'callback' => fn($r) => self::respond(fn() => Store::locked((int)$r['id'], fn() => self::action($r)))]);
        register_rest_route('getmcp-telegram/v1', '/bridge/(?P<id>\d+)/(?P<tool>[a-z_]+)', ['methods' => 'POST', 'permission_callback' => fn($r) => self::bridgePermission($r), 'callback' => fn($r) => self::respond(fn() => Store::locked((int)$r['id'], fn() => self::bridge($r)))]);
    }
    public static function action(\WP_REST_Request $r): array {
        $row = Store::owned((int)$r['id']); $action = $r['action']; $data = $r->get_json_params() ?: [];
        // @wp-test feature=telegram.accounts scenario=owner-isolation
        if (in_array($action, ['publish', 'writes'], true)) {
            if (!current_user_can('getmcp_manage_servers') || !current_user_can('getmcp_manage_tools')) { throw new Fault('GetMCP management permissions are required.', 403); }
            if ($action === 'publish') {
                if (($data['acknowledge_delegation'] ?? null) !== true) { throw new Fault('Acknowledge that gateway membership grants access to this personal Telegram account.'); }
                return Integration::publish($row);
            }
            if (!is_bool($data['enabled'] ?? null) || ($data['enabled'] && ($data['confirm'] ?? null) !== true)) { throw new Fault('Confirm enabling Telegram writes.'); }
            return Integration::writes($row, $data['enabled']);
        }
        if ($action === 'disconnect') {
            // @wp-test feature=telegram.accounts scenario=disconnect
            $row = Store::update((int)$row['id'], ['bridge_hash' => '', 'writes_enabled' => 0, 'state' => 'disconnected', 'verified_at' => null]);
            Integration::pause($row);
            try { self::withClient($row, static fn($client) => $client->logout()); $revoked = true; }
            catch (\Throwable $e) { $revoked = false; }
            return Store::present($row) + ['telegram_revoked' => $revoked, 'notice' => $revoked ? 'Disconnected and Telegram session revoked.' : 'Local access disabled. Revoke this session in Telegram Settings → Devices if Telegram was unreachable.'];
        }
        if ($action === 'login') {
            $step = $data['step'] ?? ''; $value = $data['value'] ?? null;
            if (!is_string($value) || strlen($value) > 256 || !in_array($step, ['phone', 'code', 'password'], true)) { throw new Fault('Invalid login step.'); }
            if ($step === 'phone' && (!preg_match('/^\+[1-9][0-9]{6,14}$/D', $value) || $row['state'] === 'connected')) { throw new Fault('Use an international phone number for a disconnected account.'); }
            if ($step === 'code' && !preg_match('/^[0-9]{4,8}$/D', $value)) { throw new Fault('Enter the Telegram login code.'); }
            if ($step === 'password' && !$value) { throw new Fault('Enter the Telegram two-step password.'); }
            $state = self::withClient($row, static fn($client) => $client->login($step, $value));
            Store::update((int)$row['id'], ['state' => $state, 'verified_at' => null]);
            if ($state === 'signup_unsupported') { throw new Fault('Use an existing Telegram account. Account signup is not supported.'); }
        } elseif ($action === 'verify') {
            $self = self::withClient($row, static fn($client) => $client->invoke('get_me', []));
            if (!is_array($self) || !isset($self['id']) || !empty($self['bot'])) { throw new Fault('Could not verify a personal Telegram account.', 403); }
            Store::update((int)$row['id'], ['state' => 'connected', 'verified_at' => gmdate('Y-m-d H:i:s')]);
        }
        return Store::present(Store::get((int)$row['id']));
    }
    public static function bridgePermission(\WP_REST_Request $r): bool|\WP_Error {
        try {
            $row = Store::get((int)$r['id']); $header = $r->get_header('authorization');
            if (!$row['bridge_hash'] || !preg_match('/^Bearer ([a-f0-9]{64})$/D', $header, $m) || !hash_equals($row['bridge_hash'], hash('sha256', $m[1]))) { throw new Fault('Authenticate with this connection’s bridge credential.', 401); }
            self::assertBridge($row, (string)$r['tool']); return true;
        } catch (\Throwable $e) { $error = Normalize::error($e); return new \WP_Error($error->reason, $error->getMessage(), ['status' => $error->status]); }
    }
    private static function assertBridge(array $row, string $tool): array {
        Diagnostics::assertReady();
        $owner = get_userdata((int)$row['owner_id']);
        $server = Integration::server($row);
        if (!$owner || !user_can($owner, 'read') || !$server || $server->status !== 'active' || $row['state'] !== 'connected' || !$row['verified_at']) { throw new Fault('This Telegram connection is unavailable.', 403); }
        $def = Catalog::all()[$tool] ?? null;
        if (!$def) { throw new Fault('Unknown Telegram tool.', 404); }
        $tm = new \GetMCP\Core\ToolManager(); $tools = $tm->get_by_server($server->id, ['per_page' => 100]); $active = false;
        foreach ($tools['items'] ?? $tools as $t) { if (is_object($t) && $t->name === $tool && $t->status === 'active') { $active = true; } }
        if (!$active || ($def['write'] && !$row['writes_enabled'])) { throw new Fault('This Telegram tool is disabled.', 403); }
        return $def;
    }
    public static function bridge(\WP_REST_Request $r): array {
        $row = Store::get((int)$r['id']); self::assertBridge($row, (string)$r['tool']);
        // Recheck the credential after acquiring the lock: disconnect/rotation can race permission checks.
        $permitted = self::bridgePermission($r); if (is_wp_error($permitted)) { throw new Fault($permitted->get_error_message(), 403); }
        $args = $r->get_json_params() ?: []; Catalog::arguments((string)$r['tool'], $args);
        $key = 'gmt_rate_' . $row['id']; $rate = (int)get_transient($key);
        if ($rate >= 30) { throw new Fault('Telegram bridge rate limit reached. Wait one minute.', 429); }
        set_transient($key, $rate + 1, 60);
        $result = self::withClient($row, static fn($client) => $client->invoke((string)$r['tool'], $args));
        $normalized = Normalize::result($result);
        if (strlen(wp_json_encode($normalized)) > 2 * 1024 * 1024) { throw new Fault('Telegram result exceeded 2 MB. Request a smaller page.', 413); }
        return ['result' => $normalized, 'truncated' => Normalize::truncated(), 'source_url' => self::sourceUrl()];
    }
    public static function withClient(array $row, callable $callback): mixed {
        $client = null;
        $priorErrorHandler = set_error_handler(static fn() => false); restore_error_handler();
        try {
            // The filter is a local testing seam. No REST parameter can select a driver.
            $client = apply_filters('getmcp_telegram_client', null, $row);
            if ($client === null) { $client = new Client($row); }
            return $callback($client);
        } catch (\Throwable $e) {
            $fault = Normalize::error($e);
            if ($fault->status === 401) { Store::update((int)$row['id'], ['state' => 'disconnected', 'bridge_hash' => '', 'verified_at' => null, 'writes_enabled' => 0]); }
            throw $fault;
        } finally { try { if ($client) { $client->close(); } } finally { set_error_handler($priorErrorHandler); } }
    }
    public static function removeUser(int $userId): void {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}getmcp_telegram WHERE owner_id=%d", $userId), ARRAY_A);
        foreach ($rows as $row) { Store::update((int)$row['id'], ['state' => 'disconnected', 'bridge_hash' => '', 'writes_enabled' => 0, 'verified_at' => null]); try { Integration::pause($row); } catch (\Throwable $e) {} }
        // Preserve private files for explicit owner cleanup; never recursively delete computed paths.
    }
    public static function page(): void {
        $file = GETMCP_TELEGRAM_PATH . 'assets/admin.js';
        wp_enqueue_script('getmcp-telegram', plugins_url('assets/admin.js', GETMCP_TELEGRAM_PATH . 'getmcp-telegram.php'), [], substr(hash_file('sha256', $file), 0, 12), true);
        wp_localize_script('getmcp-telegram', 'GetMCPTelegram', ['root' => rest_url('getmcp-telegram/v1/'), 'nonce' => wp_create_nonce('wp_rest')]);
        wp_enqueue_style('getmcp-telegram', plugins_url('assets/admin.css', GETMCP_TELEGRAM_PATH . 'getmcp-telegram.php'), [], substr(hash_file('sha256', GETMCP_TELEGRAM_PATH . 'assets/admin.css'), 0, 12));
        echo '<div class="wrap gmt"><h1>Telegram Accounts</h1><p>Connect your personal Telegram account to native GetMCP tools.</p><div id="gmt-status" role="status"></div><div id="gmt-app">Loading…</div></div>';
        echo '<p class="wrap"><a href="' . esc_url(self::sourceUrl()) . '">Download the complete source code (AGPL)</a> · Developed by <a href="https://synergetic.dev/">Synergetic Dev</a></p>';
    }
}
