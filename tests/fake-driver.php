<?php
/** Synthetic account driver, excluded from the install ZIP. */
if (!defined('ABSPATH') || !defined('GETMCP_TELEGRAM_TESTING') || !GETMCP_TELEGRAM_TESTING || wp_get_environment_type() !== 'local') { return; }
add_action('plugins_loaded', static function (): void {
    // Core database upgrades skip normal plugins; the MU fixture still loads.
    if (!class_exists(\GetMCP\Licensing\LicenseTier::class)) { return; }
    // Simulate entitlement in this disposable fixture only; production licensing code is unchanged.
    $r = new ReflectionClass(\GetMCP\Licensing\LicenseTier::class); $tier = $r->newInstanceWithoutConstructor();
    foreach (['id' => 'pro', 'is_expired' => false, 'raw_plan' => 'Synthetic QA Pro'] as $key => $value) { $r->getProperty($key)->setValue($tier, $value); }
    $r->getProperty('cached')->setValue(null, $tier);
}, 100);
final class TelegramFixtureClient {
    private string $key;
    private int $owner;
    public function __construct(array $row) { $this->key = 'gmt_fake_' . $row['session_id']; $this->owner = (int)$row['owner_id']; }
    public function state(): string { return get_option($this->key, 'disconnected'); }
    public function login(string $step, string $value): string {
        if ($step === 'phone') { update_option($this->key, 'waiting_code', false); }
        elseif ($step === 'code' && $this->state() === 'waiting_code') {
            if ($value !== '11111') { throw new RuntimeException('PHONE_CODE_INVALID personal secret +573001234567'); }
            update_option($this->key, 'waiting_password', false);
        } elseif ($step === 'password' && $this->state() === 'waiting_password') {
            if ($value !== 'fixture-only-2fa') { throw new RuntimeException('PASSWORD_HASH_INVALID'); }
            update_option($this->key, 'connected', false);
        } else { throw new RuntimeException('Login stage invalid'); }
        return $this->state();
    }
    public function invoke(string $tool, array $args): mixed {
        if ($this->state() !== 'connected') { throw new \Synergetic\Telegram\Fault('Reconnect this Telegram account.', 401); }
        \Synergetic\Telegram\Catalog::arguments($tool, $args);
        if ($tool === 'get_me') { return ['_' => 'user', 'id' => 100 + $this->owner, 'first_name' => 'Synthetic account', 'bot' => false, 'fixture' => true]; }
        return ['fixture' => true, 'tool' => $tool, 'args' => $args, 'items' => [], 'secret' => 'should-be-redacted'];
    }
    public function logout(): void { update_option($this->key, 'disconnected', false); }
    public function close(): void {}
}
add_filter('getmcp_telegram_client', static fn($client, $row) => new TelegramFixtureClient($row), 10, 2);
add_filter('getmcp_telegram_test_driver_active', '__return_true');
