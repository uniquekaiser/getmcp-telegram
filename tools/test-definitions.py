import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
cases = [
    ('telegram.hosting', 'Hosting requirements', 'Refuse unsupported hosting without changing server configuration.', 'hosting-refusal', 'refusal', 'administrator', 'includes/Diagnostics.php', 'public static function assertReady', '/wp-json/getmcp-telegram/v1/connections', {'name': 'Synthetic', 'api_id': 12345, 'api_hash': 'a' * 32}),
    ('telegram.accounts', 'Personal account connections', 'Own and manage isolated personal Telegram accounts with encrypted application credentials.', 'save-reload', 'positive', 'administrator', 'includes/Store.php', 'public static function create', '/wp-admin/admin.php?page=getmcp-telegram', {'name': 'Synthetic', 'api_id': 12345, 'api_hash': 'a' * 32}),
    ('telegram.accounts', 'Personal account connections', 'Own and manage isolated personal Telegram accounts with encrypted application credentials.', 'owner-isolation', 'refusal', 'administrator', 'includes/Plugin.php', '@wp-test feature=telegram.accounts scenario=owner-isolation', '/wp-json/getmcp-telegram/v1/connections/1/verify', {}),
    ('telegram.accounts', 'Personal account connections', 'Own and manage isolated personal Telegram accounts with encrypted application credentials.', 'login-two-factor', 'positive', 'administrator', 'includes/Client.php', 'public function login', '/wp-admin/admin.php?page=getmcp-telegram', {'phone': '+573001234567', 'code': '11111', 'password': 'fixture-only-2fa'}),
    ('telegram.accounts', 'Personal account connections', 'Own and manage isolated personal Telegram accounts with encrypted application credentials.', 'disconnect', 'positive', 'administrator', 'includes/Plugin.php', '@wp-test feature=telegram.accounts scenario=disconnect', '/wp-admin/admin.php?page=getmcp-telegram', {}),
    ('telegram.publication', 'Native tools and gateway membership', 'Publish reviewed tools with explicit ownership and gateway delegation; respect existing licensing and access.', 'gateway-delegation', 'positive', 'administrator', 'includes/Integration.php', 'public static function publish', '/wp-json/getmcp-telegram/v1/connections/1/publish', {'acknowledge_delegation': True}),
    ('telegram.publication', 'Native tools and gateway membership', 'Publish reviewed tools with explicit ownership and gateway delegation; respect existing licensing and access.', 'removed-membership', 'refusal', 'administrator', 'includes/Plugin.php', 'public static function beforeTool', '/mcp/synthetic-gateway', {}),
    ('telegram.writes', 'Mutation controls', 'Keep writes disabled until verification and explicit enablement, and require confirmation for each call.', 'disabled-write', 'refusal', 'administrator', 'includes/Plugin.php', 'private static function assertBridge', '/wp-json/getmcp-telegram/v1/bridge/1/send_message', {'peer': '@synthetic', 'message': 'Fixture only', 'confirm': True}),
    ('telegram.writes', 'Mutation controls', 'Keep writes disabled until verification and explicit enablement, and require confirmation for each call.', 'confirmed-fixture-write', 'positive', 'administrator', 'includes/Catalog.php', 'public static function arguments', '/wp-json/getmcp-telegram/v1/bridge/1/send_message', {'peer': '@synthetic', 'message': 'Fixture only', 'confirm': True}),
]
features = {}
for fid, title, intent, sid, kind, role, source, marker, entry, inputs in cases:
    f = features.setdefault(fid, {'id': fid, 'title': title, 'intent': intent, 'provenance': [{'kind': 'owner-requirement', 'reference': 'Conversation: separate personal Telegram GetMCP extension', 'description': 'Personal accounts; separate plugin; ordinary hosting with no companion service or GetMCP file modification.'}], 'dependencies': [{'slug': 'getmcp', 'version': '1.7.0'}, {'slug': 'getmcp-extensions', 'version': '1.2.3'}], 'scenarios': []})
    line = next(i + 1 for i, text in enumerate((root / source).read_text().splitlines()) if marker in text)
    f['scenarios'].append({'id': sid, 'title': sid.replace('-', ' ').capitalize(), 'case': kind, 'role': role,
        'entry': {'surface': 'admin' if entry.startswith('/wp-admin') else 'rest', 'path': entry},
        'sourceRefs': [{'path': source, 'line': line}], 'inputs': inputs, 'status': 'gap',
        'reason': 'CLI/WordPress fixtures cover this requirement separately; no immutable Studio snapshot, reviewed binding or supported synthetic-driver recipe has been imported.',
        'recovery': 'Import the local synthetic fixture into Studio, pin its connection/server identifiers and independent database oracle, review the preview, then bind typed actions. Use fixtures for all writes.'})
document = {'schemaVersion': '1.0', 'subject': 'plugin', 'features': list(features.values()), 'exclusions': [{'path': 'evidence', 'reason': 'Verification reports and screenshots only; no runtime code.'}]}
(root / 'docs/testing/test-definitions.json').write_text(json.dumps(document, indent=2) + '\n', encoding='utf-8')
print(f'{len(cases)} intended Studio scenarios; all binding gaps explicitly recorded.')
