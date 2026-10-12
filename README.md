# GetMCP Telegram Extension

Developed by **Synergetic Dev** — https://synergetic.dev/

A separate WordPress plugin for **personal Telegram accounts**. It publishes 44
ordinary HTTP tools into GetMCP and executes their Telegram operations using a
bundled PHP MTProto client. No Python/Node installation, command executor, daemon,
companion application or GetMCP core modification is required.

**0.1.0 is a development preview.** WordPress/database integration uses synthetic
Telegram accounts. Real Telegram authorization, sessions, pagination and hosting
performance have not been verified without an account/application credential.
Do not treat fixture results as a production readiness claim.

## Hosting

- Linux/Unix, 64-bit PHP **8.3+**; local runtime integration tested on PHP 8.3.28.
- PHP extensions: openssl, json, xml, dom, mbstring, gmp, fileinfo, iconv, gd, zlib.
- GetMCP **1.7.0**, compatible GetMCP Extensions **1.2.3** with native OAuth enabled,
  and a GetMCP plan allowing OAuth and 44 tools per Telegram server.
- A public HTTPS WordPress address reachable from PHP for the native HTTP bridge.
  The normal GetMCP SSRF and TLS protections remain enabled.
- PHP must be able to serve at least two requests concurrently: the native MCP
  request calls the same site's private bridge over HTTP.
- Outgoing Telegram data-center connections. Your host must allow the PHP socket
  transports used by MadelineProto. Recommended PHP memory: 256 MB or more;
  request/PHP timeout: at least 60 seconds. Initial handshakes can be slower.
- A writable private session directory **outside every public document root**,
  owned by the PHP worker and with mode `0700`.

On the hosting filesystem, create that directory and add its actual absolute path
to `wp-config.php` above the WordPress bootstrap:

```php
define('GETMCP_TELEGRAM_SESSION_DIR', '/home/your-account/private/telegram');
```

The directory must already exist. This plugin never auto-creates storage under
uploads or another public directory. Application credentials are encrypted in the
WordPress database using authenticated AES-GCM and WordPress salts. MTProto session
files contain account authorization material: they are **private files, not
application-encrypted files**. Protect their backups and use hosting/disk encryption
where required. Never include them in support exports or public packages.

## Installation and connection

1. Keep existing GetMCP and GetMCP Extensions installed. Upload this plugin's ZIP
   through Plugins → Add Plugin. It adds **GetMCP → Telegram Accounts**.
2. Review hosting diagnostics. They check installed requirements; they do not prove
   Telegram connectivity, web-server timeout policy or account compatibility.
3. Obtain your application's API ID/hash from https://my.telegram.org and enter them
   into the WordPress form. Do not place them in MCP tool arguments or chat.
4. Connect your existing personal account using its international phone number,
   the Telegram login code, and its two-step password when requested. Codes and
   passwords are submitted to the login handler and are not stored by this plugin.
5. Use **Verify account (read only)**. This calls `getSelf` and rejects bot accounts.
6. Review and confirm **Publish to GetMCP**. The native server initially allows only
   the WordPress connection owner. Other administrators receive no owner bypass.
7. Select the server explicitly in a project gateway when you want to delegate
   access. **Every permitted gateway user then operates this server owner's Telegram
   account.** This is intentional shared-account delegation, not an automatic choice
   of the caller's Telegram account. Create separate owned connections to isolate
   accounts/projects. Direct-server allowlists remain managed by GetMCP Extensions.
8. Read tools are active; mutations are drafts until separately enabled. Each write
   also requires `confirm: true`. Verify uncertain writes in Telegram before retrying.

The original `/mcp` gateway does not automatically acquire Telegram servers in
“all servers” mode. Publication there requires **selected** mode with an explicit
server ID. Existing gateway settings and memberships are not rewritten.

## Coverage

27 reads: identity, dialogs, chat information, history, per-chat/global search,
message lookup, contacts, member information, folders, pinned chats, drafts,
scheduled-message metadata, profile-photo metadata, notification/privacy settings,
active devices, saved GIF/sticker metadata.

17 writes: send/edit/delete/forward messages, mark messages read, save drafts,
pin/unpin, block/unblock, join/leave channels, profile/username changes, archive.
No write is used as a connectivity test.

Deferred: binary media upload/download, live update subscriptions, secret chats,
calls, group/channel creation and administration, bulk contact import, QR/passkey
login, account signup/deletion and arbitrary MTProto methods. These require their
own bounded hosting, permission and behavior tests. This is not complete parity
with either referenced Python MCP implementation.

## Runtime and source

MadelineProto **8.7.0** and 55 transitive Composer packages are pinned by
`composer.lock`. The installed ZIP contains the runtime, its source and licenses;
Composer is a build-time tool and is not needed on the hosting.
The adapter selects Amp's in-process blocking filesystem before session loading,
so its default parallel filesystem does not start a PHP subprocess.

`tools/patch-runtime.py` applies a documented source patch to:

- force full request-scoped sessions instead of starting an IPC worker;
- preserve the hosting memory/execution limits and WordPress error-log settings;
- reject direct web execution of the bundled worker entry point.
- replace the handshake's Python/external factorization fallbacks with a bounded
  PHP/GMP implementation for Telegram's public PQ challenge.
- retain a no-op Telegram logger when sessions are deserialized, preventing the
  upstream library's default raw file logging from returning on a later request.

`vendor-patch.json` records the exact changes and hashes. Diagnostics verify these
files before connection operations. Upstream library replacement fails closed
until the adapters are reviewed. The request-scoped mode is slower than a daemon
and its behavior with actual Telegram sessions still needs verification.

This separate plugin is **AGPL-3.0-only**. GetMCP Extensions keeps its existing
license. All source needed to rebuild this plugin is included in the developer
handoff and a downloadable source ZIP inside the install package. The admin page,
tool descriptions and bridge results offer that source to network users. Rebuild
the source archive when distributing/hosting a modified build. See LICENSE,
composer.lock and each bundled package's own notices. This implementation uses the
two Python projects as capability references, without copying their server code:
https://github.com/chigwell/telegram-mcp and https://github.com/jgalea/telegram-mcp.

## Development

Read [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md),
[docs/MIGRATION-ROLLBACK.md](docs/MIGRATION-ROLLBACK.md) and
[docs/VERIFICATION.md](docs/VERIFICATION.md).
No GitHub workflow or hosted CI run is added by this development preview.
# Account setup and discovery

Activation creates no account slots or credentials. Add the first personal account
in Telegram Accounts, then add further accounts only when needed. The local QA
fixture retains one connected synthetic browser account.

When active, this extension appears on GetMCP Extensions and adds a Telegram
setup template to Templates, with index/detail metadata through the protected
native templates REST API. The setup link opens Telegram Accounts; connecting,
verifying and synchronizing publishes the reviewed catalog. Generic template
installation cannot create placeholder accounts or bypass authentication.
GetMCP 1.7.0 supplies no public extension/template registry hook, so these are
isolated WordPress UI/REST adapters, not a claim of native registry support.

