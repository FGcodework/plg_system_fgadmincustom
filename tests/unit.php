<?php

/**
 * Plain-PHP unit tests of the helpers (no PHPUnit needed): php tests/unit.php
 */

declare(strict_types=1);

define('_JEXEC', 1);

require __DIR__ . '/registry_stub.php';

foreach (['Helper/ColorHelper', 'Helper/MediaUrl', 'Helper/Schema', 'Helper/StyleContext', 'Helper/LegacyMapper', 'Helper/ContrastCheck', 'Helper/FooterSanitizer', 'Helper/SettingsSanitizer',
    'Helper/ImportExport', 'Login/BodyModifier', 'Login/ThemePresets', 'Login/CssBuilder', 'Admin/CssBuilder', 'Admin/LogoReplacer', 'Admin/MenuScript'] as $f) {
    require __DIR__ . '/../src/' . $f . '.php';
}

use FG\Plugin\System\AdminCustom\Admin\CssBuilder as AdminCss;
use FG\Plugin\System\AdminCustom\Admin\LogoReplacer as L;
use FG\Plugin\System\AdminCustom\Admin\MenuScript;
use FG\Plugin\System\AdminCustom\Helper\ColorHelper as C;
use FG\Plugin\System\AdminCustom\Helper\FooterSanitizer as FS;
use FG\Plugin\System\AdminCustom\Helper\ImportExport as IE;
use FG\Plugin\System\AdminCustom\Helper\LegacyMapper as LM;
use FG\Plugin\System\AdminCustom\Helper\ContrastCheck as CC;
use FG\Plugin\System\AdminCustom\Helper\MediaUrl as M;
use FG\Plugin\System\AdminCustom\Helper\SettingsSanitizer as S;
use FG\Plugin\System\AdminCustom\Helper\StyleContext as SC;
use FG\Plugin\System\AdminCustom\Login\BodyModifier as BM;
use FG\Plugin\System\AdminCustom\Login\CssBuilder as LoginCss;
use FG\Plugin\System\AdminCustom\Login\ThemePresets as P;
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

// ---------------------------------------------------------------- ColorHelper
$eq(C::clean(' #ABC '), '#abc', 'clean short');
$eq(C::clean('#8B5A2B'), '#8b5a2b', 'clean lower');
$eq(C::clean('#12345'), '', 'clean bad length');
$eq(C::clean('red'), '', 'clean named');
$eq(C::clean('#8b5a2bcc'), '#8b5a2bcc', 'clean alpha');
$eq(C::toRgb('#fff'), [255, 255, 255], 'rgb short');
$eq(C::contrastText('#000000'), '#ffffff', 'text on black');
$eq(C::contrastText('#ffffff'), '#000000', 'text on white');
$eq(round(C::contrast('#000000', '#ffffff'), 2), 21.0, 'contrast max');
$eq(C::shade('#808080', -1.0), '#000000', 'shade black');
$eq(C::toRgba('#ff0000', 50), 'rgba(255, 0, 0, 0.50)', 'rgba');

// translucent colors are laid over a base before the text color is chosen
$eq([C::alpha('#abc'), C::alpha('#aabbcc'), C::alpha('#aabbcc00'), C::alpha('#aabbccff')], [1.0, 1.0, 0.0, 1.0], 'alpha byte');
$eq(round(C::alpha('#00000080'), 2), 0.5, 'alpha half');
$eq([C::flatten('#336699'), C::flatten('#abc'), C::flatten('#336699ff')], ['#336699', '#aabbcc', '#336699'], 'flatten opaque unchanged');
$eq([C::flatten('#00000000'), C::flatten('#000000ff'), C::flatten('#ff000080')], ['#ffffff', '#000000', '#ff7f7f'], 'flatten over white');
$eq(C::flatten('#ffffff80', '#000000'), '#808080', 'flatten over a given base');
$eq([C::contrastText('#000000'), C::contrastText('#00000020'), C::contrastText('#00000020', '#000000'), C::contrastText('#000000f0')], ['#ffffff', '#000000', '#ffffff', '#ffffff'], 'faint black is light: dark text; strong black: light text');
$eq([C::contrastText('#8b5a2b'), C::contrastText('#8b5a2bcc')], ['#ffffff', '#000000'], 'translucent brown over white is judged lighter than the opaque one');
$eq(round(C::contrast('#00000000', '#ffffff'), 2), 1.0, 'fully transparent equals the base');
$eq(round(C::luminance('#ffffff80'), 4), 1.0, 'white over white');

