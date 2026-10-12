<?php
if (!defined('GETMCP_TELEGRAM_TESTING') || wp_get_environment_type() !== 'local') { throw new RuntimeException('Local fixture only.'); }
wp_set_current_user(get_user_by('login', 'fixture-admin')->ID);
$request = new WP_REST_Request('GET', '/getmcp/v1/templates');
$items = rest_do_request($request);
$matches = array_values(array_filter($items->get_data(), fn($t) => $t['slug'] === 'synergetic-telegram-personal'));
if ($items->get_status() !== 200 || count($matches) !== 1 || $matches[0]['tool_count'] !== 44) { throw new RuntimeException('Template index failed.'); }
$detail = rest_do_request(new WP_REST_Request('GET', '/getmcp/v1/templates/synergetic-telegram-personal'));
if ($detail->get_status() !== 200 || count($detail->get_data()['tools']) !== 44) { throw new RuntimeException('Template detail failed.'); }
wp_set_current_user(0);
if (!in_array(rest_do_request($request)->get_status(), [401, 403], true)) { throw new RuntimeException('Template permissions failed.'); }
echo "PASS template index, detail, anonymous refusal; no account records created\n";
