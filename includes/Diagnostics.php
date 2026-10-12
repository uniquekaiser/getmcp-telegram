<?php
namespace Synergetic\Telegram;
final class Diagnostics {
    public static function inspect(): array {
        $errors = []; $warnings = [];
        if (version_compare(PHP_VERSION, '8.3', '<') || PHP_INT_SIZE !== 8) { $errors[] = '64-bit PHP 8.3 or newer is required.'; }
        if (!defined('GETMCP_VERSION') || GETMCP_VERSION !== '1.7.0') { $errors[] = 'This Telegram preview has been reviewed with GetMCP 1.7.0 only.'; }
        if (wp_get_environment_type() !== 'local' && wp_parse_url(rest_url(), PHP_URL_SCHEME) !== 'https') { $errors[] = 'A public HTTPS WordPress REST URL is required outside local testing.'; }
        foreach (['openssl', 'json', 'xml', 'dom', 'mbstring', 'gmp', 'fileinfo', 'iconv', 'gd', 'zlib'] as $extension) {
            if (!extension_loaded($extension)) { $errors[] = 'Missing PHP extension: ' . $extension; }
        }
        if (!function_exists('stream_socket_client')) { $errors[] = 'PHP socket connections are disabled.'; }
        if (!class_exists('GetMCPExtensions\\Runtime') || !\GetMCPExtensions\Runtime::enabled('native_oauth')) { $errors[] = 'Enable compatible GetMCP Extensions with WordPress sign-in support.'; }
        try { Vault::root(); } catch (Fault $e) { $errors[] = $e->getMessage(); }
        $manifestPath = GETMCP_TELEGRAM_PATH . 'vendor-patch.json';
        $manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
        $ipc = GETMCP_TELEGRAM_PATH . 'vendor/danog/madelineproto/src/Settings/Ipc.php';
        if (!is_array($manifest) || !is_file($ipc) || !hash_equals($manifest['sha256'] ?? '', hash_file('sha256', $ipc))) { $errors[] = 'Bundled request-scoped Telegram runtime is missing or altered.'; }
        foreach ($manifest['files'] ?? [] as $file => $hash) {
            $path = GETMCP_TELEGRAM_PATH . $file;
            if (!is_file($path) || !hash_equals($hash, hash_file('sha256', $path))) { $errors[] = 'A bundled hosting adapter is missing or altered.'; break; }
        }
        if (class_exists('danog\\MadelineProto\\API', false)) {
            $loaded = (new \ReflectionClass('danog\\MadelineProto\\API'))->getFileName();
            if (realpath($loaded) !== realpath(GETMCP_TELEGRAM_PATH . 'vendor/danog/madelineproto/src/API.php')) { $errors[] = 'Another plugin loaded a different MadelineProto runtime.'; }
        }
        $memory = ini_get('memory_limit');
        if ($memory !== '-1' && wp_convert_hr_to_bytes($memory) < 256 * 1024 * 1024) { $warnings[] = 'At least 256 MB PHP memory is recommended; large histories and media need more.'; }
        $warnings[] = 'Outgoing Telegram data-center connections and shared-host request time limits require a real-account hosting test.';
        if ((int)ini_get('max_execution_time') > 0 && (int)ini_get('max_execution_time') < 60) { $warnings[] = 'A PHP/request timeout of at least 60 seconds is recommended for initial Telegram connection setup.'; }
        return ['ready' => !$errors, 'errors' => $errors, 'warnings' => $warnings, 'php' => PHP_VERSION, 'runtime' => 'MadelineProto 8.7.0, request-scoped patch', 'worker' => 'No background worker or companion service'];
    }
    public static function assertReady(): void {
        $report = self::inspect();
        if (!$report['ready']) { throw new Fault(implode(' ', $report['errors']), 503, 'hosting_unavailable'); }
    }
}
