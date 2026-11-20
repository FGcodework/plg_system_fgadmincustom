<?php

/**
 * Tests of script.php (migration from the two old plugins) against a fake
 * database that executes the statements for real:  php tests/installer.php
 */

declare(strict_types=1);

define('_JEXEC', 1);
define('JPATH_ADMINISTRATOR', '/admin');
define('JPATH_PLUGINS', '/plugins');

require __DIR__ . '/registry_stub.php';
require __DIR__ . '/joomla_stub.php';
require __DIR__ . '/installer_stub.php';

use Joomla\CMS\Factory;
use Joomla\CMS\FakeApplication;
use Joomla\CMS\FakeDb;
use Joomla\CMS\Installer\InstallerAdapter;

$fail = 0;
$n    = 0;
$eq   = static function ($actual, $expected, string $name) use (&$fail, &$n): void {
    $n++;

    if ($actual !== $expected) {
        $fail++;
        echo "FAIL $name: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
    }
};

$row = static fn (string $el, array $params, int $enabled): array => ['element' => $el, 'params' => json_encode($params), 'enabled' => $enabled];

/** Runs a fresh script instance (it is an anonymous class, so the file is included each time). */
$run = static function (FakeDb $db, string $type = 'install'): array {
    Factory::$db  = $db;
    Factory::$app = new FakeApplication();
    $script       = (static fn () => include __DIR__ . '/../script.php')();
    $result       = $script->postflight($type, new InstallerAdapter());

    return [$result, Factory::$app->messages];
};

$newDefaults = ['brand_color' => '', 'login_enabled' => 1, 'admin_enabled' => 1, 'login_bg_type' => 'none', 'admin_logo_height' => 40];
$oldLogin    = ['brand_color' => '#1c3d5c', 'bg_type' => 'color', 'bg_color' => '#101010', 'logo' => 'images/l.png', 'use_page_style' => '0', 'hide_generator' => '1', 'custom_js' => 'x()'];
$oldPage     = ['brand_color' => '#8b5a2b', 'header_color' => '#112233', 'menu_open_first' => '1'];
$texts       = static fn (array $messages): string => implode("\n", array_column($messages, 1));
$types       = static fn (array $messages): array => array_column($messages, 0);
$params      = static fn (FakeDb $db): array => json_decode($db->rows['fgadmincustom']['params'], true);

// ---- both old plugins enabled
$db = new FakeDb();
$db->rows = [
    'fgadminlogincustom' => $row('fgadminlogincustom', $oldLogin, 1),
    'fgadminpagecustom'  => $row('fgadminpagecustom', $oldPage, 1),
    'fgadmincustom'      => $row('fgadmincustom', $newDefaults, 0),
];
[$ok, $msgs] = $run($db);
$p = $params($db);
$eq($ok, true, 'both: install never fails');
$eq([$p['brand_color'], $p['login_bg_type'], $p['login_bg_color'], $p['login_logo'], $p['admin_header_color'], $p['admin_menu_open_first']], ['#8b5a2b', 'color', '#101010', 'images/l.png', '#112233', '1'], 'both: settings of both plugins copied');
$eq([$p['login_enabled'], $p['admin_enabled'], $p['admin_logo_height']], [1, 1, 40], 'both: scopes on, untouched defaults kept');
$eq($p['login_brand_color'], '#1c3d5c', 'both: login own color (different from the page) kept as override');
$eq($p['login_custom_js'], 'x()', 'both: login custom JS carried over on the same site');
$eq(array_key_exists('hide_generator', $p) || array_key_exists('login_hide_generator', $p), false, 'both: generator setting dropped');
$eq([$db->rows['fgadmincustom']['enabled'], $db->rows['fgadminlogincustom']['enabled'], $db->rows['fgadminpagecustom']['enabled']], [1, 0, 0], 'both: new on, old off (not uninstalled)');
$eq(\count($db->rows), 3, 'both: no row removed');
$eq(str_contains($texts($msgs), 'were copied into this plugin and it was switched on'), true, 'both: success message');
$eq(str_contains($texts($msgs), 'FG Remove Generator'), true, 'both: generator warning when plg fgremovegenerator absent');

