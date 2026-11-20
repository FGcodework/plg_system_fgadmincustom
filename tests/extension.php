<?php

/**
 * Tests of the plugin class with stand-ins for Joomla: contexts, switches,
 * recovery switch, isolation of errors, settings form (export/import/preset).
 *   php tests/extension.php
 */

declare(strict_types=1);

define('_JEXEC', 1);

require __DIR__ . '/registry_stub.php';
require __DIR__ . '/joomla_stub.php';
require __DIR__ . '/app_stub.php';

foreach (['Helper/ColorHelper', 'Helper/MediaUrl', 'Helper/Schema', 'Helper/StyleContext', 'Helper/LegacyMapper', 'Helper/FooterSanitizer', 'Helper/SettingsSanitizer', 'Helper/ImportExport',
    'Login/BodyModifier', 'Login/ThemePresets', 'Login/CssBuilder', 'Admin/CssBuilder', 'Admin/LogoReplacer', 'Admin/MenuScript', 'Extension/AdminCustom'] as $f) {
    require __DIR__ . '/../src/' . $f . '.php';
}

use FG\Plugin\System\AdminCustom\Extension\AdminCustom;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Event;
use Joomla\Registry\Registry;

$fail = 0;
$n    = 0;
$eq   = static function ($actual, $expected, string $name) use (&$fail, &$n): void {
    $n++;

    if ($actual !== $expected) {
        $fail++;
        echo "FAIL $name: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
    }
};

$plugin = static function (FakeApp $app, array|Registry $params = []): AdminCustom {
    $p = new AdminCustom(null, ['params' => $params]);
    $p->setApplication($app);

    return $p;
};

/** One request, in the order Joomla runs it: dispatch, then the message area is rendered, then the head, then the body. */
$run = static function (AdminCustom $p) {
    $app = $p->getApplication();
    $app->messagesRendered = false;
    $p->announce(new Event('onAfterDispatch'));
    $app->messagesRendered = true;
    $p->injectAssets(new Event('onBeforeCompileHead'));
    $p->modifyBody(new Event('onAfterRender'));
};

$loginHtml = '<div class="login"><div class="main-brand logo text-center"><img src="atum.svg"></div><form></form></div>';
$adminHtml = '<header id="header"><div class="logo"><img src="a.svg" class="logo"><img src="b.svg" class="logo-collapsed"></div></header>';
$params    = ['brand_color' => '#8b5a2b', 'login_logo' => 'images/login.png', 'admin_logo' => 'images/admin.png', 'admin_menu_open_first' => '1'];

// --------------------------------------------------------------- subscriptions
$eq(array_keys(AdminCustom::getSubscribedEvents()), ['onAfterDispatch', 'onBeforeCompileHead', 'onAfterRender', 'onContentPrepareForm'], 'subscribed events');

// ---------------------------------------------------------------- login page
$app = new FakeApp('administrator', true, ['option' => 'com_login'], 'atum', $loginHtml);
$run($plugin($app, $params));
$eq(array_keys($app->doc->wa->styles), ['plg.system.fgadmincustom.login'], 'login: only the login style is added');
$eq(str_contains($app->doc->wa->styles['plg.system.fgadmincustom.login'], '#8b5a2b'), true, 'login: inherits the Brand color');
$eq(str_contains($app->body, '/site/images/login.png') && !str_contains($app->body, 'atum.svg'), true, 'login: logo replaced');
$eq($app->doc->wa->scripts, [], 'login: no script without custom JS and without the admin menu option');

$app = new FakeApp('administrator', true, ['option' => 'com_login'], 'atum', $loginHtml);
$run($plugin($app, $params + ['login_custom_js' => 'x()']));
$eq(array_keys($app->doc->wa->scripts), ['plg.system.fgadmincustom.login.js'], 'login: custom JS added');

$app = new FakeApp('administrator', true, [], 'atum', $loginHtml);
$run($plugin($app, $params));
$eq(count($app->doc->wa->styles), 1, 'login: an empty option counts as the login page');

$app = new FakeApp('administrator', true, ['option' => 'com_login', 'fgac' => 'off'], 'atum', $loginHtml);
$run($plugin($app, $params));
$eq([$app->doc->wa->styles, $app->body], [[], $loginHtml], 'login: ?fgac=off changes nothing');
$eq($app->state, [], 'login: ?fgac=off stores nothing');

$app = new FakeApp('administrator', true, ['option' => 'com_login'], 'atum', $loginHtml);
$run($plugin($app, $params + ['login_enabled' => '0']));
$eq([$app->doc->wa->styles, $app->body], [[], $loginHtml], 'login: switched off in the settings');

$app = new FakeApp('administrator', true, ['option' => 'com_content'], 'atum', $loginHtml);
$run($plugin($app, $params));
$eq($app->doc->wa->styles, [], 'guest on another component: nothing');