// ------------------------------------------------------------------- ContrastCheck
$labels = static fn (array $low): array => array_column($low, 'label');
$eq(CC::low([]), [], 'contrast: nothing set, nothing to say');
$eq(CC::low(['login_card_text' => '#000000', 'login_card_bg' => '#ffffff']), [], 'contrast: black on white is fine');
$eq(CC::low(['login_card_text' => '#767676', 'login_card_bg' => '#ffffff']), [], 'contrast: 4.54:1 reaches AA');
$low = CC::low(['login_card_text' => '#777777', 'login_card_bg' => '#ffffff']);
$eq([$labels($low), $low[0]['ratio']], [['PLG_SYSTEM_FGADMINCUSTOM_LOGIN_CARD_TEXT_LABEL'], 4.4], 'contrast: 4.48:1 is below AA and is shown rounded down');
$eq($labels(CC::low(['login_btn_text' => '#ffffff', 'login_btn_bg' => '#f5d000', 'login_header_text' => '#fff', 'login_header_bg' => '#eee'])), ['PLG_SYSTEM_FGADMINCUSTOM_LOGIN_BTN_TEXT_LABEL', 'PLG_SYSTEM_FGADMINCUSTOM_LOGIN_HEADER_TEXT_LABEL'], 'contrast: several pairs, in settings order');
$eq(CC::low(['login_card_text' => '#ffffff']), [], 'contrast: a text color without a background is not judged');
$eq(CC::low(['login_card_text' => '', 'login_card_bg' => '#ffffff']), [], 'contrast: an empty text color is picked automatically, not judged');
$eq(CC::low(['login_card_text' => 'red', 'login_card_bg' => '#ffffff', 'login_btn_text' => ['x'], 'login_btn_bg' => '#000']), [], 'contrast: invalid values are ignored');
$eq(CC::low(['login_card_text' => '#000000', 'login_card_bg' => '#00000010']), [], 'contrast: a translucent background is judged over white');
$eq(\count(CC::low(['login_card_text' => '#ffffff', 'login_card_bg' => '#ffffff40'])), 1, 'contrast: white text on a faint translucent background is low');
$eq(\count(CC::low(['login_card_text' => '#ffffff80', 'login_card_bg' => '#000000'])) , 0, 'contrast: a translucent text color is laid over the background');

// ------------------------------------------------------------------- MediaUrl
$eq(M::clean(''), '', 'media empty');
$eq(M::clean('images/a.png#joomlaImage://local-images/a.png?width=1'), '/site/images/a.png', 'media fragment stripped');
$eq(M::clean('#joomlaImage://x'), '', 'media only fragment');
$eq(M::clean('images/logo.svg#icon'), '/site/images/logo.svg#icon', 'other fragments are kept (SVG)');
$eq(M::clean('images/logo.svg#icon#joomlaImage://local-images/logo.svg?width=1&height=1'), '/site/images/logo.svg#icon', 'only the Joomla fragment is stripped');
$eq(M::clean('images/a.png#JOOMLAIMAGE://x'), '/site/images/a.png', 'Joomla fragment, any case');
$eq(M::clean('images/a.png#joomlaImageX'), '/site/images/a.png#joomlaImageX', 'not the Joomla fragment: kept');
$eq([M::clean('a".png'), M::clean("a.png\n#x"), M::clean('a\\b.png'), M::clean('a<b>.png'), M::clean("a'b.png")], ['', '', '', '', ''], 'characters that could break out of url()/src are refused');
$eq(M::clean('https://e.com/a.svg#frag'), 'https://e.com/a.svg#frag', 'absolute URL keeps its fragment');
$eq(M::clean('https://e.com/a.png'), 'https://e.com/a.png', 'media absolute');
$eq(M::clean('//cdn.e.com/a.png'), '//cdn.e.com/a.png', 'media protocol relative');