// generator plugin present and enabled: no warning
$db->rows = [
    'fgadminlogincustom' => $row('fgadminlogincustom', $oldLogin, 1),
    'fgremovegenerator'  => $row('fgremovegenerator', [], 1),
    'fgadmincustom'      => $row('fgadmincustom', $newDefaults, 0),
];
[, $msgs] = $run($db);
$eq(str_contains($texts($msgs), 'FG Remove Generator'), false, 'generator plugin enabled: no warning');
$eq([$db->rows['fgadmincustom']['enabled'], $db->rows['fgadminlogincustom']['enabled']], [1, 0], 'login only: new on, old off');
$eq([$params($db)['login_enabled'], $params($db)['admin_enabled']], [1, 0], 'login only: only the login scope is on');
$eq(\in_array('warning', $types($msgs), true), false, 'generator plugin enabled: no warnings at all');

// generator plugin installed but disabled: warning
$db->rows = [
    'fgadminlogincustom' => $row('fgadminlogincustom', $oldLogin, 1),
    'fgremovegenerator'  => $row('fgremovegenerator', [], 0),
    'fgadmincustom'      => $row('fgadmincustom', $newDefaults, 0),
];
[, $msgs] = $run($db);
$eq(str_contains($texts($msgs), 'FG Remove Generator'), true, 'generator plugin disabled: warning');

// no hide_generator in the old config (default is on) / explicitly off
$noGen = $oldLogin;
$noGen['hide_generator'] = '0';
$db->rows = ['fgadminlogincustom' => $row('fgadminlogincustom', $noGen, 1), 'fgadmincustom' => $row('fgadmincustom', $newDefaults, 0)];
[, $msgs] = $run($db);
$eq(str_contains($texts($msgs), 'FG Remove Generator'), false, 'hide_generator was off: no warning');

// ---- the old plugins were already disabled -> new one stays off, old stay off
$db->rows = [
    'fgadminlogincustom' => $row('fgadminlogincustom', $oldLogin, 0),
    'fgadminpagecustom'  => $row('fgadminpagecustom', $oldPage, 0),
    'fgadmincustom'      => $row('fgadmincustom', $newDefaults, 0),
];
[, $msgs] = $run($db);
$eq([$db->rows['fgadmincustom']['enabled'], $params($db)['login_enabled'], $params($db)['admin_enabled']], [0, 0, 0], 'disabled old: new plugin stays off, scopes off');
$eq(str_contains($texts($msgs), 'left disabled as well'), true, 'disabled old: message says so');
$eq($params($db)['brand_color'], '#8b5a2b', 'disabled old: settings still copied');

// ---- login on, page installed but disabled
$db->rows = [
    'fgadminlogincustom' => $row('fgadminlogincustom', $oldLogin, 1),
    'fgadminpagecustom'  => $row('fgadminpagecustom', $oldPage, 0),
    'fgadmincustom'      => $row('fgadmincustom', $newDefaults, 0),
];
$run($db);
$eq([$params($db)['login_enabled'], $params($db)['admin_enabled'], $params($db)['login_brand_color']], [1, 0, '#1c3d5c'], 'disabled page: admin scope off and login keeps its own brand');

// ---- the login page did not have a brand color but the page plugin does -> isolated notice
$db->rows = [
    'fgadminlogincustom' => $row('fgadminlogincustom', ['use_page_style' => '0', 'hide_generator' => '0'], 1),
    'fgadminpagecustom'  => $row('fgadminpagecustom', $oldPage, 1),
    'fgadmincustom'      => $row('fgadmincustom', $newDefaults, 0),
];
[, $msgs] = $run($db);
$eq([$params($db)['login_use_brand'], str_contains($texts($msgs), 'Use Brand settings')], [0, true], 'isolated: switch off and notice');
$eq(\in_array('notice', $types($msgs), true), true, 'isolated: notice type');

