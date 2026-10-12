<?php
use Synergetic\Telegram\{Catalog, Store, Plugin, Vault, Normalize, Integration};
if (!defined('GETMCP_TELEGRAM_TESTING')) { throw new RuntimeException('Run only in the local Telegram fixture.'); }
$GLOBALS['passes'] = 0; $GLOBALS['failures'] = [];
function check($value, string $label): void { global $passes, $failures; if ($value) { $passes++; echo "PASS $label\n"; } else { $failures[] = $label; echo "FAIL $label\n"; } }
function req(string $route, array $data = [], string $method = 'POST', string $token = ''): WP_REST_Response {
    $r = new WP_REST_Request($method, '/getmcp-telegram/v1/' . $route); $r->set_header('content-type', 'application/json');
    if ($token) { $r->set_header('authorization', 'Bearer ' . $token); }
    $r->set_body(wp_json_encode($data)); return rest_do_request($r);
}
$admin = get_user_by('login', 'fixture-admin'); wp_set_current_user($admin->ID);
$u2 = get_user_by('login', 'fixture-other'); if (!$u2) { $id = wp_create_user('fixture-other', 'fixture-only-other-password', 'other@example.test'); $u2 = get_userdata($id); }
check(\Synergetic\Telegram\Diagnostics::inspect()['ready'], 'hosting diagnostics');
$secret = ['api_id' => 12345, 'api_hash' => str_repeat('a', 32)];
$encrypted = Vault::encrypt($secret); check(Vault::decrypt($encrypted) === $secret && !str_contains($encrypted, $secret['api_hash']), 'credential encryption round trip');
try { Vault::decrypt(substr($encrypted, 0, -5) . 'aaaaa'); check(false, 'tamper rejection'); } catch (Throwable $e) { check(true, 'tamper rejection'); }
wp_set_current_user(0); check(req('connections', ['name' => 'Denied'] + $secret)->get_status() === 401, 'anonymous create denied');
wp_set_current_user($u2->ID); check(req('connections', ['name' => 'Denied'] + $secret)->get_status() === 403, 'subscriber create denied');
wp_set_current_user($admin->ID);
check(req('connections', ['name' => 'Broken', 'api_id' => 1, 'api_hash' => 'not-a-secret'])->get_status() === 400, 'invalid application credentials rejected');
$created = req('connections', ['name' => 'Synthetic integration'] + $secret); check($created->get_status() === 200, 'save account'); $id = $created->get_data()['id'];
check(!str_contains(wp_json_encode($created->get_data()), 'api_hash') && !str_contains(wp_json_encode($created->get_data()), 'session_id'), 'account response redaction');
wp_set_current_user($u2->ID); check(req("connections/$id/verify")->get_status() === 403, 'owner isolation direct request');
$otherListing = req('status', [], 'GET')->get_data()['connections'];
check(!in_array($id, array_column($otherListing, 'id'), true) && count(array_filter($otherListing, fn($entry) => $entry['owner_id'] !== (int)$u2->ID)) === 0, 'other user listing isolation');
wp_set_current_user($admin->ID);
check(req("connections/$id/login", ['step' => 'phone', 'value' => '+573001234567'])->get_data()['state'] === 'waiting_code', 'phone login stage');
$wrong = req("connections/$id/login", ['step' => 'code', 'value' => '00000']); check($wrong->get_status() === 400 && !str_contains(wp_json_encode($wrong->get_data()), '+573001234567'), 'invalid code secret-safe error');
check(req("connections/$id/login", ['step' => 'code', 'value' => '11111'])->get_data()['state'] === 'waiting_password', 'two-factor login stage');
check(req("connections/$id/login", ['step' => 'password', 'value' => 'fixture-only-2fa'])->get_data()['state'] === 'connected', 'two-factor completion');
check(req("connections/$id/publish", ['acknowledge_delegation' => true])->get_status() === 400, 'unverified publication denied');
check(req("connections/$id/verify")->get_data()['verified_at'] !== null, 'harmless account verification');
$tierClass = new ReflectionClass(\GetMCP\Licensing\LicenseTier::class); $savedTier = $tierClass->getProperty('cached')->getValue(); $deniedTier = $tierClass->newInstanceWithoutConstructor();
foreach (['id' => 'unlicensed', 'is_expired' => false, 'raw_plan' => 'Synthetic denial'] as $key => $value) { $tierClass->getProperty($key)->setValue($deniedTier, $value); } $tierClass->getProperty('cached')->setValue(null, $deniedTier);
check(req("connections/$id/publish", ['acknowledge_delegation' => true])->get_status() === 403, 'native entitlement gate rejects publication');
$tierClass->getProperty('cached')->setValue(null, $savedTier);
check(req("connections/$id/publish")->get_status() === 400, 'gateway delegation acknowledgment required');
$published = req("connections/$id/publish", ['acknowledge_delegation' => true]); check($published->get_status() === 200, 'native GetMCP publication');
if ($published->get_status() !== 200) { print_r($published->get_data()); exit(1); }
$row = Store::get($id); $sm = new \GetMCP\Core\ServerManager(); $server = $sm->get((int)$row['server_id']);
$outbound = (new \GetMCP\Execution\AuthInjector())->inject(['headers' => []], false, $server);
$token = substr($outbound['headers']['Authorization'] ?? '', 7);
check($token !== '', 'encrypted bridge credential readable by server');
$clone = $sm->create(['name' => 'Synthetic clone refusal', 'status' => 'active', 'auth_type' => 'oauth', 'auth_config' => $server->auth_config, 'settings' => $server->settings, 'outbound_auth_type' => 'bearer', 'outbound_auth_credentials' => wp_json_encode(['token' => $token])]);
$cloneTool = (new \GetMCP\Core\ToolManager())->create(['server_id' => $clone->id, 'name' => 'get_me', 'endpoint_url' => rest_url("getmcp-telegram/v1/bridge/$id/get_me"), 'http_method' => 'POST']);
try { apply_filters('getmcp_outbound_request_headers', [], $cloneTool); check(false, 'cloned server cannot inherit another account binding'); } catch (Throwable $e) { check($e instanceof \Synergetic\Telegram\Fault && $e->status === 403, 'cloned server cannot inherit another account binding'); }
$auth = json_decode($server->auth_config, true); check($auth['provider'] === 'getmcp' && $auth['allowed_user_ids'] === [(int)$admin->ID], 'explicit owner allowlist');
$tm = new \GetMCP\Core\ToolManager(); $tools = $tm->get_by_server($server->id, ['per_page' => 100]); $items = $tools['items'];
check(count($items) === count(Catalog::all()), 'all catalog tools registered');
check(count(array_filter($items, fn($t) => $t->cache_ttl || $t->retry_count)) === 0, 'native cache and retry disabled');
check(count(array_filter($items, fn($t) => Catalog::all()[$t->name]['write'] && $t->status !== 'draft')) === 0, 'writes start as drafts');
check(req("bridge/$id/get_me")->get_status() === 401, 'anonymous bridge denied');
check(req("bridge/$id/get_me", [], 'POST', str_repeat('b', 64))->get_status() === 401, 'wrong bridge identity rejected');
check(req("bridge/$id/get_me", [], 'POST', $token)->get_data()['result']['fixture'] === true, 'authenticated bridge read');
check(req("bridge/$id/get_history", ['peer' => '@synthetic', 'limit' => 101], 'POST', $token)->get_status() === 400, 'bounded history arguments');
check(req("bridge/$id/get_me", ['rpc_method' => 'account.deleteAccount'], 'POST', $token)->get_status() === 400, 'arbitrary RPC injection refused');
check(req("bridge/$id/send_message", ['peer' => '@synthetic', 'message' => 'fixture write', 'confirm' => true], 'POST', $token)->get_status() === 403, 'write gate enforced independently of schema');
check(req("connections/$id/writes", ['enabled' => true])->get_status() === 400, 'write enable needs confirmation');
check(req("connections/$id/writes", ['enabled' => true, 'confirm' => true])->get_status() === 200, 'write enable');
check(req("bridge/$id/send_message", ['peer' => '@synthetic', 'message' => 'fixture write'], 'POST', $token)->get_status() === 400, 'write call needs confirmation');
check(req("bridge/$id/send_message", ['peer' => '@synthetic', 'message' => 'fixture write', 'confirm' => true], 'POST', $token)->get_status() === 200, 'fixture mutation only');
$oldSettings = get_option('getmcp_settings', []); update_option('getmcp_settings', array_merge($oldSettings, ['default_cache_ttl' => 300]));
add_filter('getmcp_tool_endpoint_url', static fn($url) => str_replace('http://localhost:8932', 'https://wpdev.synergetic.dev', $url));
add_filter('pre_http_request', static function ($pre, $args, $url) {
    $path = wp_parse_url($url, PHP_URL_PATH);
    parse_str(wp_parse_url($url, PHP_URL_QUERY) ?? '', $query);
    $route = $query['rest_route'] ?? (str_starts_with($path, '/wp-json/') ? substr($path, strlen('/wp-json')) : '');
    if (str_starts_with($route, '/getmcp-telegram/v1/bridge/')) {
        $request = new WP_REST_Request($args['method'], $route);
        foreach ($args['headers'] as $key => $value) { $request->set_header($key, $value); }
        $request->set_body($args['body'] ?? '{}'); $response = rest_do_request($request);
        return ['headers' => [], 'body' => wp_json_encode($response->get_data()), 'response' => ['code' => $response->get_status(), 'message' => 'Fixture'], 'cookies' => [], 'filename' => null];
    }
    return new WP_Error('fixture_network_blocked', 'This test blocks all unexpected outbound HTTP requests.');
}, 10, 3);
$handler = new \GetMCP\Protocol\ToolsCallHandler($server, null);
$native = $handler->handle('tools/call', ['name' => 'get_history', 'arguments' => ['peer' => '@synthetic', 'limit' => 2]]);
check(empty($native['isError']) && str_contains(wp_json_encode($native), 'fixture'), 'native handler resolver credential injection and result transformation');
if (!empty($native['isError'])) { echo wp_json_encode($native['content']) . "\n"; }
check(get_option('getmcp_settings')['default_cache_ttl'] === 300, 'global cache setting preserved after native call');
$other = new WP_User($u2->ID); $other->set_role('administrator'); wp_set_current_user($other->ID);
check(req("connections/$id/verify")->get_status() === 403, 'administrators receive no owner bypass');
$second = req('connections', ['name' => 'Second synthetic identity'] + $secret)->get_data()['id'];
req("connections/$second/login", ['step' => 'phone', 'value' => '+573009876543']); req("connections/$second/login", ['step' => 'code', 'value' => '11111']); req("connections/$second/login", ['step' => 'password', 'value' => 'fixture-only-2fa']); req("connections/$second/verify");
$secondPublished = req("connections/$second/publish", ['acknowledge_delegation' => true]); check($secondPublished->get_status() === 200, 'second account publishes independently');
$secondRow = Store::get($second); $secondServer = $sm->get((int)$secondRow['server_id']); $secondRequest = (new \GetMCP\Execution\AuthInjector())->inject(['headers' => []], false, $secondServer); $secondToken = substr($secondRequest['headers']['Authorization'], 7);
check(req("bridge/$second/get_me", [], 'POST', $token)->get_status() === 401 && req("bridge/$id/get_me", [], 'POST', $secondToken)->get_status() === 401, 'cross-account credentials fail in both directions');
check(req("bridge/$second/get_me", [], 'POST', $secondToken)->get_data()['result']['id'] !== req("bridge/$id/get_me", [], 'POST', $token)->get_data()['result']['id'], 'distinct upstream identities');
check(Store::get($second)['session_id'] !== Store::get($id)['session_id'], 'separate private session paths');
req("connections/$second/disconnect"); $other->set_role('subscriber'); wp_set_current_user($admin->ID);
$nativeWrite = $handler->handle('tools/call', ['name' => 'send_message', 'arguments' => ['peer' => '@synthetic', 'message' => 'synthetic only', 'confirm' => true]]);
check(empty($nativeWrite['isError']) && str_contains(wp_json_encode($nativeWrite), 'synthetic only'), 'native fixture write body mapping');
if (!empty($nativeWrite['isError'])) { echo wp_json_encode($nativeWrite['content']) . "\n"; }
$jsonText = "  {\"hello\": [\"Telegram\", \"exact text\"]}\n";
$jsonMessage = $handler->handle('tools/call', ['name' => 'send_message', 'arguments' => ['peer' => '@synthetic', 'message' => $jsonText, 'confirm' => true]]);
$decodedMessage = json_decode($jsonMessage['content'][0]['text'] ?? '{}', true);
check(empty($jsonMessage['isError']) && ($decodedMessage['result']['args']['message'] ?? null) === $jsonText, 'native JSON-looking message text and whitespace preserved');
update_option('getmcp_settings', $oldSettings);
$originalMode = get_option('getmcp_gateway_mode', 'all'); $originalSelected = get_option('getmcp_gateway_servers', []);
update_option('getmcp_gateway_mode', 'all'); update_option('getmcp_gateway_servers', []);
check(!apply_filters('getmcp_gateway_servers', [$server]), 'original gateway requires explicit publication');
update_option('getmcp_gateway_mode', 'selected'); update_option('getmcp_gateway_servers', [$server->id]);
check(count(apply_filters('getmcp_gateway_servers', [$server])) === 1, 'original gateway explicit selection supported');
update_option('getmcp_gateway_mode', $originalMode); update_option('getmcp_gateway_servers', $originalSelected);
$gateway = \GetMCP\Gateway\FeatureManager::run('save', ['kind' => 'gateway', 'name' => 'Synthetic Telegram gateway', 'server_ids' => [$server->id], 'allowed_user_ids' => [(int)$admin->ID]]);
$gatewayServer = $sm->get((int)$gateway['id']); $fh = new \GetMCP\Gateway\FeatureHandler($gatewayServer, null, (int)$admin->ID);
$sm->update($server->id, ['auth_config' => wp_json_encode(['provider' => 'getmcp', 'allowed_user_ids' => []])]);
check(!\GetMCP\Auth\FirstPartyOAuth::can_user_access((int)$admin->ID, $sm->get($server->id)), 'direct account server empty allowlist denies owner');
check(count($fh->handle('tools/list', [])['tools']) === 44, 'gateway explicit membership delegates account tools');
$listed = $fh->handle('tools/list', [])['tools']; $gatewayName = array_values(array_filter($listed, fn($d) => str_contains($d['name'], 'get_me')))[0]['name'];
check(empty($fh->handle('tools/call', ['name' => $gatewayName, 'arguments' => []])['isError']), 'gateway read works while direct access denied');
try { (new \GetMCP\Gateway\FeatureHandler($gatewayServer, null, (int)$u2->ID))->handle('tools/list', []); check(false, 'gateway excluded user denied'); } catch (Throwable $e) { check(true, 'gateway excluded user denied'); }
\GetMCP\Gateway\FeatureManager::run('save', ['id' => $gatewayServer->id, 'server_ids' => []]);
try { $fh->handle('tools/call', ['name' => $gatewayName, 'arguments' => []]); check(false, 'removed gateway membership immediately fails'); } catch (Throwable $e) { check(true, 'removed gateway membership immediately fails'); }
$sm->update($server->id, ['auth_config' => wp_json_encode($auth)]);
$sm->update($server->id, ['status' => 'paused']); check(req("bridge/$id/get_me", [], 'POST', $token)->get_status() === 403, 'paused native server fails immediately'); $sm->update($server->id, ['status' => 'active']);
$readTool = array_values(array_filter($items, fn($t) => $t->name === 'get_me'))[0]; $tm->update($readTool->id, ['status' => 'draft']); check(req("bridge/$id/get_me", [], 'POST', $token)->get_status() === 403, 'disabled individual tool fails immediately'); $tm->update($readTool->id, ['status' => 'active']);
check(Normalize::result(['id' => PHP_INT_MAX, 'auth_key' => 'secret', 'bytes' => "\xff"]) === ['id' => (string)PHP_INT_MAX, 'bytes' => ['encoding' => 'base64', 'value' => '/w==']], 'nested normalization and 64-bit identifiers');
check(req("connections/$id/disconnect")->get_data()['telegram_revoked'] === true, 'fixture disconnect revokes');
check(req("bridge/$id/get_me", [], 'POST', $token)->get_status() === 401, 'disconnect immediately invalidates bridge credential');
$row = Store::get($id); check($row['bridge_hash'] === '' && !$row['writes_enabled'] && $sm->get($server->id)->status === 'paused', 'disconnect persists denied state');
try { $handler->handle('tools/call', ['name' => 'get_me', 'arguments' => []]); check(false, 'native call cannot return stale data after disconnect'); } catch (Throwable $e) { check(true, 'native call cannot return stale data after disconnect'); }
require GETMCP_TELEGRAM_PATH . 'vendor/autoload.php';
check((new \danog\MadelineProto\Settings\Ipc())->getSlow() === true, 'actual bundled runtime forces full request-scoped sessions');
$settings = new \danog\MadelineProto\Settings(); $settings->getSerialization()->setInterval(20); check(true, 'actual bundled settings API compatibility');
echo wp_json_encode(['passed' => $GLOBALS['passes'], 'failed' => $GLOBALS['failures'], 'driver' => 'synthetic', 'real_telegram' => 'unavailable: credentials not provided']) . "\n";
exit($GLOBALS['failures'] ? 1 : 0);
