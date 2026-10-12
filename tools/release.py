"""Build a tagged release from locally verified source; no implicit publication."""
from pathlib import Path
import hashlib, json, os, re, subprocess, sys
root = Path(__file__).resolve().parents[1]
header = (root / 'getmcp-telegram.php').read_text()
version = re.search(r'\* Version:\s*(\S+)', header)[1]
assert f"'GETMCP_TELEGRAM_VERSION', '{version}'" in header
assert f'Stable tag: {version}' in (root / 'readme.txt').read_text()
tag = os.environ.get('GITHUB_REF_NAME')
if tag: assert tag == 'v' + version, 'Tag/version mismatch'
receipt = json.loads((root / 'verification/local-preflight.json').read_text())
for relative, digest in receipt['inputs'].items():
    assert hashlib.sha256((root / relative).read_bytes().replace(b'\r\n', b'\n')).hexdigest() == digest, 'Local evidence invalidated: ' + relative
assert receipt['integration']['passed'] >= 60 and not receipt['integration']['failed']
lines = (root / 'CHANGELOG.md').read_text().splitlines()
start = next(i for i, line in enumerate(lines) if line.startswith('## ' + version + ' '))
end = next((i for i in range(start + 1, len(lines)) if lines[i].startswith('## ')), len(lines))
notes = lines[start:end]
assert any(line.startswith('- ') for line in notes)
assert all(re.match(r'^- \[(NEW|IMPROVE|FIX|SECURITY|COMPAT|DEPRECATE|REMOVE|DEV|TRANSLATIONS)\] ', line) for line in notes if line.startswith('- '))
(root / 'evidence').mkdir(exist_ok=True)
(root / 'evidence/integration-php83.txt').write_text(json.dumps(receipt['integration']) + '\n')
subprocess.run([sys.executable, str(root / 'tools/build.py')], cwd=root, check=True)
(root / 'dist/release-notes.md').write_text('\n'.join(notes).strip() + '\n')