// ----------------------------------------------------------- SettingsSanitizer
$r = S::sanitize([
    'brand_color' => '#8B5A2B', 'admin_header_color' => 'url(x)', 'admin_page_bg' => '',
    'ctrl_radius' => '12', 'card_radius' => '99', 'login_card_radius' => '55', 'admin_logo_height' => '40',
    'btn_shadow' => 'medium', 'card_shadow' => 'huge', 'login_btn_shadow' => '', 'login_card_shadow' => 'strong',
    'admin_sidebar_follow' => '1', 'login_hide_forgot' => '2', 'login_bg_type' => 'gradient', 'login_logo_fit' => 'stretch',
    'admin_logo' => 'images/a.png#joomlaImage://x', 'admin_logo_small' => '"><script>', 'login_bg_image' => 'javascript:alert(1)',
    'login_footer_text' => '<p>Made by <a href="https://x.sk">FG</a></p>', 'login_custom_css' => '.login { outline: 1px solid red }',
    'login_custom_js' => 'alert(1)', 'login_theme_preset' => 'dark', 'unknown' => 'x', 'export_json' => '{}', '_schema' => 'fgadmincustom/1',
    'login_enabled' => '0', 'login_use_brand' => '1', 'admin_enabled' => '1',
]);
$eq($r['values']['brand_color'], '#8b5a2b', 'san color');
$eq($r['values']['admin_page_bg'], '', 'san empty color');
$eq($r['values']['ctrl_radius'], 12, 'san radius');
$eq($r['values']['login_card_radius'], 55, 'san login card radius reaches 60');
$eq($r['values']['admin_logo_height'], 40, 'san height');
$eq($r['values']['btn_shadow'], 'medium', 'san shadow');
$eq($r['values']['login_btn_shadow'], '', 'san login shadow may inherit');
$eq($r['values']['login_card_shadow'], 'strong', 'san login shadow');
$eq($r['values']['admin_sidebar_follow'], 1, 'san flag');
$eq($r['values']['login_bg_type'], 'gradient', 'san list');
$eq($r['values']['admin_logo'], 'images/a.png#joomlaImage://x', 'san media');
$eq($r['values']['login_enabled'], 0, 'san scope flag');
$eq(array_key_exists('login_custom_js', $r['values']) || array_key_exists('login_custom_css', $r['values']), false, 'san custom JS and CSS never importable');
$eq(array_key_exists('login_theme_preset', $r['values']) || array_key_exists('unknown', $r['values']) || array_key_exists('export_json', $r['values']) || array_key_exists('_schema', $r['values']), false, 'san ignores non-importable');
$eq($r['rejected'], ['admin_header_color', 'card_radius', 'card_shadow', 'login_hide_forgot', 'login_logo_fit', 'admin_logo_small', 'login_bg_image'], 'san rejected');
$eq(S::sanitize(['btn_shadow' => ''])['rejected'], ['btn_shadow'], 'san brand shadow cannot be empty');
$eq(S::sanitize(['login_footer_text' => '<script>alert(1)</script>'])['rejected'], ['login_footer_text'], 'san footer script');
$eq(S::sanitize(['login_footer_text' => '<img src=x onerror=alert(1)>'])['rejected'], ['login_footer_text'], 'san footer handler');
$eq(S::sanitize(['login_footer_text' => '<a href="javascript:alert(1)">x</a>'])['rejected'], ['login_footer_text'], 'san footer js url');
foreach (['login_custom_css' => '@import url("https://example.com/x.css");', 'custom_css' => '.x{color:red}', 'login_custom_js' => 'alert(1)', 'custom_js' => 'alert(1)'] as $k => $v) {
    $eq(S::sanitize([$k => $v, 'brand_color' => '#112233']), ['values' => ['brand_color' => '#112233'], 'rejected' => []], "san $k is never importable (and not reported as invalid)");
}
$eq(S::sanitize(['ctrl_radius' => '-3'])['rejected'], ['ctrl_radius'], 'san negative');
$eq(S::sanitize(['brand_color' => ['x']])['rejected'], ['brand_color'], 'san array');
$eq(S::sanitize(['login_brand_color' => ''])['values'], ['login_brand_color' => ''], 'san empty override');

// ------------------------------------------------------------- FooterSanitizer
foreach ([
    'entity hex'          => '<a href="java&#x73;cript:alert(1)">x</a>',
    'entity decimal'      => '<a href="&#106;avascript:alert(1)">x</a>',
    'named tab entity'    => '<a href="java&Tab;script:alert(1)">x</a>',
    'raw tab'             => "<a href=\"java\tscript:alert(1)\">x</a>",
    'raw newline'         => "<a href=\"java\nscript:alert(1)\">x</a>",
    'leading control'     => "<a href=\"\x01javascript:alert(1)\">x</a>",
    'colon entity'        => '<a href="javascript&colon;alert(1)">x</a>',
    'upper case'          => '<A HREF="JAVASCRIPT:alert(1)">x</A>',
    'vbscript'            => '<a href="vbscript:msgbox(1)">x</a>',
    'data url'            => '<a href="data:text/html,x">x</a>',
    'data image'          => '<img src="data:image/svg+xml,x" alt="">',
    'file url'            => '<a href="file:///etc/passwd">x</a>',
    'backslash host'      => '<a href="\\\\evil.example">x</a>',
    'inline style'        => '<div style="position:fixed;inset:0;background:#fff">fake login</div>',
    'event handler'       => '<b onmouseover="alert(1)">x</b>',
    'handler on img'      => '<img src="/a.png" onerror="alert(1)">',
    'id attribute'        => '<b id="x">x</b>',
    'script'              => '<script>alert(1)</script>',
    'svg'                 => '<svg onload="alert(1)"></svg>',
    'math mXSS'           => '<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>',
    'iframe'              => '<iframe src="/x"></iframe>',
    'form'                => '<form action="/x"><button>x</button></form>',
    'stray closer'        => 'x</div><div class="evil">y',
    'srcset'              => '<img src="/a.png" srcset="javascript:alert(1)">',
    'too long'            => str_repeat('a', 5001),
    'invalid utf-8'       => "x\xff",
    'nul byte'            => "x\0y",
] as $name => $html) {
    $eq(FS::sanitize($html), null, "footer rejected: $name");
}

