<?php
namespace Synergetic\Telegram;
final class Integration {
    public static function server(array $row): ?\GetMCP\Core\Server {
        if (!$row['server_id']) { return null; }
        $server = (new \GetMCP\Core\ServerManager())->get((int)$row['server_id']);
        if (!$server) { throw new Fault('The linked GetMCP server was removed.', 409); }
        $settings = json_decode($server->settings ?? '{}', true) ?: [];
        if ((int)($settings['telegram_connection_id'] ?? 0) !== (int)$row['id']) { throw new Fault('GetMCP server ownership changed. Refusing to overwrite its configuration.', 409); }
        return $server;
    }
    public static function publish(array $row): array {
        Diagnostics::assertReady();
        if ($row['state'] !== 'connected' || !$row['verified_at']) { throw new Fault('Connect the account and verify its identity before publishing.'); }
        if (!current_user_can('getmcp_manage_servers') || !current_user_can('getmcp_manage_tools')) { throw new Fault('You cannot manage GetMCP servers and tools.', 403); }
        $manager = new \GetMCP\Core\ServerManager(); $tm = new \GetMCP\Core\ToolManager();
        $server = self::server($row); $tier = \GetMCP\Licensing\LicenseTier::current();
        if (!$tier->allows_auth_method('oauth') || (!$server && !$tier->can_create_server($manager->get_count()))) { throw new Fault('Your GetMCP plan does not include another server with OAuth.', 403); }
        $catalog = Catalog::all();
        // The whole catalog counts, including draft mutations; never evade the tool limit.
        if (!$server && !$tier->can_create_tool(count($catalog) - 1)) { throw new Fault('Your GetMCP plan needs capacity for ' . count($catalog) . ' Telegram tools.', 403); }
        if ($server) {
            $existing = $tm->get_by_server($server->id, ['per_page' => 100]);
            $names = array_map(static fn($t) => $t->name, $existing['items']);
            $missing = count(array_diff(array_keys($catalog), $names));
            if ($missing && !$tier->can_create_tool((int)$existing['total'] + $missing - 1)) { throw new Fault('Your GetMCP plan does not allow the missing Telegram tools to be added.', 403); }
        }
        $credential = bin2hex(random_bytes(32));
        if (!$server) {
            $server = $manager->create([
                'name' => $row['name'], 'slug' => 'telegram-' . $row['id'], 'status' => 'paused',
                'auth_type' => 'oauth', 'auth_config' => wp_json_encode(['provider' => 'getmcp', 'allowed_user_ids' => [(int)$row['owner_id']]]),
                'settings' => wp_json_encode(['telegram_connection_id' => (int)$row['id'], 'gateway_enabled' => false, 'instructions' => 'This server uses its owner’s personal Telegram account. Gateway membership delegates access to that account. Writes require explicit confirmation; never retry a mutation automatically. Complete AGPL source: ' . Plugin::sourceUrl()]),
                'outbound_auth_type' => 'bearer', 'outbound_auth_credentials' => wp_json_encode(['token' => $credential]),
            ]);
            if (!$server) { throw new Fault('Could not create the native GetMCP server.', 500); }
            $row = Store::update((int)$row['id'], ['server_id' => $server->id]);
        } else {
            $manager->update($server->id, ['status' => 'paused', 'outbound_auth_type' => 'bearer', 'outbound_auth_credentials' => wp_json_encode(['token' => $credential])]);
        }
        Store::update((int)$row['id'], ['bridge_hash' => '']); // Fail closed while synchronizing.
        $tools = $tm->get_by_server($server->id, ['per_page' => 100]);
        $prior = []; foreach ($tools['items'] ?? $tools as $tool) { if (is_object($tool)) { $prior[$tool->name] = $tool; } }
        foreach ($catalog as $name => $def) {
            $mapping = []; foreach ((array)$def['schema']['properties'] as $key => $_) { $mapping[$key] = ['target' => 'body', 'key' => $key]; }
            $data = ['server_id' => $server->id, 'name' => $name, 'description' => $def['description'] . ' Complete AGPL source: ' . Plugin::sourceUrl(),
                'endpoint_url' => rest_url('getmcp-telegram/v1/bridge/' . $row['id'] . '/' . $name), 'http_method' => 'POST',
                'input_schema' => $def['schema'], 'parameter_mapping' => $mapping,
                'cache_ttl' => 0, 'retry_count' => 0, 'timeout' => 60,
                'annotations' => ['readOnlyHint' => !$def['write'], 'destructiveHint' => $def['write'], 'idempotentHint' => !$def['write'], 'openWorldHint' => true],
                'status' => !$def['write'] || $row['writes_enabled'] ? 'active' : 'draft'];
            $saved = isset($prior[$name]) ? $tm->update($prior[$name]->id, $data) : $tm->create($data);
            if (!$saved) { throw new Fault('Could not save Telegram tool ' . $name . '. The server remains paused.', 500); }
        }
        Store::update((int)$row['id'], ['bridge_hash' => hash('sha256', $credential)]);
        if (!$manager->update($server->id, ['status' => 'active'])) { Store::update((int)$row['id'], ['bridge_hash' => '']); throw new Fault('The server could not be activated.', 500); }
        return Store::present(Store::get((int)$row['id']));
    }
    public static function pause(array $row): void {
        $server = self::server($row);
        if ($server) { (new \GetMCP\Core\ServerManager())->update($server->id, ['status' => 'paused']); }
    }
    public static function writes(array $row, bool $enabled): array {
        if ($enabled && ($row['state'] !== 'connected' || !$row['verified_at'])) { throw new Fault('Verify the connected account before enabling writes.'); }
        $server = self::server($row);
        // Change the bridge gate first; native UI status cannot bypass it.
        $row = Store::update((int)$row['id'], ['writes_enabled' => $enabled ? 1 : 0]);
        if ($server) {
            $tm = new \GetMCP\Core\ToolManager(); $tools = $tm->get_by_server($server->id, ['per_page' => 100]);
            foreach ($tools['items'] ?? $tools as $tool) {
                if (is_object($tool) && !empty(Catalog::all()[$tool->name]['write'])) { if (!$tm->update($tool->id, ['status' => $enabled ? 'active' : 'draft'])) { Store::update((int)$row['id'], ['writes_enabled' => 0]); throw new Fault('Could not synchronize write tools; writes are disabled.', 500); } }
            }
        }
        return Store::present($row);
    }
}
