<?php
/** Remove only disposable test records; preserve the connected browser account. */
if (!defined('GETMCP_TELEGRAM_TESTING') || !GETMCP_TELEGRAM_TESTING || wp_get_environment_type() !== 'local') { throw new RuntimeException('Local synthetic fixture only.'); }
global $wpdb;
$rows = $wpdb->get_results("SELECT id,name,state,server_id,session_id FROM {$wpdb->prefix}getmcp_telegram", ARRAY_A);
$keepers = array_values(array_filter($rows, fn($r) => $r['name'] === 'Browser QA account' && $r['state'] === 'connected'));
if (count($keepers) !== 1) { throw new RuntimeException('Expected exactly one connected Browser QA account; no cleanup performed.'); }
foreach ($rows as $row) {
    if ($row['id'] === $keepers[0]['id']) { continue; }
    if (!in_array($row['name'], ['Synthetic integration', 'Second synthetic identity'], true)) { throw new RuntimeException('Unexpected account: ' . $row['name'] . '; refusing broad cleanup.'); }
}
$sm = new \GetMCP\Core\ServerManager();
foreach ($rows as $row) {
    if ($row['id'] === $keepers[0]['id']) { continue; }
    if ($row['server_id']) { $server = $sm->get((int)$row['server_id']); if ($server && (json_decode($server->settings, true)['telegram_connection_id'] ?? 0) == $row['id']) { $sm->delete((int)$server->id); } }
    delete_option('gmt_fake_' . $row['session_id']);
    $wpdb->delete($wpdb->prefix . 'getmcp_telegram', ['id' => $row['id']], ['%d']);
}
// These exact server names are generated only by our disposable test harness.
foreach ($wpdb->get_results("SELECT id,name FROM {$wpdb->prefix}getmcp_servers", ARRAY_A) as $server) {
    if ((int)$server['id'] !== (int)$keepers[0]['server_id'] && in_array($server['name'], ['Synthetic clone refusal', 'Synthetic Telegram gateway'], true)) { $sm->delete((int)$server['id']); }
}
echo wp_json_encode(['remaining' => $wpdb->get_results("SELECT id,name,state,server_id FROM {$wpdb->prefix}getmcp_telegram", ARRAY_A)]) . "\n";