foreach ([
    'Made by <a href="https://fgcodework.github.io" target="_blank" rel="noopener">FG</a>' => 'Made by <a href="https://fgcodework.github.io" target="_blank" rel="noopener">FG</a>',
    '<a href="https://x.sk" target="_blank">x</a>'                                            => '<a href="https://x.sk" target="_blank" rel="noopener">x</a>',
    '<a href="https://x.sk" target="_blank" rel="nofollow">x</a>'                             => '<a href="https://x.sk" target="_blank" rel="nofollow noopener">x</a>',
    '<a href="/kontakt?a=1&amp;b=2#top">k</a>'                                                => '<a href="/kontakt?a=1&amp;b=2#top">k</a>',
    '<a href="mailto:info@example.sk">mail</a> | <a href="tel:+421900000000">tel</a>'          => '<a href="mailto:info@example.sk">mail</a> | <a href="tel:+421900000000">tel</a>',
    '<a href="//cdn.example.sk/x">x</a>'                                                      => '<a href="//cdn.example.sk/x">x</a>',
    '&copy; 2026 <strong>FG</strong><br><small class="muted">All rights reserved</small>'     => '© 2026 <strong>FG</strong><br><small class="muted">All rights reserved</small>',
    '<p>One</p><p>Two &amp; three &lt;b&gt;</p>'                                              => '<p>One</p><p>Two &amp; three &lt;b&gt;</p>',
    '<img src="/images/f.png" alt="FG" width="24" height="24"> FG'                            => '<img src="/images/f.png" alt="FG" width="24" height="24"> FG',
    '<b>unclosed <i>tags'                                                                     => '<b>unclosed <i>tags</i></b>',
    'a <!-- comment --> b'                                                                    => 'a  b',
    '</form><a href="/x">x</a>'                                                              => '<a href="/x">x</a>',
    ''                                                                                        => '',
    'Žilinská pôrodnica ľščťžýáíé'                                                            => 'Žilinská pôrodnica ľščťžýáíé',
] as $in => $expected) {
    $out = FS::sanitize((string) $in);
    $eq($out, $expected, 'footer kept/normalised: ' . substr((string) $in, 0, 40));
    $eq($out === null ? null : FS::sanitize($out), $out, 'footer sanitizing twice changes nothing: ' . substr((string) $in, 0, 30));
}

// Through the settings sanitizer: stored value is the rebuilt one; the dangerous ones are skipped and reported.
$r = S::sanitize(['login_footer_text' => '<a href="https://x.sk" target="_blank">x</a>']);
$eq($r['values']['login_footer_text'] ?? null, '<a href="https://x.sk" target="_blank" rel="noopener">x</a>', 'settings: footer stored in its rebuilt form');
$r = S::sanitize(['login_footer_text' => '<a href="java&#x73;cript:alert(1)">Test</a>', 'brand_color' => '#112233']);
$eq([$r['rejected'], $r['values']], [['login_footer_text'], ['brand_color' => '#112233']], 'settings: entity-encoded javascript: link is skipped, the rest still imported');

// Every theme preset (written by the plugin itself) passes the sanitizer once prefixed.
foreach (P::all() as $name => $preset) {
    $prefixed = [];

    foreach ($preset as $key => $value) {
        $prefixed['login_' . $key] = $value;
    }

    $eq(S::sanitize($prefixed)['rejected'], [], "preset $name valid");
}

