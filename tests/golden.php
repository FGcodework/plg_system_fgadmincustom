<?php

/**
 * Golden-master comparison against the two plugins this one replaces.
 *
 *   php tests/golden.php <dir of plg_system_fgadminlogincustom 1.19.4> <dir of plg_system_fgadminpagecustom 1.4.3> [iterations] [seed]
 *
 * For many random configurations of the OLD plugins it checks that, after the
 * settings are migrated (LegacyMapper) and resolved (StyleContext), the NEW
 * builders produce byte-identical CSS and HTML to the old ones - for the login
 * page and for the administrator. Not shipped in the install package.
 */

declare(strict_types=1);

define('_JEXEC', 1);

if ($argc < 3) {
    fwrite(STDERR, "usage: php tests/golden.php <old-login-dir> <old-page-dir> [iterations] [seed]\n");
    exit(2);
}

[$oldLogin, $oldPage] = [rtrim($argv[1], '/'), rtrim($argv[2], '/')];
$iterations = (int) ($argv[3] ?? 3000);
$seed       = (int) ($argv[4] ?? 20261120);

require __DIR__ . '/registry_stub.php';

foreach (['ColorHelper', 'MediaUrl', 'SettingsSanitizer', 'ImportExport', 'BodyModifier', 'ThemePresets', 'CssBuilder', 'SharedStyle'] as $h) {
    require $oldLogin . '/src/Helper/' . $h . '.php';
}

foreach (['ColorHelper', 'SettingsSanitizer', 'ImportExport', 'LogoReplacer', 'MenuScript', 'CssBuilder'] as $h) {
    require $oldPage . '/src/Helper/' . $h . '.php';
}

foreach (['Helper/ColorHelper', 'Helper/MediaUrl', 'Helper/Schema', 'Helper/StyleContext', 'Helper/LegacyMapper', 'Login/CssBuilder', 'Login/BodyModifier', 'Admin/CssBuilder', 'Admin/LogoReplacer'] as $f) {
    require __DIR__ . '/../src/' . $f . '.php';
}

use FG\Plugin\System\AdminCustom\Admin\CssBuilder as NewAdminCss;
use FG\Plugin\System\AdminCustom\Admin\LogoReplacer as NewLogos;
use FG\Plugin\System\AdminCustom\Helper\LegacyMapper;
use FG\Plugin\System\AdminCustom\Helper\MediaUrl as NewMedia;
use FG\Plugin\System\AdminCustom\Helper\StyleContext;
use FG\Plugin\System\AdminCustom\Login\BodyModifier as NewBody;
use FG\Plugin\System\AdminCustom\Login\CssBuilder as NewLoginCss;
use FG\Plugin\System\AdminLoginCustom\Helper\BodyModifier as OldBody;
use FG\Plugin\System\AdminLoginCustom\Helper\CssBuilder as OldLoginCss;
use FG\Plugin\System\AdminLoginCustom\Helper\MediaUrl as OldMedia;
use FG\Plugin\System\AdminLoginCustom\Helper\SharedStyle as OldShared;
use FG\Plugin\System\AdminPageCustom\Helper\CssBuilder as OldAdminCss;
use FG\Plugin\System\AdminPageCustom\Helper\LogoReplacer as OldLogos;
use Joomla\Registry\Registry;

mt_srand($seed);

$pick = static fn (array $pool) => $pool[mt_rand(0, \count($pool) - 1)];

$colors  = ['', '', '#1c3d5c', '#FFFFFF', '#f5d000', '#000', '#8b5a2b', '#8b5a2bcc', 'red', '#12345', '#E8F0F8'];
$radii   = ['', '', '0', '4', '12', '40', '60', '99', 'abc', 12, '12.5', ' 7 '];
$shadows = ['none', 'none', 'subtle', 'medium', 'strong', '', 'huge'];
$flags   = [0, 1, '0', '1'];
$paths   = ['', '', 'images/a.png', 'images/a.png#joomlaImage://local-images/a.png?width=1', 'https://e.com/a.png', '//cdn.e.com/a.png'];

