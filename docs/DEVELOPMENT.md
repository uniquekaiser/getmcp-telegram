# Developer handoff

Modules: `Store` owns account rows and serialized database locks; `Vault` encrypts
API ID/hash and validates private paths; `Client` adapts the pinned PHP library;
`Catalog` is the reviewed tool/schema allowlist; `Integration` publishes native
GetMCP servers/tools; `Plugin` provides protected REST/admin interfaces and scoped
native cache protection; `Normalize` preserves nested results, binary data and
large IDs while omitting authorization material; `Diagnostics` reports hosting gaps.

REST namespace `getmcp-telegram/v1`:

- `GET /status`: current user's redacted accounts, diagnostics and catalog.
- `POST /connections`: `{name,api_id,api_hash}`; existing GetMCP server/tool managers only.
- `POST /connections/{id}/login`: `{step:"phone"|"code"|"password",value}`; owner only.
- `POST /connections/{id}/verify`: harmless personal-account identity read; owner only.
- `POST /connections/{id}/publish`: `{acknowledge_delegation:true}`; owner + management permissions.
- `POST /connections/{id}/writes`: `{enabled:true,confirm:true}` or `{enabled:false}`.
- `POST /connections/{id}/disconnect`: owner only; local access disabled before remote logout.
- `POST /bridge/{id}/{tool}`: only that connection's generated bearer credential.
  The native server stores it encrypted; REST responses never reveal it. WordPress
  cookies, gateway bearer tokens and posted user IDs cannot authenticate this route.

The browser interfaces use the normal WordPress REST nonce. Configuration/connection
changes are serialized per account with MySQL/MariaDB `GET_LOCK`; a concurrent
operation returns 409. The bridge repeats credential/server/account/tool checks
after acquiring its lock. No generic RPC tool, command runner, remote code loader or
automatic runtime installation exists. Mutation HTTP retries are zero; Telegram
RPC resend timeout is longer than the operation drop timeout. Server-side flood
waits are passed with `floodWaitLimit=0`; an error includes a sanitized wait duration.

For Apache, the package denies direct vendor access with `vendor/.htaccess` and
guards the library's worker entry point. Add an equivalent rule to an Nginx host:

```nginx
location ^~ /wp-content/plugins/getmcp-telegram/vendor/ { deny all; }
```

GetMCP's normal quota, native rate limit, OAuth scopes, logging, TLS and SSRF checks
remain on the native tool call path. Native analytics may contain chat content as
with other GetMCP tools: configure your site's logging/retention accordingly.
Telegram application credentials and login code/password are separate from tools.
The bridge also has an account-level 30-requests rolling-minute guard.

Local fixture (one project-owned Docker stack):

```powershell
docker compose -f tools/compose.yaml build wordpress
docker compose -f tools/compose.yaml run --rm --no-deps -w /var/www/html/wp-content/plugins/getmcp-telegram wordpress composer install --no-dev --prefer-dist --no-interaction
python tools/patch-runtime.py
docker compose -f tools/compose.yaml up -d
```

Install WordPress on `http://localhost:8932`, activate GetMCP 1.7.0, GetMCP Extensions
1.2.3, then this plugin. Configure the fixture-only private volume path
`/var/private-telegram` with ownership `www-data` and permissions 0700. The compose
file expects the existing vendor snapshot and sibling Extensions checkout.

Synthetic-driver tests require explicit local fixture configuration
`GETMCP_TELEGRAM_TESTING=true`, `WP_ENVIRONMENT_TYPE=local` and a fixture MU plugin
requiring `tests/fake-driver.php`. This driver also simulates a Pro entitlement,
clearly separated from actual licensing. Neither the driver nor fixture scaffolding
is included in the install ZIP. Never deploy it or claim its account data as real.

```powershell
docker compose -f tools/compose.yaml exec -T wordpress wp eval-file wp-content/plugins/getmcp-telegram/tests/integration.php --allow-root
docker compose -f tools/compose.yaml exec -T wordpress wp eval-file wp-content/plugins/getmcp-telegram/tests/catalog-contract.php --allow-root
node --check assets/admin.js
python tools/build.py
```

For a real-account pilot, enable the actual client (remove the fixture driver), use
Telegram test DC accounts only with an appropriate real API ID/hash, and validate
new-session login, 2FA, persistence across requests, no subprocesses, two users,
revocation and bounded reads before any writes. Then perform a harmless live-account
read on the target hosting. Credentials belong in the WordPress setup form.

The machine-readable Studio expectations are in `docs/testing/test-definitions.json`.
CLI/browser evidence is separate; Studio bindings/import/review have not been performed.