// ---------------------------------------------------------------- StyleContext
$p = new Registry([
    'brand_color' => '#8B5A2B', 'ctrl_radius' => '12', 'card_radius' => '99', 'btn_shadow' => 'medium', 'card_shadow' => 'strong',
    'login_brand_color' => '', 'login_ctrl_radius' => '4', 'login_card_radius' => '', 'login_btn_shadow' => '', 'login_card_shadow' => 'none',
    'login_bg_type' => 'color', 'admin_header_color' => '#112233', 'admin_logo_height' => '40',
]);
$login = SC::login($p);
$eq($login->get('brand_color'), '#8b5a2b', 'login inherits Brand color (normalised)');
$eq($login->get('ctrl_radius'), '4', 'login override wins');
$eq($login->get('card_radius', 'x'), 'x', 'login ignores an out-of-range Brand radius (0-60)');
$eq($login->get('btn_shadow'), 'medium', 'login inherits Brand button shadow');
$eq($login->get('card_shadow'), 'none', 'login own explicit "none" wins over Brand');
$eq($login->get('bg_type'), 'color', 'login keys are unprefixed for the builder');
$eq($login->get('header_color', 'x'), 'x', 'login does not see admin keys');
$admin = SC::admin($p);
$eq($admin->get('brand_color'), '#8B5A2B', 'admin takes Brand raw');
$eq($admin->get('ctrl_radius'), '12', 'admin ignores login overrides');
$eq($admin->get('header_color'), '#112233', 'admin keys are unprefixed for the builder');
$eq($admin->get('bg_type', 'x'), 'x', 'admin does not see login keys');
$p->set('login_use_brand', '0');
$eq(SC::login($p)->get('btn_shadow', 'x'), 'x', 'login_use_brand off ignores Brand');
$eq(SC::login($p)->get('ctrl_radius'), '4', 'login_use_brand off keeps own values');
$eq(SC::normalise('ctrl_radius', ' 12 '), '12', 'normalise radius trims');
$eq(SC::normalise('ctrl_radius', '41'), null, 'normalise ctrl radius range');
$eq(SC::normalise('card_radius', '60'), '60', 'normalise card radius range');
$eq(SC::normalise('brand_color', 'red'), null, 'normalise invalid color');
$eq(SC::normalise('btn_shadow', 'huge'), null, 'normalise invalid shadow');

// ----------------------------------------------------------------- LegacyMapper
$loginOnly = ['brand_color' => '#1c3d5c', 'ctrl_radius' => '6', 'card_radius' => '12', 'btn_shadow' => 'subtle', 'card_shadow' => 'none',
    'logo' => 'images/l.png', 'bg_type' => 'gradient', 'hide_sidebar' => '1', 'hide_generator' => '1', 'use_page_style' => '0', 'custom_js' => 'x()'];
$m = LM::fromLegacy($loginOnly, null);
$eq($m['params']['brand_color'], '#1c3d5c', 'login only: Brand takes over the login brand color');
$eq($m['params']['login_brand_color'], '', 'login only: override empty (inherits)');
$eq($m['params']['login_enabled'] . $m['params']['admin_enabled'], '10', 'login only: scope');
$eq($m['params']['login_logo'] . '|' . $m['params']['login_bg_type'], 'images/l.png|gradient', 'login only: keys prefixed');
$eq($m['params']['login_custom_js'], 'x()', 'login only: custom_js carried over (same site)');
$eq(isset($m['params']['login_hide_generator']) || isset($m['params']['hide_generator']) || isset($m['params']['use_page_style']), false, 'login only: dropped keys');
$eq($m['notes'], ['GENERATOR_WAS_ON'], 'login only: generator note');
$eq($m['params']['login_hide_forgot'], 1, 'login only: option never saved -> the old default (hidden) is kept, not this plugin\'s new default');
$m2 = LM::fromLegacy($loginOnly + ['hide_forgot' => '0'], null);
$eq($m2['params']['login_hide_forgot'], '0', 'login only: a saved value is carried over as it was');
$m3 = LM::fromLegacy($loginOnly + ['hide_forgot' => '1'], null);
$eq($m3['params']['login_hide_forgot'], '1', 'login only: saved "hidden" stays hidden');
$eq((int) (new Registry([]))->get('hide_forgot', 0), 0, 'a missing value is "not hidden"');

$pageOnly = ['brand_color' => '#8b5a2b', 'header_color' => '#112233', 'logo' => 'images/p.png', 'ctrl_radius' => '8', 'menu_open_first' => '1', 'sidebar_follow' => '1'];
$m = LM::fromLegacy(null, $pageOnly);
$eq($m['params']['brand_color'] . '|' . $m['params']['admin_header_color'] . '|' . $m['params']['admin_logo'], '#8b5a2b|#112233|images/p.png', 'page only: brand + admin keys');
$eq($m['params']['login_enabled'] . $m['params']['admin_enabled'], '01', 'page only: scope');
$eq(isset($m['params']['login_brand_color']), false, 'page only: no login keys');