$app = new FakeApp('site', true, ['option' => 'com_login'], 'cassiopeia', $loginHtml);
$run($plugin($app, $params));
$eq([$app->doc->wa->styles, $app->body], [[], $loginHtml], 'frontend: nothing');

// ---------------------------------------------------------- logged-in admin
$app = new FakeApp('administrator', false, ['option' => 'com_content'], 'atum', $adminHtml);
$run($plugin($app, $params));
$eq(array_keys($app->doc->wa->styles), ['plg.system.fgadmincustom.admin'], 'admin: only the admin style is added');
$eq(str_contains($app->doc->wa->styles['plg.system.fgadmincustom.admin'], '--header-bg: #8b5a2b'), true, 'admin: Brand color');
$eq(array_keys($app->doc->wa->scripts), ['plg.system.fgadmincustom.menu'], 'admin: menu script');
$eq(str_contains($app->body, '/site/images/admin.png'), true, 'admin: header logo replaced');

$app = new FakeApp('administrator', false, ['option' => 'com_content'], 'atum', $adminHtml);
$run($plugin($app, $params + ['admin_enabled' => '0']));
$eq([$app->doc->wa->styles, $app->body], [[], $adminHtml], 'admin: switched off in the settings');

$app = new FakeApp('administrator', false, ['option' => 'com_content', 'tmpl' => 'component'], 'atum', $adminHtml);
$run($plugin($app, $params));
$eq($app->doc->wa->styles, [], 'admin: popup views untouched');

$app = new FakeApp('administrator', false, ['option' => 'com_content'], 'othertemplate', $adminHtml);
$run($plugin($app, $params));
$eq($app->doc->wa->styles, [], 'admin: other templates untouched');

// Recovery switch of the logged-in administrator lives in the session.
$app = new FakeApp('administrator', false, ['option' => 'com_content', 'fgac' => 'off'], 'atum', $adminHtml);
$pl  = $plugin($app, $params);
$run($pl);
$eq([$app->doc->wa->styles, $app->body], [[], $adminHtml], 'admin: ?fgac=off switches off');
$eq($app->messages, [['warning', 'PLG_SYSTEM_FGADMINCUSTOM_SAFE_MODE_ACTIVE']], 'admin: ?fgac=off tells the user once');
$app->query = ['option' => 'com_content'];
$app->messages = [];
$run($pl);
$eq($app->doc->wa->styles, [], 'admin: stays off for the session');
$app->query = ['option' => 'com_content', 'fgac' => 'on'];
$run($pl);
$eq(array_keys($app->doc->wa->styles), ['plg.system.fgadmincustom.admin'], 'admin: ?fgac=on switches it back on');

// ------------------------------------- a replaced plugin is still enabled
use Joomla\CMS\Plugin\PluginHelper as PH;

PH::$enabled = ['system/fgadminpagecustom'];
$warning     = ['warning', 'PLG_SYSTEM_FGADMINCUSTOM_REPLACED_PLUGIN_ENABLED|System - FG Admin Page Customizer'];
$app         = new FakeApp('administrator', false, ['option' => 'com_content'], 'atum', $adminHtml);
$pl          = $plugin($app, $params);
$run($pl);
$eq($app->messages, [$warning], 'old plugin enabled: warned on any admin page, not only in the plugin settings');
$eq($pl->languageLoads >= 1, true, 'old plugin enabled: the plugin language is loaded first (it is not outside the plugin settings)');
$run($pl);
$eq(count($app->messages), 1, 'old plugin enabled: warned once per session');
$eq(array_keys($app->doc->wa->styles), ['plg.system.fgadmincustom.admin'], 'old plugin enabled: this plugin still does its job');

$app = new FakeApp('administrator', true, ['option' => 'com_login'], 'atum', $loginHtml);
$run($plugin($app, $params));
$eq($app->messages, [], 'old plugin enabled: nothing queued on the login page (nobody to read it)');

$app = new FakeApp('administrator', false, ['option' => 'com_content', 'fgac' => 'off'], 'atum', $adminHtml);
$run($plugin($app, $params));
$eq(count($app->messages), 2, 'old plugin enabled: also warned in safe mode (safe mode message + warning)');

$app = new FakeApp('administrator', false, ['option' => 'com_content'], 'atum', $adminHtml);
$run($plugin($app, $params + ['admin_enabled' => '0']));
$eq($app->messages, [$warning], 'old plugin enabled: also warned while the admin part is switched off');

PH::$enabled = [];
$app = new FakeApp('administrator', false, ['option' => 'com_content'], 'atum', $adminHtml);
$run($plugin($app, $params));
$eq($app->messages, [], 'no old plugin enabled: no warning');

