"""Apply a narrow reproducible AGPL source patch, not a core GetMCP change."""
import hashlib, json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
path = root / 'vendor/danog/madelineproto/src/Settings/Ipc.php'
source = path.read_text(encoding='utf-8')
old = "return Magic::$isIpcWorker || \\PHP_OS_FAMILY === 'Windows';"
new = "return true; // Synergetic: request-scoped full sessions; never spawn IPC workers."
if source.count(old) == 1:
    path.write_text(source.replace(old, new), encoding='utf-8', newline='\n')
elif new not in source:
    raise SystemExit('Unsupported MadelineProto Ipc source. Review before patching.')
manifest = {'library': 'danog/madelineproto', 'version': '8.7.0', 'file': 'vendor/danog/madelineproto/src/Settings/Ipc.php', 'before': old, 'after': new, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
# The runtime never needs public executable library entry points. Deny on Apache;
# the deployment guide supplies the equivalent Nginx rule.
(root / 'vendor/.htaccess').write_text('Require all denied\n', encoding='utf-8')
# Block direct web execution of the bundled worker even on Nginx hosts. This
# extension never needs that endpoint because Ipc::getSlow() forces full sessions.
entry = root / 'vendor/danog/madelineproto/src/Ipc/Runner/entry.php'
entry_text = entry.read_text(encoding='utf-8')
guard = "if (PHP_SAPI !== 'cli' && !defined('GETMCP_TELEGRAM_PATH')) { http_response_code(403); exit; }"
if guard not in entry_text:
    marker = 'namespace danog\\MadelineProto\\Ipc\\Runner;'
    if marker not in entry_text:
        raise SystemExit('Review changed worker entry before packaging.')
    entry.write_text(entry_text.replace(marker, marker + '\n' + guard), encoding='utf-8', newline='\n')
magic = root / 'vendor/danog/madelineproto/src/Magic.php'
magic_text = magic.read_text(encoding='utf-8')
replacements = {
    "ini_set('memory_limit', -1);": '// Synergetic: preserve the hosting PHP memory limit.',
    'set_time_limit(-1);': '// Synergetic: preserve the hosting PHP execution limit.',
    "ini_set('error_log', self::$script_cwd.DIRECTORY_SEPARATOR.'MadelineProto.log');": '// Synergetic: preserve the WordPress PHP error log location.',
    'error_reporting(E_ALL);': '// Synergetic: preserve the WordPress PHP error reporting level.',
    "ini_set('log_errors', 1);": '// Synergetic: preserve the WordPress PHP error logging setting.',
}
for before, after in replacements.items():
    if before in magic_text:
        magic_text = magic_text.replace(before, after)
    elif after not in magic_text:
        raise SystemExit('Review changed Magic source before packaging: ' + before)
magic.write_text(magic_text, encoding='utf-8', newline='\n')
manifest['patches'] = [{'file': 'vendor/danog/madelineproto/src/Magic.php', 'before': b, 'after': a} for b, a in replacements.items()]
manifest['patches'].append({'file': 'vendor/danog/madelineproto/src/Ipc/Runner/entry.php', 'after': guard})
auth = root / 'vendor/danog/madelineproto/src/MTProtoSession/AuthKeyHandler.php'
auth_text = auth.read_text(encoding='utf-8')
before_methods = """                    'native_single_cpp',
                    'python_single_alt',
                    'python_single',
                    'native_single',"""
after_methods = "                    'php_gmp', // Synergetic: no Python, subprocess or external factorization service."
before_call = '$p = PrimeModule::$method($pq);'
after_call = '$p = \\Synergetic\\Telegram\\PrimeFactor::factor($pq);'
for before, after in [(before_methods, after_methods), (before_call, after_call)]:
    if before in auth_text:
        auth_text = auth_text.replace(before, after)
    elif after not in auth_text:
        raise SystemExit('Review changed Telegram auth-key factorization code.')
    manifest['patches'].append({'file': auth.relative_to(root).as_posix(), 'before': before, 'after': after})
auth.write_text(auth_text, encoding='utf-8', newline='\n')
logger = root / 'vendor/danog/madelineproto/src/Settings/Logger.php'
logger_text = logger.read_text(encoding='utf-8')
before_type = """$this->type = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg')
            ? MadelineProtoLogger::ECHO_LOGGER
            : MadelineProtoLogger::FILE_LOGGER;"""
after_type = "$this->type = MadelineProtoLogger::CALLABLE_LOGGER; // Synergetic: never emit raw Telegram payload logs."
before_extra = "$this->extra = Magic::$script_cwd.'/MadelineProto.log';"
after_extra = '$this->extra = static function (): void {};'
before_init = '$this->init();'
after_init = '$this->extra = static function (): void {}; // Synergetic: restore the no-op callback after deserialization.\n        $this->init();'
for before, after in [(before_type, after_type), (before_extra, after_extra), (before_init, after_init)]:
    if after not in logger_text:
        if before not in logger_text:
            raise SystemExit('Review changed Telegram logger persistence settings.')
        logger_text = logger_text.replace(before, after)
    manifest['patches'].append({'file': logger.relative_to(root).as_posix(), 'before': before, 'after': after})
logger.write_text(logger_text, encoding='utf-8', newline='\n')
manifest['files'] = {p.relative_to(root).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest() for p in [path, entry, magic, auth, logger]}
(root / 'vendor-patch.json').write_text(json.dumps(manifest, indent=2) + '\n', encoding='utf-8')
print(manifest['sha256'])