// both, login shared the page style: login keeps no overrides
$m = LM::fromLegacy(['use_page_style' => '1', 'brand_color' => '#111111', 'ctrl_radius' => '4', 'hide_generator' => '0'], ['brand_color' => '#8b5a2b', 'ctrl_radius' => '12']);
$eq($m['params']['brand_color'] . '|' . $m['params']['ctrl_radius'], '#8b5a2b|12', 'shared: Brand = page values');
$eq($m['params']['login_brand_color'] . '|' . $m['params']['login_ctrl_radius'], '|', 'shared: login inherits');
$eq($m['notes'], [], 'shared: no notes');

// both, login has its own different color
$m = LM::fromLegacy(['use_page_style' => '0', 'brand_color' => '#111111', 'ctrl_radius' => '12', 'hide_generator' => '0'], ['brand_color' => '#8b5a2b', 'ctrl_radius' => '12']);
$eq($m['params']['login_brand_color'], '#111111', 'own: login keeps its color as override');
$eq($m['params']['login_ctrl_radius'], '', 'own: equal radius is inherited');

// both, the login page had no brand color but the administrator has one
$m = LM::fromLegacy(['use_page_style' => '0', 'hide_generator' => '0'], ['brand_color' => '#8b5a2b']);
$eq($m['params']['login_use_brand'], 0, 'isolated: login does not use Brand');
$eq($m['notes'], ['LOGIN_BRAND_ISOLATED'], 'isolated: note');

// a disabled page plugin shares nothing with the login page
$m = LM::fromLegacy(['use_page_style' => '1', 'brand_color' => '#111111', 'hide_generator' => '0'], ['brand_color' => '#8b5a2b'], true, false);
$eq($m['params']['login_brand_color'], '#111111', 'disabled page: login keeps its own color');
$eq($m['params']['admin_enabled'], 0, 'disabled page: admin off');

// no scope switches for an import
$m = LM::fromLegacyJson($pageOnly);
$eq(isset($m['params']['admin_enabled']) || isset($m['params']['login_enabled']), false, 'import: scope untouched');
$eq(LM::detect($loginOnly), 'login', 'detect login');
$eq(LM::detect($pageOnly), 'page', 'detect page');
$eq(LM::detect(['_schema' => 'fgadmincustom/1', 'bg_type' => 'x']), null, 'detect own export');
$eq(LM::detect(['brand_color' => '#fff']), null, 'detect ambiguous');
$eq(LM::fromLegacyJson(['brand_color' => '#fff']), null, 'import of unknown shape');

// Valid old configurations convert to settings the sanitizer accepts (so a legacy import is never "invalid")
mt_srand(11);
$colors = ['', '#1c3d5c', '#FFFFFF', '#f5d000'];
$radii  = ['', '0', '12', '40'];
$sh     = ['none', 'subtle', 'medium', 'strong'];
$bad    = 0;

for ($i = 0; $i < 500; $i++) {
    $pick  = static fn (array $a) => $a[mt_rand(0, \count($a) - 1)];
    $lcfg  = ['brand_color' => $pick($colors), 'ctrl_radius' => $pick($radii), 'card_radius' => $pick($radii), 'btn_shadow' => $pick($sh), 'card_shadow' => $pick($sh),
        'logo_width' => '220', 'bg_type' => 'color', 'hide_sidebar' => '1', 'use_page_style' => (string) mt_rand(0, 1), 'hide_generator' => '1'];
    $pcfg  = ['brand_color' => $pick($colors), 'ctrl_radius' => $pick($radii), 'card_radius' => $pick($radii), 'btn_shadow' => $pick($sh), 'card_shadow' => $pick($sh),
        'sidebar_follow' => '1', 'logo_height' => '40'];
    $res   = LM::fromLegacy(mt_rand(0, 4) ? $lcfg : null, mt_rand(0, 1) ? $pcfg : null, (bool) mt_rand(0, 1), (bool) mt_rand(0, 1));
    $bad  += S::sanitize($res['params'])['rejected'] === [] ? 0 : 1;
}

$eq($bad, 0, 'mapped settings pass the sanitizer');

