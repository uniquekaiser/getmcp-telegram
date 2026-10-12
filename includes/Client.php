<?php
namespace Synergetic\Telegram;
/** One full MTProto instance per request; the bundled patch prevents worker spawning. */
class Client {
    private ?\danog\MadelineProto\API $api = null;
    public function __construct(array $row) {
        Diagnostics::assertReady();
        require_once GETMCP_TELEGRAM_PATH . 'vendor/autoload.php';
        // Serialization runs before MadelineProto's full initialization. Amp's
        // default filesystem can spawn a PHP subprocess; select in-process I/O now.
        \Amp\File\filesystem(new \Amp\File\Driver\BlockingFilesystemDriver());
        $credentials = Vault::decrypt($row['api_credentials']);
        $settings = new \danog\MadelineProto\Settings();
        $settings->getAppInfo()->setApiId($credentials['api_id'])->setApiHash($credentials['api_hash'])->setShowPrompt(false);
        // No raw RPC logs or exception reports containing personal messages/secrets.
        $settings->getLogger()->setType(\danog\MadelineProto\Logger::CALLABLE_LOGGER)->setExtra(static function (): void {});
        $settings->getRpc()->setRpcDropTimeout(20)->setRpcResendTimeout(30)->setFloodTimeout(5);
        $settings->getSerialization()->setInterval(20);
        $settings->getFiles()->setReportBrokenMedia(false);
        $settings->getPeer()->setFullFetch(false)->setCacheAllPeersOnStartup(false);
        $oldMask = umask(0077);
        try { $this->api = new \danog\MadelineProto\API(Vault::session($row), $settings); }
        finally { umask($oldMask); }
    }
    public function close(): void {
        if ($this->api) { $this->api = null; \danog\MadelineProto\API::finalize(); }
    }
    public function state(): string {
        return match ($this->api->getAuthorization()) {
            \danog\MadelineProto\API::WAITING_CODE => 'waiting_code',
            \danog\MadelineProto\API::WAITING_PASSWORD => 'waiting_password',
            \danog\MadelineProto\API::LOGGED_IN => 'connected',
            \danog\MadelineProto\API::WAITING_SIGNUP => 'signup_unsupported',
            default => 'disconnected',
        };
    }
    public function login(string $action, string $value): string {
        if ($action === 'phone') {
            if ($this->state() === 'connected') { throw new Fault('This private session is still authorized. Revoke it in Telegram Devices or create a fresh connection before signing into another account.', 409); }
            $this->api->phoneLogin($value);
        }
        elseif ($action === 'code' && $this->state() === 'waiting_code') { $this->api->completePhoneLogin($value); }
        elseif ($action === 'password' && $this->state() === 'waiting_password') { $this->api->complete2faLogin($value); }
        else { throw new Fault('The Telegram login step is no longer valid. Reload the connection.'); }
        return $this->state();
    }
    public function logout(): void { $this->api->logout(); }
    public function invoke(string $tool, array $arguments): mixed {
        $def = Catalog::all()[$tool]; $args = Catalog::arguments($tool, $arguments);
        if ($this->state() !== 'connected') { throw new Fault('Reconnect this Telegram account in WordPress.', 401); }
        if ($tool === 'get_me') { $self = $this->api->getSelf(); if (!empty($self['bot'])) { throw new Fault('This extension accepts personal accounts only.', 403); } return $self; }
        if ($tool === 'get_chat') { return $this->api->getInfo($args['peer']); }
        if ($tool === 'resolve_username') { return $this->api->getInfo('@' . ltrim($args['username'], '@')); }
        if ($tool === 'get_full_user') { return $this->api->getFullInfo($args['id']); }
        if ($tool === 'get_full_channel') { return $this->api->getFullInfo($args['channel']); }
        if ($tool === 'get_full_group') { return $this->api->getFullInfo(-$args['chat_id']); }
        if (in_array($tool, ['get_messages', 'get_channel_messages'], true)) { $args['id'] = array_map(static fn($id) => ['_' => 'inputMessageID', 'id' => $id], $args['id']); }
        if ($tool === 'get_notify_settings') { $args['peer'] = ['_' => 'inputNotifyPeer', 'peer' => $args['peer']]; }
        if ($tool === 'get_privacy') {
            $map = ['status' => 'StatusTimestamp', 'phone' => 'PhoneNumber', 'profile_photo' => 'ProfilePhoto', 'forwards' => 'Forwards', 'calls' => 'PhoneCall', 'invites' => 'ChatInvite'];
            $args['key'] = ['_' => 'inputPrivacyKey' . $map[$args['key']]];
        }
        if ($tool === 'send_message') {
            if (isset($args['reply_to_id'])) { $args['reply_to'] = ['_' => 'inputReplyToMessage', 'reply_to_msg_id' => $args['reply_to_id']]; unset($args['reply_to_id']); }
        }
        if ($tool === 'archive_chat') { $args = ['folder_peers' => [['_' => 'inputFolderPeer', 'peer' => $args['peer'], 'folder_id' => $args['archived'] ? 1 : 0]]]; }
        [$namespace, $method] = explode('.', $def['method'], 2);
        $args['cancellation'] = new \Amp\TimeoutCancellation(20);
        $args['floodWaitLimit'] = 0;
        return $this->api->{$namespace}->{$method}(...$args);
    }
}
