<?php
/** Check every adapted argument against the pinned PHP client's real method signatures. */
require GETMCP_TELEGRAM_PATH . 'vendor/autoload.php';
$failures = [];
foreach (\Synergetic\Telegram\Catalog::all() as $name => $def) {
    if (!str_contains($def['method'], '.')) { if (!method_exists('danog\\MadelineProto\\API', $def['method'])) { $failures[] = "$name: helper does not exist"; } continue; }
    [$ns, $fn] = explode('.', $def['method']);
    $class = 'danog\\MadelineProto\\Namespace\\' . ucfirst($ns);
    if (!method_exists($class, $fn)) { $failures[] = "$name: method $class::$fn not declared"; continue; }
    $method = new ReflectionMethod($class, $fn);
    $params = []; foreach ($method->getParameters() as $p) { $params[$p->getName()] = $p; }
    foreach ((array)$def['schema']['properties'] as $arg => $schema) {
        if (in_array($arg, ['confirm', 'reply_to_id', 'archived'], true) || ($name === 'archive_chat' && $arg === 'peer')) { continue; }
        if (!isset($params[$arg])) { $failures[] = "$name: unsupported argument $arg"; }
    }
    foreach ($def['defaults'] as $arg => $value) {
        if (!isset($params[$arg])) { $failures[] = "$name: unsupported default $arg"; continue; }
        $type = (string)$params[$arg]->getType();
        if (is_array($value) && !str_contains($type, 'array')) { $failures[] = "$name: default $arg not an array"; }
        if (is_int($value) && !str_contains($type, 'int')) { $failures[] = "$name: default $arg not an integer"; }
    }
}
if ($failures) { echo implode("\n", $failures) . "\n"; exit(1); }
echo 'PASS pinned-library signatures for all ' . count(\Synergetic\Telegram\Catalog::all()) . " catalog tools\n";
