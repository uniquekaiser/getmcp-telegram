<?php
if (!defined('GETMCP_TELEGRAM_TESTING') || wp_get_environment_type() !== 'local') { throw new RuntimeException('Local fixture only.'); }
wp_set_current_user(get_user_by('login', 'fixture-admin')->ID);
$sm = new \GetMCP\Core\ServerManager();
$count = 0;
foreach (['google-ads','google-analytics','google-search-console','meta-ads','instagram'] as $slug) {
    $server = $sm->create(['name'=>'Fixture marketing backend QA','status'=>'draft','auth_type'=>'none']);
    try {
        $r = new WP_REST_Request('POST', '/getmcp/v1/templates/synergetic-' . $slug . '/install');
        $r->set_header('content-type','application/json'); $r->set_body(wp_json_encode(['server_id'=>$server->uuid]));
        $response = rest_do_request($r);
        if ($response->get_status() !== 201) { throw new RuntimeException($slug . ': ' . wp_json_encode($response->get_data())); }
        $saved = $sm->get($server->id); $settings = json_decode($saved->settings, true);
        if ($saved->status !== 'draft' || $saved->auth_type !== 'oauth' || !empty($settings['personal_provider']['allowed_user_ids']) || $saved->auth_credentials || $saved->outbound_auth_credentials) { throw new RuntimeException('Unsafe template defaults: ' . $slug); }
        $count++;
    } finally { $sm->delete($server->id); }
}
// Clean only explicitly named disposable browser drafts, including partial failures.
global $wpdb;
foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}getmcp_servers WHERE name=%s", 'Fixture marketing catalogue QA')) as $id) { $sm->delete((int)$id); }
echo "PASS $count marketing templates install as drafts with empty allowlists and no credentials; disposable drafts removed\n";