// ----------------------------------------------------------------- ImportExport
$eq(IE::paramsToArray('{"a":1}'), ['a' => 1], 'params json');
$eq(IE::paramsToArray('garbage'), [], 'params bad json');
$eq(IE::paramsToArray(new Registry(['a' => 2])), ['a' => 2], 'params registry');
$eq(IE::paramsToArray(null), [], 'params null');
$data = ['params' => '{"keep":1}'];
IE::applyValuesToData($data, ['brand_color' => '#fff']);
$eq(json_decode($data['params'], true), ['keep' => 1, 'brand_color' => '#fff'], 'apply keeps json string shape');
$data = (object) ['params' => new Registry(['keep' => 1])];
IE::applyValuesToData($data, ['x' => 5]);
$eq($data->params instanceof Registry && $data->params->get('x') === 5 && $data->params->get('keep') === 1, true, 'apply keeps Registry shape');
$data = ['params' => ['keep' => 1]];
IE::applyValuesToData($data, ['x' => 5]);
$eq($data['params'], ['keep' => 1, 'x' => 5], 'apply keeps array shape');
$data = ['params' => 42];
IE::applyValuesToData($data, ['x' => 5]);
$eq($data['params'], 42, 'apply leaves an unknown shape alone');

// ------------------------------------------------------------------- BodyModifier
$page = '<div class="login"><div class="main-brand logo text-center"><img src="atum.svg"><div class="inner">x</div></div><form></form></div>';
$out  = (string) BM::apply($page, '/a.png', '', '');
$eq(str_contains($out, 'alc-logo-img') && !str_contains($out, 'atum.svg'), true, 'body logo slot replaced');
$eq(str_contains($out, '</form></div>') && str_contains($out, '<form></form>'), true, 'body logo balanced div kept the rest');
$out = (string) BM::apply($page, '/a.png', '/d.png', '');
$eq(str_contains($out, 'alc-logo-light') && str_contains($out, 'alc-logo-dark'), true, 'body dark logo pair');
$out = (string) BM::apply($page, '', '/d.png', '');
$eq(str_contains($out, '/d.png') && !str_contains($out, 'alc-logo-dark'), true, 'body only dark logo used for all schemes');
$out = (string) BM::apply('<div class="login"><form></form></div>', '/a$1b\\0$0.png', '', '');
$eq(str_contains($out, 'src="/a$1b\\0$0.png"'), true, 'logo fallback: $ and \\ in the URL stay text');
$out = (string) BM::apply('<div class="login"><form></form></div>', '/a.png', '', '');
$eq(str_contains($out, '<div class="alc-logo">'), true, 'body logo fallback prepended');
$out = (string) BM::apply('<form></form>', '', '', 'Made by $1 \\2');
$eq(str_contains($out, '<div class="alc-footer">Made by $1 \\2</div>'), true, 'body footer literal');
$eq(BM::apply($page, '', '', ''), null, 'body nothing configured');
$once = (string) BM::apply('<div class="login"><form></form></div>', '', '', 'Made by FG');
$eq(substr_count($once, 'alc-footer'), 1, 'body footer added once');
$eq(BM::apply($once, '', '', 'Made by FG'), null, 'body footer not added twice (second plugin running on the same page)');

$eq(BM::apply('<div class="main-brand logo"><div>unbalanced', '/a.png', '', ''), null, 'body unbalanced untouched');

// ------------------------------------------------------------------- Login CssBuilder
$eq((new LoginCss(new Registry([])))->build() !== '', true, 'login css default has base rules');
$eq(str_contains((new LoginCss(SC::login(new Registry(['brand_color' => '#8b5a2b']))))->build(), '#8b5a2b'), true, 'login css gets the Brand color');
$forgotRule = static fn (array $p): bool => str_contains((new LoginCss(SC::login(new Registry($p))))->build(), 'form ~ .text-center');
$eq([$forgotRule([]), $forgotRule(['login_hide_forgot' => '0']), $forgotRule(['login_hide_forgot' => '1'])], [false, false, true], 'login css: recovery link hidden only when asked');
$eq(str_contains((new LoginCss(SC::login(new Registry(['login_use_brand' => '0', 'brand_color' => '#8b5a2b']))))->build(), '#8b5a2b'), false, 'login css without Brand');
$eq(str_contains((new LoginCss(SC::login(new Registry(['ctrl_radius' => '99']))))->build(), 'border-radius: 40px'), false, 'login css ignores an invalid Brand radius');
$eq(str_contains((new LoginCss(SC::login(new Registry(['login_ctrl_radius' => '99']))))->build(), 'border-radius: 40px'), true, 'login css radius clamped');
$eq(str_contains((new LoginCss(SC::login(new Registry(['login_custom_css' => '.x{color:red}']))))->build(), '.x{color:red}'), true, 'login css custom appended');

