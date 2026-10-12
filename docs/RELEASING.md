# Releases and updates

Developed by Synergetic Dev, https://synergetic.dev/.

The public repository uses anonymous GitHub Releases. Installed sites require no
GitHub token, licence key or Elite Licenser connection. The Elite relay applies
to private distribution; public distribution does not need that extra service.
The public updater runs only in packaged builds with the DISTRIBUTION marker.
It accepts only the exact getmcp-telegram-X.Y.Z.zip asset, never source archives.

Run local integration and security regressions before publication. Record source
hashes and verification results in verification/local-preflight.json. The release
script refuses a build if those inputs have changed. Hosted checks supplement
local evidence; they do not claim a real Telegram test or proprietary GetMCP UI
build. Bump all versions, update the categorized changelog, verify both packages,
then push one annotated vX.Y.Z tag. Only tag pushes start the release workflow.

0.1.0 remains a development preview. Its workflow publishes a prerelease, which
the stable updater does not offer automatically. Move to a stable release only
after real-account hosting and update-flow acceptance checks have passed and
the workflow's prerelease setting has been deliberately changed.
