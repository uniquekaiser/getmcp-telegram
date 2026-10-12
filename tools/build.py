"""Produce installable/source artifacts without account state or fixture runtime."""
import hashlib, json, re, shutil, zipfile
from pathlib import Path

root = Path(__file__).resolve().parents[1]
dist = root / 'dist'; dist.mkdir(exist_ok=True)
manifest = json.loads((root / 'vendor-patch.json').read_text())
for file, expected in manifest['files'].items():
    assert hashlib.sha256((root / file).read_bytes()).hexdigest() == expected, file
result = json.loads((root / 'evidence/integration-php83.txt').read_text().splitlines()[-1])
assert not result['failed'] and result['passed'] >= 60, result
version = re.search(r'\* Version: ([\d.]+)', (root / 'getmcp-telegram.php').read_text())[1]
assert version == '0.1.0'
exclude = {'.git', 'dist', 'evidence', '__pycache__', '.playwright-cli', 'runtime', '.venv', 'source'}
files = [p for p in root.rglob('*') if p.is_file() and not any(part in exclude for part in p.relative_to(root).parts)]
assert not any(p.name == 'wp-config.php' or p.name.startswith('.env') and not p.name.endswith('.example') for p in files)

def runtime_file(path):
    parts = path.relative_to(root).parts
    if parts[0] in {'tests', 'tools', 'graphify-out', '.github', 'verification', '.gitignore', '.graphifyignore'}: return False
    if parts[0] == 'vendor' and any(x in {'.github', '.git', 'examples', 'tests', 'test', 'tools', 'docs'} for x in parts[1:]): return False
    return True

runtime = [p for p in files if runtime_file(p)]
assert (root / 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php').is_file()
source = dist / f'getmcp-telegram-{version}-source.zip'
with zipfile.ZipFile(source, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for p in files:
        if any(x in {'.github', '.git'} for x in p.relative_to(root).parts): continue
        z.write(p, 'getmcp-telegram/' + p.relative_to(root).as_posix())
(root / 'source').mkdir(exist_ok=True)
shutil.copyfile(source, root / 'source' / source.name)
install = dist / f'getmcp-telegram-{version}.zip'
hashes = {p.relative_to(root).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest() for p in runtime}
hashes['source/' + source.name] = hashlib.sha256(source.read_bytes()).hexdigest()
inventory = {'plugin': 'getmcp-telegram', 'version': version, 'all_files_new': True, 'credentials': 'excluded', 'runtime_files': hashes}
with zipfile.ZipFile(install, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    z.writestr('getmcp-telegram/DISTRIBUTION', f'GetMCP Telegram Extension {version}\n')
    for p in runtime: z.write(p, 'getmcp-telegram/' + p.relative_to(root).as_posix())
    z.write(source, 'getmcp-telegram/source/' + source.name)
    z.writestr('getmcp-telegram/FILE-MANIFEST.json', json.dumps(inventory, indent=2))
with zipfile.ZipFile(install) as z:
    assert not any('/tests/' in name or '/tools/' in name or 'wp-config' in name for name in z.namelist())
    assert z.testzip() is None

handoff = dist / f'getmcp-telegram-{version}-developer-handoff.zip'
with zipfile.ZipFile(handoff, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for p in files:
        if any(x in {'.github', '.git'} for x in p.relative_to(root).parts): continue
        z.write(p, 'source/getmcp-telegram/' + p.relative_to(root).as_posix())
    for p in (root / 'evidence').glob('*'):
        if p.is_file() and p.suffix in {'.txt', '.json', '.jpg'}: z.write(p, 'verification/' + p.name)
    z.write(install, 'packages/' + install.name)
    z.writestr('FILE-MANIFEST.json', json.dumps(inventory, indent=2))
checksums = [(hashlib.sha256(p.read_bytes()).hexdigest(), p.name) for p in [install, handoff, source]]
(dist / 'SHA256SUMS.txt').write_text(''.join(f'{digest}  {name}\n' for digest, name in checksums), encoding='utf-8')
print(json.dumps({'assets': [{'file': str(p), 'bytes': p.stat().st_size, 'sha256': d} for (d, _), p in zip(checksums, [install, handoff, source])], 'runtime_files': len(runtime)}, indent=2))
