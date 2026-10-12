<?php
namespace Synergetic\Telegram;
final class Fault extends \RuntimeException {
    public function __construct(string $message, public readonly int $status = 400, public readonly string $reason = 'telegram_error') {
        parent::__construct($message);
    }
}
