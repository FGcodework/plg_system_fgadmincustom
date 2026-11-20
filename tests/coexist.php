<?php

/**
 * What happens when the old plugins are still enabled next to this one (the
 * installer disables them, but someone may switch them on again). Runs the
 * real old plugin classes, so it needs their sources; development only:
 *   php tests/coexist.php <old-login-dir> <old-page-dir>
 */

declare(strict_types=1);

define('_JEXEC', 1);

if ($argc < 3) {
    fwrite(STDERR, "usage: php tests/coexist.php <old-login-dir> <old-page-dir>\n");
    exit(2);
}

require __DIR__ . '/registry_stub.php';
require __DIR__ . '/joomla_stub.php';
require __DIR__ . '/app_stub.php';

foreach (glob(__DIR__ . '/../src/{Helper,Login,Admin,Extension}/*.php', GLOB_BRACE) as $f) {
    require_once $f;
}

foreach ([$argv[1], $argv[2]] as $dir) {
    foreach (array_merge(glob(rtrim($dir, '/') . '/src/Helper/*.php'), glob(rtrim($dir, '/') . '/src/Extension/*.php')) as $f) {
        require_once $f;
    }
}

use FG\Plugin\System\AdminCustom\Extension\AdminCustom;
use FG\Plugin\System\AdminCustom\Helper\LegacyMapper;
use FG\Plugin\System\AdminLoginCustom\Extension\AdminLoginCustom;
use FG\Plugin\System\AdminPageCustom\Extension\AdminPageCustom;
use Joomla\CMS\Log\Log;
use Joomla\Event\Event;

$fail = 0;
$n    = 0;
$eq   = static function ($actual, $expected, string $name) use (&$fail, &$n): void {
    $n++;

    if ($actual !== $expected) {
        $fail++;
        echo "FAIL $name: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
    }
};

$oldLogin = ['brand_color' => '#1c3d5c', 'bg_type' => 'color', 'bg_color' => '#101010', 'logo' => 'images/l.png', 'use_page_style' => '0', 'hide_generator' => '1', 'footer_text' => '<p>FG</p>'];
$oldPage  = ['brand_color' => '#8b5a2b', 'header_color' => '#112233', 'logo' => 'images/p.png', 'logo_small' => 'images/ps.png', 'menu_open_first' => '1'];
$new      = LegacyMapper::fromLegacy($oldLogin, $oldPage, true, true)['params'];

$loginHtml = '<div class="login"><div class="main-brand logo text-center"><img src="atum.svg"></div><form></form></div>';
$adminHtml = '<header id="header"><div class="logo"><img src="a.svg" class="logo"><img src="b.svg" class="logo-collapsed"></div></header>';

$make = static function (string $class, array $params, FakeApp $app) {
    $p = new $class(null, ['params' => $params]);
    $p->setApplication($app);

    return $p;
};
$go = static function (array $plugins): void {
    foreach ($plugins as $p) {
        if (method_exists($p, 'announce')) {
            $p->announce(new Event('onAfterDispatch'));
        }

        $p->getApplication()->messagesRendered = true;
    }

    foreach ($plugins as $p) {
        $p->injectAssets(new Event('onBeforeCompileHead'));
    }

    foreach ($plugins as $p) {
        $p->modifyBody(new Event('onAfterRender'));
    }
};

foreach (['old plugins first' => true, 'this plugin first' => false] as $label => $oldFirst) {
    Log::$entries = [];

    // ---- login page
    $app     = new FakeApp('administrator', true, ['option' => 'com_login'], 'atum', $loginHtml);
    $old     = [$make(AdminLoginCustom::class, $oldLogin, $app), $make(AdminPageCustom::class, $oldPage, $app)];
    $thrown  = null;

    try {
        $go($oldFirst ? [...$old, $make(AdminCustom::class, $new, $app)] : [$make(AdminCustom::class, $new, $app), ...$old]);
    } catch (\Throwable $e) {
        $thrown = $e->getMessage();
    }

    $eq($thrown, null, "$label / login: no exception");
    $eq(array_values(array_filter(array_keys($app->doc->wa->styles), static fn ($k) => str_contains($k, 'login'))) !== [], true, "$label / login: styled");
    $eq(substr_count($app->body, 'alc-logo-img'), 1, "$label / login: exactly one logo");
    $eq(substr_count($app->body, '<form></form>'), 1, "$label / login: the form is intact");
    $eq(substr_count($app->body, 'class="alc-footer"'), $oldFirst ? 1 : 2, "$label / login: footer count" . ($oldFirst ? '' : ' (the old plugin cannot know about ours: it adds its own)'));
    $eq($app->doc->generator, '', "$label / login: generator stays hidden by the old plugin");

    // ---- logged-in administrator
    $app    = new FakeApp('administrator', false, ['option' => 'com_content'], 'atum', $adminHtml);
    $old    = [$make(AdminLoginCustom::class, $oldLogin, $app), $make(AdminPageCustom::class, $oldPage, $app)];
    $thrown = null;

    try {
        $go($oldFirst ? [...$old, $make(AdminCustom::class, $new, $app)] : [$make(AdminCustom::class, $new, $app), ...$old]);
    } catch (\Throwable $e) {
        $thrown = $e->getMessage();
    }

    $eq($thrown, null, "$label / admin: no exception");
    $eq([substr_count($app->body, '/site/images/p.png'), substr_count($app->body, '/site/images/ps.png'), substr_count($app->body, '<img')], [1, 1, 2], "$label / admin: each header logo replaced once");
    $eq(count($app->doc->wa->styles), 2, "$label / admin: both stylesheets present");
    $both = array_filter($app->doc->wa->scripts, static fn ($s) => str_contains($s, 'fgapcMenuOpenFirst'));
    $eq(count($both), 2, "$label / admin: both menu scripts share one guard flag, so the menu is bound once");
    $eq(Log::$entries, [], "$label: nothing logged");
}

// The recovery switch of this plugin does not reach the old plugins (that is what the warning says).
$app = new FakeApp('administrator', true, ['option' => 'com_login', 'fgac' => 'off'], 'atum', $loginHtml);
$go([$make(AdminLoginCustom::class, $oldLogin, $app), $make(AdminCustom::class, $new, $app)]);
$eq(array_keys($app->doc->wa->styles), ['plg.system.fgadminlogincustom.style'], '?fgac=off switches off this plugin only');

$app = new FakeApp('administrator', true, ['option' => 'com_login', 'fgalc' => 'off'], 'atum', $loginHtml);
$go([$make(AdminLoginCustom::class, $oldLogin, $app), $make(AdminCustom::class, $new, $app)]);
$eq(array_keys($app->doc->wa->styles), ['plg.system.fgadmincustom.login'], '?fgalc=off switches off the old login plugin only');

echo $fail === 0 ? "OK: $n coexistence assertions passed\n" : "$fail of $n FAILED\n";
exit($fail === 0 ? 0 : 1);