// ------------------------------------------------------------------- LogoReplacer
$logos = ['expanded' => '/m/big.png', 'collapsed' => '/m/small.png'];
$out   = L::replace('<header id="header"><div class="logo"><img src="a.svg" class="logo" alt=""><img src="b.svg" class="logo-collapsed"></div><div><img src="/avatar.png"></div></header><main><img src="/x/logo.png"></main>', $logos);
$eq(substr_count((string) $out, '/m/big.png'), 1, 'logo expanded');
$eq(substr_count((string) $out, '/m/small.png'), 1, 'logo collapsed');
$eq(str_contains((string) $out, '/avatar.png') && str_contains((string) $out, '/x/logo.png'), true, 'logo leaves other images');
$eq(str_contains((string) L::replace('<header id="header"><img class="logo-large" src="a.svg" srcset="a.svg 1x"><img class="logo-small" src="b.svg"></header>', $logos), 'srcset'), false, 'logo srcset removed');
$eq(L::replace('<main><img src="/logo.png"></main>', $logos), null, 'logo no header');
$eq(L::replace('<header>no images</header>', $logos), null, 'logo no images');
$eq(str_contains((string) L::replace('<header id="header"><img src="logo.png"></header>', ['expanded' => '/a$1\\b.png', 'collapsed' => '']), '/a$1\\b.png'), true, 'logo special chars literal');

// the real Atum 5 header: neither large image nor its URL needs to say "logo" (a custom Atum logo does not)
$atum = '<header id="header" class="header"><div class="header-inside"><div class="logo "><img loading="eager" src="/administrator/images/brand.png" alt="">'
    . '<img class="logo-collapsed" loading="eager" src="/administrator/images/brand-small.png" alt=""></div><img src="/avatar.png" alt="Logout"></div></header>';
$out  = (string) L::replace($atum, $logos);
$eq([substr_count($out, '/m/big.png'), substr_count($out, '/m/small.png'), str_contains($out, '/avatar.png'), str_contains($out, 'brand.png')], [1, 1, true, false], 'logo: Atum markup, custom logo URLs without "logo" in them');
$eq(str_contains((string) L::replace('<header id="header"><div class="logo"><a href="/"><img src="x.png"><img class="logo-collapsed" src="y.png"></a></div></header>', $logos), '/m/small.png'), true, 'logo: images wrapped in a link');
$a = '<header id="header"><a class="logo small" href="/"><img src="x.png" alt="Logout"><img src="y.png"></a><div class="header-item"><img src="/avatar.png"><img src="/icon.png"></div></header>';
$o = (string) L::replace($a, $logos);
$eq([substr_count($o, '/m/big.png'), substr_count($o, '/m/small.png'), str_contains($o, '/avatar.png') && str_contains($o, '/icon.png')], [1, 1, true], 'logo: the first two images of the header are not touched unless they are in the logo element');
$eq(L::replace('<header id="header"><img src="/avatar.png" alt="Catalogue logout"><img src="/icon.png" title="logo"></header>', $logos), null, 'logo: the words in alt/title do not make a logo');
$eq(L::replace('<header><div class="logo"><img src="a.svg"></div></header>', $logos), null, 'logo: only the Atum header (id="header") is looked at');
$eq(str_contains((string) L::replace('<header class="x"></header><header id="header"><div class="logo"><img src="a.svg"></div></header>', $logos), '/m/big.png'), true, 'logo: another <header> before it does not matter');

// ------------------------------------------------------------------- Admin CssBuilder
$eq((new AdminCss(new Registry([])))->build(), '', 'admin css empty');
$css = (new AdminCss(SC::admin(new Registry(['brand_color' => '#8b5a2b']))))->build();
$eq(str_contains($css, '--header-bg: #8b5a2b'), true, 'admin css brand header');
$eq(str_contains((new AdminCss(SC::admin(new Registry(['login_brand_color' => '#8b5a2b']))))->build(), '#8b5a2b'), false, 'admin css ignores login overrides');
$eq(str_contains((new AdminCss(SC::admin(new Registry(['ctrl_radius' => '99']))))->build(), 'border-radius: 40px'), true, 'admin css radius clamped');
$eq(str_contains((new AdminCss(SC::admin(new Registry(['admin_logo_height' => '500']))))->build(), 'height: 120px'), true, 'admin css logo height clamped');
$js = MenuScript::build();
$eq(str_contains($js, 'fgapcMenuOpenFirst') && !str_contains($js, '<script'), true, 'menu script shape (guard shared with FG Admin Page Customizer)');

echo $fail === 0 ? "OK: $n unit assertions passed\n" : "$fail of $n FAILED\n";
exit($fail === 0 ? 0 : 1);
