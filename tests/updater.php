<?php
if (!defined('GETMCP_TELEGRAM_TESTING') || wp_get_environment_type() !== 'local') { throw new RuntimeException('Fixture only.'); }
use Synergetic\Telegram\Updater;
if (Updater::checker() !== null) { throw new RuntimeException('Source checkout updater must remain disabled.'); }
$version = GETMCP_TELEGRAM_VERSION;
$url = Updater::REPOSITORY . '/releases/download/v' . $version . '/getmcp-telegram-' . $version . '.zip';
if (!Updater::valid_package_url($url, $version) || Updater::valid_package_url($url . '?token=bad', $version) || Updater::valid_package_url(str_replace('.zip','-source.zip',$url), $version)) { throw new RuntimeException('Exact public asset policy failed.'); }
$info = Updater::normalize((object)['version'=>$version,'download_url'=>$url,'sections'=>['changelog'=>'<ul><li>[NEW] Test</li></ul>'],'icons'=>['1x'=>'https://example.test/icon.png']]);
if ($info->requires_php !== '8.3' || $info->name !== 'GetMCP Telegram Extension' || $info->icons['1x'] !== 'https://example.test/icon.png' || !str_contains($info->sections['changelog'],'<strong>[NEW]</strong>')) { throw new RuntimeException('Metadata normalization failed.'); }
echo "PASS source updater disabled, exact asset selection, rejected archive/token URLs, requirements, preserved icons and changelog HTML\n";
