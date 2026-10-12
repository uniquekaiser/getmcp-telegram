<?php
use Synergetic\Telegram\PrimeFactor;
$cases = [15, 221, 1000000016000000063, 4611685975477714963];
foreach ($cases as $pq) {
    $start = hrtime(true); $p = PrimeFactor::factor($pq);
    if ($p <= 1 || $p >= $pq || $pq % $p !== 0) { throw new RuntimeException('Invalid factorization.'); }
    echo 'PASS public PQ ' . $pq . ' in ' . round((hrtime(true) - $start) / 1e9, 3) . " seconds\n";
}
require GETMCP_TELEGRAM_PATH . 'vendor/autoload.php';
$source = file_get_contents(GETMCP_TELEGRAM_PATH . 'vendor/danog/madelineproto/src/MTProtoSession/AuthKeyHandler.php');
if (str_contains($source, "'python_single") || !str_contains($source, 'PrimeFactor::factor')) { throw new RuntimeException('Unexpected factorization subprocess fallback.'); }
$before = [ini_get('memory_limit'), ini_get('max_execution_time'), ini_get('error_log'), error_reporting()];
new \danog\MadelineProto\Settings();
$after = [ini_get('memory_limit'), ini_get('max_execution_time'), ini_get('error_log'), error_reporting()];
if ($before !== $after) { throw new RuntimeException('Library altered WordPress runtime limits/logging.'); }
echo "PASS factorization route uses PHP/GMP only; library light startup preserves hosting limits/logging\n";
$logger = new \danog\MadelineProto\Settings\Logger();
$restored = unserialize(serialize($logger));
if ($restored->getType() !== \danog\MadelineProto\Logger::CALLABLE_LOGGER || !is_callable($restored->getExtra())) { throw new RuntimeException('Session reload enabled raw provider logging.'); }
echo "PASS serialized logger resumes with no-op callback and no provider payload logs\n";
\Amp\File\filesystem(new \Amp\File\Driver\BlockingFilesystemDriver());
$probe = \Synergetic\Telegram\Vault::root() . '/qa-probe-' . bin2hex(random_bytes(6));
\Amp\File\write($probe, 'synthetic in-process filesystem probe');
if (\Amp\File\read($probe) !== 'synthetic in-process filesystem probe') { throw new RuntimeException('Filesystem bootstrap mismatch.'); }
\Amp\File\deleteFile($probe);
echo "PASS pre-session filesystem read/write uses the explicit blocking driver\n";
