<?php
namespace Synergetic\Telegram;
/** Secrets never appear in presentation data or autoloaded options. */
final class Store {
    public static function migrate(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$wpdb->prefix}getmcp_telegram (
          id bigint unsigned NOT NULL AUTO_INCREMENT,
          owner_id bigint unsigned NOT NULL,
          server_id bigint unsigned NOT NULL DEFAULT 0,
          name varchar(120) NOT NULL,
          state varchar(30) NOT NULL DEFAULT 'disconnected',
          session_id varchar(48) NOT NULL,
          api_credentials longtext NOT NULL,
          bridge_hash varchar(64) NOT NULL DEFAULT '',
          writes_enabled tinyint NOT NULL DEFAULT 0,
          verified_at datetime NULL,
          updated_at datetime NOT NULL,
          PRIMARY KEY (id), KEY owner (owner_id), UNIQUE KEY session (session_id)
        ) {$wpdb->get_charset_collate()};");
        update_option('getmcp_telegram_schema', 1, false);
    }
    public static function get(int $id): array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}getmcp_telegram WHERE id=%d", $id), ARRAY_A);
        if (!$row) { throw new Fault('Telegram connection not found.', 404); }
        return $row;
    }
    public static function owned(int $id): array {
        $row = self::get($id);
        if ((int)$row['owner_id'] !== get_current_user_id()) { throw new Fault('This is another user’s Telegram connection.', 403); }
        return $row;
    }
    public static function listing(): array {
        global $wpdb;
        return array_map([self::class, 'present'], $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}getmcp_telegram WHERE owner_id=%d ORDER BY id", get_current_user_id()), ARRAY_A));
    }
    public static function create(array $data): array {
        $name = sanitize_text_field($data['name'] ?? '');
        $apiId = $data['api_id'] ?? null; $hash = $data['api_hash'] ?? '';
        if (!is_int($apiId) || $apiId < 1 || $apiId > 2147483647 || !is_string($hash) || !preg_match('/^[a-f0-9]{32}$/D', $hash) || !$name || strlen($name) > 120) {
            throw new Fault('Enter a name, positive Telegram API ID and 32-character API hash.');
        }
        Diagnostics::assertReady();
        global $wpdb;
        $ok = $wpdb->insert($wpdb->prefix . 'getmcp_telegram', [
            'owner_id' => get_current_user_id(), 'name' => $name,
            'session_id' => bin2hex(random_bytes(24)),
            'api_credentials' => Vault::encrypt(['api_id' => $apiId, 'api_hash' => $hash]),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if (!$ok) { throw new Fault('Could not save the Telegram connection.', 500); }
        return self::get((int)$wpdb->insert_id);
    }
    public static function update(int $id, array $data): array {
        global $wpdb;
        $fields = array_intersect_key($data, array_flip(['state', 'server_id', 'bridge_hash', 'writes_enabled', 'verified_at']));
        $fields['updated_at'] = gmdate('Y-m-d H:i:s');
        if (false === $wpdb->update($wpdb->prefix . 'getmcp_telegram', $fields, ['id' => $id])) { throw new Fault('Could not update the Telegram connection.', 500); }
        return self::get($id);
    }
    public static function present(array $row): array {
        $data = array_intersect_key($row, array_flip(['id', 'name', 'owner_id', 'server_id', 'state', 'writes_enabled', 'verified_at', 'updated_at']));
        foreach (['id', 'owner_id', 'server_id'] as $key) { $data[$key] = (int)$data[$key]; }
        $data['writes_enabled'] = (bool)$data['writes_enabled'];
        if ($data['server_id'] && class_exists('GetMCP\\Core\\ServerManager')) {
            $server = (new \GetMCP\Core\ServerManager())->get($data['server_id']);
            $data['endpoint'] = $server ? $server->get_endpoint_url() : null;
            $data['published'] = $server && $server->status === 'active';
        }
        return $data;
    }
    public static function locked(int $id, callable $callback): mixed {
        global $wpdb;
        $key = 'gmt_' . substr(hash('sha256', $wpdb->prefix . $id), 0, 40);
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $key)) !== 1) { throw new Fault('This Telegram connection is busy. Try again after the current operation finishes.', 409); }
        try { return $callback(); }
        finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key)); }
    }
}
