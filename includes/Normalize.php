<?php
namespace Synergetic\Telegram;
final class Normalize {
    private static bool $truncated = false;
    public static function truncated(): bool { return self::$truncated; }
    public static function result(mixed $value, int $depth = 0): mixed {
        if ($depth === 0) { self::$truncated = false; }
        if ($depth > 12) { self::$truncated = true; return '[maximum nesting reached]'; }
        if (is_int($value) && abs($value) > 9007199254740991) { return (string)$value; }
        if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) { return ['encoding' => 'base64', 'value' => base64_encode($value)]; }
        if (is_array($value)) {
            if (count($value) > 2000) { self::$truncated = true; }
            $out = [];
            foreach (array_slice($value, 0, 2000, true) as $key => $item) {
                if (is_string($key) && preg_match('/(?:auth_key|api_hash|phone_code_hash|password|secret|access_token|refresh_token|salt|access_hash|file_reference)/i', $key)) { continue; }
                $out[$key] = self::result($item, $depth + 1);
            }
            return $out;
        }
        if (is_object($value)) { return self::result(get_object_vars($value), $depth + 1); }
        return $value;
    }
    public static function error(\Throwable $e): Fault {
        if ($e instanceof Fault) { return $e; }
        $message = $e->getMessage();
        if (preg_match('/FLOOD_(?:PREMIUM_)?WAIT_(\d+)/', $message, $m)) { return new Fault('Telegram rate limit: wait ' . $m[1] . ' seconds. Do not automatically retry writes.', 429, 'telegram_flood_wait'); }
        foreach (['PHONE_CODE_INVALID' => 'The Telegram login code is invalid.', 'PHONE_CODE_EXPIRED' => 'The Telegram login code expired. Start again.', 'PASSWORD_HASH_INVALID' => 'The Telegram two-step password is incorrect.', 'PHONE_NUMBER_INVALID' => 'The Telegram phone number is invalid.', 'API_ID_INVALID' => 'Telegram rejected the API ID/hash.', 'AUTH_KEY_UNREGISTERED' => 'This Telegram session was revoked. Reconnect the account.', 'SESSION_REVOKED' => 'This Telegram session was revoked. Reconnect the account.', 'CHAT_ADMIN_REQUIRED' => 'Telegram requires chat administrator permission for this operation.', 'MESSAGE_ID_INVALID' => 'Telegram did not find the supplied message ID.', 'CHANNEL_PRIVATE' => 'This account cannot access the private channel.'] as $code => $text) {
            if (str_contains($message, $code)) { return new Fault($text, str_contains($code, 'AUTH_') || $code === 'SESSION_REVOKED' ? 401 : 400, strtolower($code)); }
        }
        // No raw provider exceptions: they can contain phone numbers, credentials or message bodies.
        return new Fault('Telegram could not complete the operation. Check hosting connectivity and account permissions. A write may have completed; inspect Telegram before repeating it.', 502, 'telegram_upstream_failure');
    }
}