$loginPools = [
    'logo' => $paths, 'logo_dark' => $paths, 'logo_width' => ['', '220', '40', '800', '1000'],
    'logo_height' => ['', '60', '600', '10'], 'logo_fit' => ['contain', 'cover', '', 'stretch'],
    'hide_template_logo' => $flags, 'hide_sidebar' => $flags, 'hide_forgot' => $flags, 'hide_chrome' => $flags,
    'footer_text' => ['', '<p>Made by <a href="https://x.sk">FG</a></p>', 'Made by $1 \\2'],
    'bg_type' => ['none', 'color', 'gradient', 'image', ''], 'bg_color' => $colors, 'grad_start' => $colors,
    'grad_end' => $colors, 'grad_angle' => ['', '135', '0', '360', '500'], 'bg_image' => $paths,
    'bg_overlay' => $colors, 'bg_overlay_opacity' => ['', '40', '0', '100', '150'],
    'brand_color' => $colors, 'card_bg' => $colors, 'card_text' => $colors, 'card_radius' => $radii,
    'card_shadow' => $shadows, 'ctrl_radius' => $radii, 'btn_shadow' => $shadows, 'btn_bg' => $colors,
    'btn_hover' => $colors, 'btn_text' => $colors, 'link_color' => $colors, 'header_bg' => $colors,
    'header_text' => $colors, 'hide_lang_switcher' => $flags, 'header_match_btn' => $flags,
    'custom_css' => ['', '.x{color:red}'], 'custom_js' => ['', 'console.log(1)'],
    'hide_generator' => $flags, 'use_page_style' => [0, 1, 1, '1', '0'],
];

$pagePools = [
    'brand_color' => $colors, 'header_color' => $colors, 'sidebar_follow' => $flags, 'sidebar_color' => $colors,
    'page_bg' => $colors, 'logo' => $paths, 'logo_small' => $paths, 'logo_height' => ['', '16', '40', '500', '5'],
    'ctrl_radius' => $radii, 'card_radius' => $radii, 'btn_shadow' => $shadows, 'card_shadow' => $shadows,
    'menu_open_first' => $flags,
];

$randomParams = static function (array $pools, float $missing) use ($pick): array {
    $out = [];

    foreach ($pools as $key => $pool) {
        if (mt_rand(0, 1000) / 1000 < $missing) {
            continue;
        }

        $out[$key] = $pick($pool);
    }

    return $out;
};

$loginBodies = [
    '<div class="login"><div class="main-brand logo text-center"><img src="atum.svg"><div class="inner">x</div></div><form></form></div>',
    '<div class="login"><form></form></div>',
    '<html><body><div class="main-brand logo"><img src="a.svg"></div><form id="login-form"></form><footer></footer></body></html>',
];
$adminBodies = [
    '<header id="header"><div class="logo"><img src="a.svg" class="logo" alt=""><img src="b.svg" class="logo-collapsed"></div></header><main></main>',
    '<header><img class="logo-large" src="a.svg" srcset="a.svg 1x"><img class="logo-small" src="b.svg"></header>',
    '<header><a href="/"><img src="/a.svg"></a><img src="/b.svg"><img src="/avatar.png"></header>',
];

$stats    = ['login' => 0, 'admin' => 0, 'body' => 0, 'isolated' => 0, 'generator' => 0, 'skipped' => 0];
$failures = [];