// One session: first nothing is enabled, then the old plugin is switched on (exactly what happens in the plugin list).
PH::$enabled = [];
$app = new FakeApp('administrator', false, ['option' => 'com_content'], 'atum', $adminHtml);
$pl  = $plugin($app, $params);
$run($pl);
$run($pl);
$eq($app->messages, [], 'session: nothing enabled, nothing shown');
PH::$enabled = ['system/fgadminlogincustom'];
$run($pl);
$eq(array_column($app->messages, 1), ['PLG_SYSTEM_FGADMINCUSTOM_REPLACED_PLUGIN_ENABLED|System - FG Admin Login Customizer'], 'session: switching an old plugin on later still warns');
$run($pl);
$eq(count($app->messages), 1, 'session: and only once');
PH::$enabled = ['system/fgadminlogincustom', 'system/fgadminpagecustom'];
$run($pl);
$eq(array_column($app->messages, 1)[1] ?? '', 'PLG_SYSTEM_FGADMINCUSTOM_REPLACED_PLUGIN_ENABLED|System - FG Admin Page Customizer', 'session: a second old plugin switched on later warns about that one only');
$eq(count($app->messages), 2, 'session: no repeat for the first one');
PH::$enabled = [];

// ------------------------------------------------ errors never break a page
Log::$entries = [];
$app = new FakeApp('administrator', true, ['option' => 'com_login'], 'atum', $loginHtml);
$run($plugin($app, new ExplodingRegistry($params, 'admin_')));
$eq(count($app->doc->wa->styles) === 1 && str_contains($app->body, '/site/images/login.png'), true, 'a broken admin code path does not touch the login page');
$eq(Log::$entries, [], 'login page: nothing logged');

$app = new FakeApp('administrator', false, ['option' => 'com_content'], 'atum', $adminHtml);
$thrown = null;

try {
    $run($plugin($app, new ExplodingRegistry($params, 'admin_')));
} catch (\Throwable $e) {
    $thrown = $e;
}

$eq($thrown, null, 'a fatal error in the admin code path is contained');
$eq([$app->body, count(Log::$entries) >= 1, Log::$entries[0][0] ?? ''], [$adminHtml, true, 'plg_system_fgadmincustom'], 'the admin error is logged and the page is left as it was');

Log::$entries = [];
$app = new FakeApp('administrator', true, ['option' => 'com_login'], 'atum', $loginHtml);
$thrown = null;

try {
    $run($plugin($app, new ExplodingRegistry($params, 'login_')));
} catch (\Throwable $e) {
    $thrown = $e;
}

$eq([$thrown, $app->body, count(Log::$entries) >= 1], [null, $loginHtml, true], 'a fatal error in the login code path is contained, the form stays reachable');

// ----------------------------------------------------- settings form
$form = static function (array|object $data, string $name = 'com_plugins.plugin'): Event {
    return new Event('onContentPrepareForm', ['form' => new Form($name), 'data' => $data]);
};
$formApp = static fn (): FakeApp => new FakeApp('administrator', false, ['option' => 'com_plugins'], 'atum');

$app = $formApp();
$pl  = $plugin($app, []);
$data = (object) ['element' => 'fgadmincustom', 'folder' => 'system', 'params' => ['brand_color' => '#8b5a2b', 'login_custom_js' => 'x()', 'login_theme_preset' => '']];
$pl->prepareAdminForm($form($data));
$export = json_decode($data->params['export_json'], true);
$eq($export['_schema'] ?? null, 'fgadmincustom/1', 'form: export carries the schema marker');
$eq(($export['brand_color'] ?? null) . '|' . ($export['login_custom_js'] ?? null), '#8b5a2b|x()', 'form: export includes the settings (and custom JS, for a deliberate transfer)');
$eq(array_key_exists('export_json', $export) || array_key_exists('import_json', $export), false, 'form: export does not contain itself');

// a form of another plugin or screen is left alone
$other = (object) ['element' => 'other', 'folder' => 'system', 'params' => ['a' => 1]];
$plugin($formApp(), [])->prepareAdminForm($form($other));
$eq($other->params, ['a' => 1], 'form: other plugin untouched');
$other = (object) ['element' => 'fgadmincustom', 'folder' => 'system', 'params' => ['a' => 1]];
$plugin($formApp(), [])->prepareAdminForm($form($other, 'com_content.article'));
$eq($other->params, ['a' => 1], 'form: other form untouched');

// the plugins this one replaces warn when still enabled
PluginHelper::$enabled = ['system/fgadminlogincustom'];
$app  = $formApp();
$data = (object) ['element' => 'fgadmincustom', 'folder' => 'system', 'params' => []];
$plugin($app, [])->prepareAdminForm($form($data));
$eq($app->messages, [['warning', 'PLG_SYSTEM_FGADMINCUSTOM_REPLACED_PLUGIN_ENABLED|System - FG Admin Login Customizer']], 'form: warns about the enabled old login plugin');
PluginHelper::$enabled = [];