// ---- only the page plugin
$db->rows = ['fgadminpagecustom' => $row('fgadminpagecustom', $oldPage, 1), 'fgadmincustom' => $row('fgadmincustom', $newDefaults, 0)];
$run($db);
$eq([$params($db)['login_enabled'], $params($db)['admin_enabled'], $db->rows['fgadminpagecustom']['enabled'], $db->rows['fgadmincustom']['enabled']], [0, 1, 0, 1], 'page only: migrated');

// ---- nothing to migrate
$db->rows = ['fgadmincustom' => $row('fgadmincustom', $newDefaults, 0)];
[$ok, $msgs] = $run($db);
$eq([$ok, $msgs, $db->rows['fgadmincustom']['enabled'], $params($db), $db->executed > 0], [true, [], 0, $newDefaults, true], 'no old plugins: nothing changed, no messages');
$db->executed = 0;
$run($db);
$eq($db->executed, 0, 'no old plugins: no write statements');

// ---- an update never touches settings
$db->rows = [
    'fgadminlogincustom' => $row('fgadminlogincustom', $oldLogin, 1),
    'fgadmincustom'      => $row('fgadmincustom', ['brand_color' => '#abcdef'], 1),
];
[$ok, $msgs] = $run($db, 'update');
$eq([$ok, $msgs, $db->rows['fgadminlogincustom']['enabled'], $params($db)], [true, [], 1, ['brand_color' => '#abcdef']], 'update: no-op');
$run($db, 'discover_install');
$eq($db->rows['fgadminlogincustom']['enabled'], 0, 'discover_install migrates like install');

// ---- the new plugin's own row is missing (should not happen) -> nothing is touched
$db->rows = ['fgadminlogincustom' => $row('fgadminlogincustom', $oldLogin, 1)];
[$ok, $msgs] = $run($db);
$eq([$ok, $db->rows['fgadminlogincustom']['enabled'], $msgs], [true, 1, []], 'no own row: old plugin left running');

// ---- corrupt params JSON in the old plugin is treated as empty
$db->rows = [
    'fgadminlogincustom' => ['element' => 'fgadminlogincustom', 'params' => '{broken', 'enabled' => 1],
    'fgadmincustom'      => $row('fgadmincustom', $newDefaults, 0),
];
[$ok, $msgs] = $run($db);
$eq([$ok, $db->rows['fgadminlogincustom']['enabled'], \in_array('error', $types($msgs), true)], [true, 0, false], 'corrupt old params: still migrates');

// ---- failures: the installation succeeds, nothing is disabled
$db->rows = [
    'fgadminlogincustom' => $row('fgadminlogincustom', $oldLogin, 1),
    'fgadminpagecustom'  => $row('fgadminpagecustom', $oldPage, 1),
    'fgadmincustom'      => $row('fgadmincustom', $newDefaults, 0),
];
$db->failSelect = true;
[$ok, $msgs]    = $run($db);
$eq([$ok, $db->rows['fgadminlogincustom']['enabled'], $db->rows['fgadminpagecustom']['enabled'], $db->rows['fgadmincustom']['enabled']], [true, 1, 1, 0], 'DB failure on read: install ok, nothing changed');
$eq([$types($msgs), str_contains($texts($msgs), 'simulated database failure'), str_contains($texts($msgs), 'Import field')], [['warning'], true, true], 'DB failure on read: warning with fallback instructions');

$db->failSelect = false;
$db->failOn     = true;
[$ok, $msgs]    = $run($db);
$eq([$ok, $db->rows['fgadminlogincustom']['enabled'], $db->rows['fgadminpagecustom']['enabled'], $db->rows['fgadmincustom']['enabled']], [true, 1, 1, 0], 'save failure: old plugins keep running');
$eq(\in_array('warning', $types($msgs), true) && !str_contains($texts($msgs), 'were copied'), true, 'save failure: no false success message');