for ($i = 0; $i < $iterations; $i++) {
    $missing = [0.0, 0.1, 0.4][$i % 3];
    $login   = mt_rand(0, 9) < 9 ? $randomParams($loginPools, $missing) : null;
    $page    = mt_rand(0, 9) < 5 ? $randomParams($pagePools, $missing) : null;
    $loginOn = mt_rand(0, 99) < 85;
    $pageOn  = mt_rand(0, 99) < 80;

    if ($login === null && $page === null) {
        $stats['skipped']++;
        continue;
    }

    $mapped = LegacyMapper::fromLegacy($login, $page, $loginOn, $pageOn);
    $new    = new Registry($mapped['params']);

    $stats['isolated']  += \in_array('LOGIN_BRAND_ISOLATED', $mapped['notes'], true) ? 1 : 0;
    $stats['generator'] += \in_array('GENERATOR_WAS_ON', $mapped['notes'], true) ? 1 : 0;

    // ----- login page -----
    if ($login !== null && $loginOn) {
        $own = new Registry($login);
        $eff = $own;

        if ($page !== null && $pageOn) {
            $eff = OldShared::apply($own, new Registry($page));
        }

        $oldCss = (new OldLoginCss($eff))->build();
        $newCss = (new NewLoginCss(StyleContext::login($new)))->build();
        $stats['login']++;

        if ($oldCss !== $newCss) {
            $failures[] = ['login css', $i, $login, $page, $loginOn, $pageOn, $mapped, $oldCss, $newCss];
        }

        foreach ($loginBodies as $b => $html) {
            $oldHtml = OldBody::apply(
                $html,
                OldMedia::clean((string) ($login['logo'] ?? '')),
                OldMedia::clean((string) ($login['logo_dark'] ?? '')),
                trim((string) ($login['footer_text'] ?? ''))
            );
            $newHtml = NewBody::apply(
                $html,
                NewMedia::clean((string) $new->get('login_logo', '')),
                NewMedia::clean((string) $new->get('login_logo_dark', '')),
                trim((string) $new->get('login_footer_text', ''))
            );
            $stats['body']++;

            if ($oldHtml !== $newHtml) {
                $failures[] = ['login body #' . $b, $i, $login, $page, $loginOn, $pageOn, $mapped];
            }
        }

        if ((string) ($login['custom_js'] ?? '') !== (string) $new->get('login_custom_js', '')) {
            $failures[] = ['login custom_js', $i, $login, $page, $loginOn, $pageOn, $mapped];
        }
    }

    // ----- administrator -----
    if ($page !== null && $pageOn) {
        $oldCss = (new OldAdminCss(new Registry($page)))->build();
        $newCss = (new NewAdminCss(StyleContext::admin($new)))->build();
        $stats['admin']++;

        if ($oldCss !== $newCss) {
            $failures[] = ['admin css', $i, $login, $page, $loginOn, $pageOn, $mapped, $oldCss, $newCss];
        }

        foreach ($adminBodies as $b => $html) {
            $oldLogos = ['expanded' => OldMedia::clean((string) ($page['logo'] ?? '')), 'collapsed' => OldMedia::clean((string) ($page['logo_small'] ?? ''))];
            $newLogos = ['expanded' => NewMedia::clean((string) $new->get('admin_logo', '')), 'collapsed' => NewMedia::clean((string) $new->get('admin_logo_small', ''))];

            if (OldLogos::replace($html, $oldLogos) !== NewLogos::replace($html, $newLogos)) {
                $failures[] = ['admin logos #' . $b, $i, $login, $page, $loginOn, $pageOn, $mapped];
            }
        }

        $flag = static fn ($v): int => (int) $v;

        if ($flag($page['menu_open_first'] ?? 0) !== $flag($new->get('admin_menu_open_first', 0))
            || $flag($page['sidebar_follow'] ?? 1) !== $flag($new->get('admin_sidebar_follow', 1)) && \array_key_exists('sidebar_follow', $page)) {
            $failures[] = ['admin flags', $i, $login, $page, $loginOn, $pageOn, $mapped];
        }
    }

    // ----- scope switches -----
    if ((int) $new->get('login_enabled', -1) !== ($login !== null && $loginOn ? 1 : 0)
        || (int) $new->get('admin_enabled', -1) !== ($page !== null && $pageOn ? 1 : 0)) {
        $failures[] = ['scope', $i, $login, $page, $loginOn, $pageOn, $mapped];
    }
}

printf(
    "login css: %d, login body: %d, admin css: %d | isolated: %d, generator notes: %d, skipped: %d | seed %d\n",
    $stats['login'],
    $stats['body'],
    $stats['admin'],
    $stats['isolated'],
    $stats['generator'],
    $stats['skipped'],
    $seed
);

if ($failures === []) {
    echo "GOLDEN OK: new output is identical to the old plugins\n";
    exit(0);
}

$byKind = [];

foreach ($failures as $f) {
    $byKind[$f[0]] = ($byKind[$f[0]] ?? 0) + 1;
}

echo 'GOLDEN FAILED: ' . json_encode($byKind) . "\n";

foreach (\array_slice($failures, 0, 3) as [$kind, $i, $login, $page, $loginOn, $pageOn, $mapped, $oldCss, $newCss]) {
    echo "--- $kind (iteration $i) loginOn=" . var_export($loginOn, true) . ' pageOn=' . var_export($pageOn, true) . "\n";
    echo 'login: ' . json_encode($login) . "\n" . 'page: ' . json_encode($page) . "\n" . 'mapped: ' . json_encode($mapped) . "\n";

    if (isset($oldCss, $newCss)) {
        $o = explode("\n", $oldCss);
        $n = explode("\n", $newCss);
        echo "only in OLD:\n  " . implode("\n  ", array_diff($o, $n)) . "\nonly in NEW:\n  " . implode("\n  ", array_diff($n, $o)) . "\n";
    }
}

exit(1);
