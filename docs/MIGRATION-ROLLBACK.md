# Migration and rollback

This is a new plugin. Activation creates `{prefix}getmcp_telegram` at schema version
1. No existing GetMCP credentials, options, allowlists, servers or gateways are
migrated. Existing WordPress encryption salts must be retained. Salt changes make
stored application credentials undecryptable and cause a clear connection error;
there is no plaintext fallback or implicit credential substitution.

Only explicitly publishing a verified connection creates a new native GetMCP
server and its 44 tools. Its owner is the current WordPress user; no posted owner ID
is accepted. Publication uses normal GetMCP managers and checks server/tool/OAuth
license capacities. It does not edit unrelated records. Republishing preserves
allowlists and server settings, rotates this connection's bridge credential and
synchronizes its catalog. Partial publication leaves the server paused.

To roll back an account, use Disconnect while the plugin remains active. Local
bridge access is disabled first, writes are disabled and the linked server is
paused. Then the plugin attempts to revoke the Telegram session. If Telegram is
unreachable, revoke that device from Telegram Settings → Devices manually.
Disconnect does not delete the server or its membership from other gateways.

To roll back the plugin, disconnect accounts and deactivate it. The REST bridge
then disappears and those native tools return an error. Tables, application
credentials, private session files and native server definitions are preserved.
No cached successful response is created by this plugin's native-call hooks.
Removing the plugin has no automatic recursive filesystem or database deletion.
Delete retained records/private files only as a separate explicit cleanup after
Telegram sessions have been revoked and your backup policy has been applied.

Deleting a WordPress user disables their local bridge credentials and pauses their
linked servers. It preserves private files for deliberate cleanup; it does not
claim that Telegram has revoked a device if the remote API was not contacted.

Restore the same reviewed package, WordPress database, encryption salts and private
session directory to restore a disconnected environment. Do not restore two live
copies of one authorization session: use a newly authorized session on each host.

Upgrade: replace plugin files only, retain the private directory outside the plugin
tree, and rerun diagnostics plus account reads. Do not auto-update MadelineProto or
run Composer on a customer site. There is no automatic GitHub updater in this
preview; a public distribution repository has not been created for this plugin.
