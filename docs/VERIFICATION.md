# Verification status — development preview 0.1.0

Passed locally:

- Extensions card and Telegram template setup link verified in the WordPress
  7.1.3 browser; the template link opens account setup. Protected template index
  and detail return the 44-tool catalog; anonymous requests are refused.
- Fixture cleanup retains exactly one connected Browser QA account. Activation
  does not create account records; further accounts require explicit form saves.

- WordPress 7.1.3 database upgrade and admin recovery, plus 60 integration checks
  on PHP 8.3.28. The fixture-only entitlement helper now tolerates core upgrade
  requests that skip normal plugins. Telegram remains simulated.

- Real WordPress 6.8.3, MariaDB 11.4 and PHP 8.3.28 integration, with the actual
  GetMCP 1.7.0 and GetMCP Extensions 1.2.3 implementations. Telegram traffic and
  entitlement are explicitly synthetic test fixtures.
- Account persistence, encryption/tamper rejection, permission and administrator
  owner-isolation refusal, login code/2FA states, harmless verification, publication,
  distinct account keys/sessions, gated fixture mutations, disconnect, native body
  mapping/credential injection/results, global-cache preservation, paused/disabled
  refusal, original-gateway publication and project-gateway delegation/removal.
  **60 checks passed** in the source fixture and again with the install ZIP's
  extracted runtime loaded as the active plugin from an isolated directory.
- The pinned library signatures for all 44 tools, plus its patched full-session
  setting and settings API. These checks do not prove actual MTProto sessions.
- Bounded PHP/GMP factorization of public semiprime fixtures, including large
  64-bit challenges, and light library startup preserving hosting limits/logging.
- Existing OAuth security regression harness: **62 checks** on PHP 8.3.28.
- Existing user-access regression harness: **33 checks** on PHP 8.3.28.
- Composer platform requirements and dependency audit: no reported advisories or
  abandoned packages at the recorded build time. This is not a comprehensive audit
  of the MTProto library or plugin.
- Browser save/reload, synthetic code/2FA login and harmless identity verification.
  Screenshot: `verification/browser-account.jpg` in the developer handoff.

Browser gaps: a native confirmation in the first fixture tab stalled CDP input.
Later tabs could render the verified account but their subsequent click/keyboard
events did not change controls. The UI now uses inline review/confirm controls;
publication/write-enable/disconnect browser completion has **not** been verified.
Their REST/backend paths are exercised by the integration suite.

Read-only owned-host preflight: Linux, PHP 8.3.35/64-bit; all required extensions
and socket-client function present; 3072 MB global memory; global PHP execution
limit 480 seconds. The connector itself imposed a 30-second local execution limit.
The private Telegram session directory is not configured. No plugin or provider
account was installed/changed on that host. Reverse-proxy/PHP-FPM timeouts and
Telegram data-center connectivity remain unverified.

Unavailable: real Telegram application/account credentials, authorization, cross-
request MTProto session persistence, real pagination/RPC responses, actual 2FA,
upstream revocation/errors, no-worker behavior under an authorized session, hosting
resource use and a live-account harmless read. No real Telegram write was executed.

Skipped: hosted CI/publication, customer deployment, WordPress.org Plugin Check,
binary media/secret-chat/update-stream features, and unsupported hosting matrices.
PHP 8.4/8.5 and WordPress 6.2/7.1 runtime coverage have not been established by the
PHP 8.3/WordPress 6.8 fixture. Studio import/review/bindings are explicit gaps in
the canonical test definitions.

Initial fixture/code issues (credential inspection accessor, incorrect generated
hash defaults and missing wrapper method declarations) were corrected and affected
checks rerun. Final evidence files, exact check counts and package hashes accompany
the handoff. Any unresolved test failure prevents package creation.

Do not promote this preview to production-ready from fixture evidence. Next gate:
connect a Telegram test account/application in a local real-client fixture, prove
session persistence without subprocesses, then run a harmless read on the actual
hosting before enabling writes.