// import of this plugin's own JSON (custom JS is never taken)
$app  = $formApp();
$data = (object) ['element' => 'fgadmincustom', 'folder' => 'system', 'params' => ['import_json' => json_encode([
    '_schema' => 'fgadmincustom/1', 'brand_color' => '#112233', 'ctrl_radius' => '7', 'card_radius' => '999', 'login_custom_js' => 'steal()',
])]];
$plugin($app, [])->prepareAdminForm($form($data));
$eq([$data->params['brand_color'], $data->params['ctrl_radius'], $data->params['import_json']], ['#112233', 7, ''], 'import: valid values applied, field cleared');
$eq(array_key_exists('card_radius', $data->params) && $data->params['card_radius'] === 999, false, 'import: invalid value not applied');
$eq(array_key_exists('login_custom_js', $data->params), false, 'import: custom JS not applied');
$types = array_column($app->messages, 1);
$eq(\in_array('PLG_SYSTEM_FGADMINCUSTOM_IMPORT_JS_SKIPPED', $types, true) && \in_array('PLG_SYSTEM_FGADMINCUSTOM_IMPORT_APPLIED', $types, true), true, 'import: user is told what happened');
$eq(str_contains(implode(' ', $types), 'IMPORT_SKIPPED|card_radius'), true, 'import: rejected keys are listed');

// import of an export of the old login plugin
$app  = $formApp();
$old  = ['brand_color' => '#1c3d5c', 'bg_type' => 'color', 'bg_color' => '#101010', 'logo' => 'images/l.png', 'hide_generator' => '1', 'use_page_style' => '0', 'custom_js' => 'steal()', 'ctrl_radius' => '6'];
$data = (object) ['element' => 'fgadmincustom', 'folder' => 'system', 'params' => new Registry(['import_json' => json_encode($old)])];
$plugin($app, [])->prepareAdminForm($form($data));
$p = $data->params;
$eq([$p->get('login_bg_type'), $p->get('login_bg_color'), $p->get('login_logo'), $p->get('brand_color'), $p->get('login_custom_js', 'none')], ['color', '#101010', 'images/l.png', '#1c3d5c', 'none'], 'legacy import: login export converted, custom JS dropped');
$eq(\in_array('PLG_SYSTEM_FGADMINCUSTOM_IMPORT_LEGACY', array_column($app->messages, 1), true), true, 'legacy import: user is told');
$eq($p->get('login_enabled', 'untouched'), 'untouched', 'legacy import: scope switches untouched');

// invalid JSON
$app  = $formApp();
$data = (object) ['element' => 'fgadmincustom', 'folder' => 'system', 'params' => '{"import_json":"not json"}'];
$plugin($app, [])->prepareAdminForm($form($data));
$eq(array_column($app->messages, 1), ['PLG_SYSTEM_FGADMINCUSTOM_IMPORT_INVALID_JSON'], 'import: invalid JSON is reported');
$eq(\is_string($data->params) && json_decode($data->params, true)['import_json'] === '', true, 'import: JSON-string shape preserved, field cleared');

// theme preset applies to the login color fields
$app  = $formApp();
$data = (object) ['element' => 'fgadmincustom', 'folder' => 'system', 'params' => ['login_theme_preset' => 'corporate_blue', 'brand_color' => '#abcdef']];
$plugin($app, [])->prepareAdminForm($form($data));
$eq([$data->params['login_bg_type'], $data->params['login_btn_bg'], $data->params['login_theme_preset'], $data->params['brand_color']], ['gradient', '#1c3d5c', '', '#abcdef'], 'preset: login colors set, trigger reset, Brand untouched');
$eq(\in_array('PLG_SYSTEM_FGADMINCUSTOM_PRESET_APPLIED', array_column($app->messages, 1), true), true, 'preset: user is told');

$app  = $formApp();
$data = (object) ['element' => 'fgadmincustom', 'folder' => 'system', 'params' => ['login_theme_preset' => 'reset', 'login_brand_color' => '#123456']];
$plugin($app, [])->prepareAdminForm($form($data));
$eq([$data->params['login_brand_color'], $data->params['login_bg_type']], ['', 'none'], 'preset reset clears the login overrides');

// a form handler that fails must not break the page
$brokenData = new class () {
    public function __get($n)
    {
        throw new \RuntimeException('boom');
    }
};
$thrown = null;

try {
    $plugin($formApp(), [])->prepareAdminForm($form($brokenData));
} catch (\Throwable $e) {
    $thrown = $e;
}

$eq($thrown, null, 'form: an unexpected data shape never breaks the page');

echo $fail === 0 ? "OK: $n extension assertions passed\n" : "$fail of $n FAILED\n";
exit($fail === 0 ? 0 : 1);
