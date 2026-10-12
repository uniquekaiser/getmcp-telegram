=== GetMCP Telegram Extension ===
Contributors: uniquekaiser
Requires at least: 6.2
Requires PHP: 8.3
Requires Plugins: getmcp, getmcp-extensions
Tested up to: 7.1.3
Stable tag: 0.1.0
License: AGPL-3.0-only
License URI: https://www.gnu.org/licenses/agpl-3.0.html

Personal Telegram accounts for native GetMCP tools. Developed by Synergetic Dev.

== Description ==
Development preview. Separate WordPress plugin with a bundled PHP MTProto client,
44 native tools, account isolation, hosting diagnostics and explicit write controls.
No companion application. See README.md for private session storage requirements
and the distinction between fixture tests and unverified real Telegram behavior.

== Changelog ==
= 0.1.0 =
* [NEW] Connect personal Telegram accounts with code and two-step sign-in, identity verification and separate account storage.
* [NEW] Publish 27 read tools and 17 explicitly gated write tools through native GetMCP servers.
* [NEW] Discover Telegram in the searchable Templates catalogue and manage it from Extensions.
* [SECURITY] Isolate account owners, encrypt application credentials, protect session paths and stop access immediately after disconnect.
* [IMPROVE] Receive public GitHub package updates without a GitHub token on the WordPress site.
* [COMPAT] Verify synthetic integration on WordPress 7.1.3 and PHP 8.3; real Telegram sessions remain unverified in this development preview.
