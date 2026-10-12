<?php
namespace Synergetic\Telegram;
final class Vault {
    private static function key(): string { return hash('sha256', wp_salt('auth') . '|getmcp-telegram-v1', true); }
    public static function encrypt(array $data): string {
        $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt(wp_json_encode($data), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, 'getmcp-telegram');
        if ($cipher === false) { throw new Fault('Could not encrypt Telegram credentials.', 500); }
        return base64_encode($iv . $tag . $cipher);
    }
    public static function decrypt(string $value): array {
        $raw = base64_decode($value, true);
        $clear = is_string($raw) && strlen($raw) > 28 ? openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'getmcp-telegram') : false;
        $data = $clear === false ? null : json_decode($clear, true);
        if (!is_array($data)) { throw new Fault('Telegram credentials cannot be decrypted. Restore your WordPress salts or create a new connection.', 409); }
        return $data;
    }
    public static function root(): string {
        $configured = defined('GETMCP_TELEGRAM_SESSION_DIR') ? GETMCP_TELEGRAM_SESSION_DIR : '';
        if (!is_string($configured) || !$configured || !is_dir($configured) || is_link($configured)) {
            throw new Fault('Configure GETMCP_TELEGRAM_SESSION_DIR in wp-config.php as an existing private directory outside the web root.', 503);
        }
        $root = realpath($configured);
        $public = [realpath(ABSPATH), realpath($_SERVER['DOCUMENT_ROOT'] ?? ABSPATH)];
        foreach (array_filter($public) as $path) {
            if ($root === $path || str_starts_with($root . DIRECTORY_SEPARATOR, rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                throw new Fault('Telegram session storage must be outside the web root.', 503);
            }
        }
        if (PHP_OS_FAMILY !== 'Windows' && (fileperms($root) & 0077)) { throw new Fault('Set the private Telegram directory permissions to 0700.', 503); }
        if (!is_writable($root)) { throw new Fault('The private Telegram directory is not writable by PHP.', 503); }
        return $root;
    }
    public static function session(array $row): string {
        if (!preg_match('/^[a-f0-9]{48}$/D', $row['session_id'])) { throw new Fault('Invalid Telegram session identifier.', 500); }
        $path = self::root() . DIRECTORY_SEPARATOR . $row['session_id'];
        if (is_link($path)) { throw new Fault('Symbolic session paths are not allowed.', 503); }
        return $path;
    }
}