// ---- the writes are one transaction
$fresh = static function () use ($row, $oldLogin, $oldPage, $newDefaults): FakeDb {
    $d       = new FakeDb();
    $d->rows = [
        'fgadminlogincustom' => $row('fgadminlogincustom', $oldLogin, 1),
        'fgadminpagecustom'  => $row('fgadminpagecustom', $oldPage, 1),
        'fgadmincustom'      => $row('fgadmincustom', $newDefaults, 0),
    ];

    return $d;
};

$db          = $fresh();
[$ok, $msgs] = $run($db);
$eq([$db->log, $db->depth], [['start', 'commit'], 0], 'success: one transaction, committed, none left open');

$db            = $fresh();
$db->failDisable = true;
[$ok, $msgs]   = $run($db);
$eq([$ok, $db->log, $db->depth], [true, ['start', 'rollback'], 0], 'failure after the settings were saved: rolled back');
$eq([$db->rows['fgadminlogincustom']['enabled'], $db->rows['fgadminpagecustom']['enabled'], $db->rows['fgadmincustom']['enabled'], $db->rows['fgadmincustom']['params']], [1, 1, 0, json_encode($newDefaults)], 'failure after the settings were saved: the new plugin has no settings and is off, the old ones still on');
$eq([$types($msgs), str_contains($texts($msgs), 'simulated disable failure'), str_contains($texts($msgs), 'Nothing was changed')], [['warning'], true, true], 'failure after the settings were saved: the warning says nothing was changed');

$db             = $fresh();
$db->failCommit = true;
[$ok, $msgs]    = $run($db);
$eq([$ok, $db->log, $db->rows['fgadminlogincustom']['enabled'], $db->rows['fgadmincustom']['enabled']], [true, ['start', 'commit', 'rollback'], 1, 0], 'commit fails: rolled back, nothing changed');

$db               = $fresh();
$db->failDisable  = true;
$db->failRollback = true;
[$ok, $msgs]      = $run($db);
$eq([$ok, $types($msgs), str_contains($texts($msgs), 'rolled back'), str_contains($texts($msgs), 'Nothing was changed')], [true, ['warning'], true, false], 'rollback fails too: installation still ok, the warning does not claim nothing changed');

$db          = $fresh();
$db->failOn  = true;
[$ok, $msgs] = $run($db);
$eq([$db->log, $db->rows['fgadminlogincustom']['enabled']], [['start', 'rollback'], 1], 'failure on the first statement: rolled back');

$db          = $fresh();
[$ok, $msgs] = $run($db, 'update');
$eq($db->log, [], 'update: no transaction opened');

$db             = $fresh();
$db->rows       = ['fgadmincustom' => $row('fgadmincustom', $newDefaults, 0)];
[$ok, $msgs]    = $run($db);
$eq($db->log, [], 'nothing to migrate: no transaction opened');

// ---- requirements: checked before anything is installed
$script = static fn () => (static fn () => include __DIR__ . '/../script.php')();
Factory::$app = new FakeApplication();
$eq($script()->preflight('install', new InstallerAdapter()), true, 'preflight: Joomla 5.4 passes');
\Joomla\CMS\Version::$short = '4.4.9';
Factory::$app = new FakeApplication();
$eq([$script()->preflight('install', new InstallerAdapter()), array_column(Factory::$app->messages, 0), str_contains(implode(' ', array_column(Factory::$app->messages, 1)), 'Joomla 5')], [false, ['error'], true], 'preflight: Joomla 4 is refused with an error message');
\Joomla\CMS\Version::$short = '6.0.1';
Factory::$app = new FakeApplication();
$eq($script()->preflight('update', new InstallerAdapter()), true, 'preflight: Joomla 6 passes');
\Joomla\CMS\Version::$short = '5.4.11';

// ---- language files are loaded before messages are built
$run($fresh());
$eq(\count(Factory::$app->lang->loaded) >= 2, true, 'language files requested');

echo $fail === 0 ? "OK: $n installer assertions passed\n" : "$fail of $n FAILED\n";
exit($fail === 0 ? 0 : 1);
