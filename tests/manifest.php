<?php

/**
 * Consistency of the manifest, the language files and the settings schema:
 *   php tests/manifest.php [<old-login-dir> <old-page-dir>]
 * With the two old plugin directories it also checks that every default value
 * is the one the old plugin had.
 */

declare(strict_types=1);

define('_JEXEC', 1);

require __DIR__ . '/../src/Helper/Schema.php';

use FG\Plugin\System\AdminCustom\Helper\Schema;

$root = dirname(__DIR__);
$fail = 0;
$n    = 0;
$ok   = static function (bool $cond, string $name) use (&$fail, &$n): void {
    $n++;

    if (!$cond) {
        $fail++;
        echo "FAIL $name\n";
    }
};

$xml = simplexml_load_file($root . '/fgadmincustom.xml');
$ok($xml !== false, 'manifest is valid XML');

$names = [];
$keys  = [];

foreach ($xml->xpath('//field') as $field) {
    $names[(string) $field['name']] = $field;

    foreach (['label', 'description'] as $attr) {
        if (isset($field[$attr])) {
            $keys[(string) $field[$attr]] = true;
        }
    }

    foreach ($field->xpath('option') as $option) {
        if (str_starts_with((string) $option, 'PLG_')) {
            $keys[(string) $option] = true;
        }
    }
}

foreach ($xml->xpath('//fieldset[@label]') as $fieldset) {
    $keys[(string) $fieldset['label']] = true;
}

$keys['PLG_SYSTEM_FGADMINCUSTOM_XML_DESCRIPTION'] = true;

// Every key the manifest and the code use exists in both languages.
$code = '';

foreach (['src/Extension/AdminCustom.php', 'script.php'] as $f) {
    $code .= file_get_contents($root . '/' . $f);
}

preg_match_all("/'(PLG_SYSTEM_FGADMINCUSTOM_[A-Z0-9_]+)'/", $code, $m);

foreach ($m[1] as $k) {
    $keys[$k] = true;
}

foreach (['en-GB', 'sk-SK'] as $tag) {
    $ini = parse_ini_file($root . "/language/$tag/plg_system_fgadmincustom.ini");
    $ok($ini !== false, "$tag ini parses");

    foreach (array_keys($keys) as $k) {
        $ok(isset($ini[$k]) && $ini[$k] !== '', "$tag has key $k");
    }

    foreach ($ini as $k => $v) {
        if ($k !== 'PLG_SYSTEM_FGADMINCUSTOM_FIELD_SUPPORT_DESC') {
            $ok(!str_contains($v, '<') && !str_contains($v, '>'), "$tag $k has no angle brackets");
        }

        $ok(!str_contains($v, 'fgalc') && !str_contains($v, 'fgapc'), "$tag $k mentions no old recovery switch");
    }

    $ok(!str_contains(file_get_contents($root . "/language/$tag/plg_system_fgadmincustom.ini"), "\r"), "$tag ini is LF");

    // Strings used by sprintf keep exactly one %s.
    foreach (['IMPORT_SKIPPED', 'REPLACED_PLUGIN_ENABLED', 'MIGRATION_FAILED'] as $k) {
        $ok(substr_count($ini['PLG_SYSTEM_FGADMINCUSTOM_' . $k], '%s') === 1, "$tag $k has one %s");
    }
}

// Manifest fields <-> importable settings.
$actions = ['support_note', 'export_json', 'import_json', 'login_theme_preset', 'login_custom_js'];

foreach (Schema::fields() as $key => $spec) {
    $ok(isset($names[$key]), "schema key $key has a manifest field");
}

foreach ($names as $name => $field) {
    $ok(isset(Schema::fields()[$name]) || \in_array($name, $actions, true), "manifest field $name is in the schema");
}

// Every key the builders read through StyleContext is a field.
foreach (Schema::LOGIN as $key) {
    $ok(isset($names['login_' . $key]), "login field login_$key exists");
}

foreach (Schema::ADMIN as $key) {
    $ok(isset($names['admin_' . $key]), "admin field admin_$key exists");
}

foreach (Schema::BRAND as $key) {
    $ok(isset($names[$key]), "brand field $key exists");
}

// showon references existing fields.
foreach ($names as $name => $field) {
    if (isset($field['showon'])) {
        preg_match_all('/([a-z_]+):/', (string) $field['showon'], $sm);

        foreach ($sm[1] as $target) {
            $ok(isset($names[$target]), "showon of $name points to $target");
        }
    }
}

$ok((string) $xml->version === '1.0.0', 'version 1.0.0');
$ok((string) $xml->scriptfile === 'script.php' && is_file($root . '/script.php'), 'script file present');

// Defaults equal the old plugins' defaults.
if ($argc >= 3) {
    $defaults = static function (string $file): array {
        $x   = simplexml_load_file($file);
        $out = [];

        foreach ($x->xpath('//field') as $f) {
            $out[(string) $f['name']] = isset($f['default']) ? (string) $f['default'] : null;
        }

        return $out;
    };

    $oldLogin = $defaults($argv[1] . '/fgadminlogincustom.xml');
    $oldPage  = $defaults($argv[2] . '/fgadminpagecustom.xml');
    $new      = $defaults($root . '/fgadmincustom.xml');

    foreach ($oldLogin as $name => $default) {
        if (\in_array($name, ['support_note', 'export_json', 'import_json', 'hide_generator', 'use_page_style', 'brand_color', 'card_shadow', 'btn_shadow', 'card_radius', 'ctrl_radius'], true)) {
            continue;
        }

        $ok(\array_key_exists('login_' . $name, $new) && $new['login_' . $name] === $default, "default of login_$name = " . var_export($default, true));
    }

    foreach ($oldPage as $name => $default) {
        if (\in_array($name, ['support_note', 'export_json', 'import_json', 'brand_color', 'btn_shadow', 'card_shadow', 'ctrl_radius', 'card_radius'], true)) {
            $ok(($new[$name] ?? null) === $default, "default of brand $name = " . var_export($default, true));
            continue;
        }

        $ok(\array_key_exists('admin_' . $name, $new) && $new['admin_' . $name] === $default, "default of admin_$name = " . var_export($default, true));
    }

    // The login brand overrides start empty so that Brand is inherited.
    foreach (['login_brand_color', 'login_ctrl_radius', 'login_card_radius', 'login_btn_shadow', 'login_card_shadow'] as $name) {
        $ok(($new[$name] ?? null) === null || $new[$name] === '', "login override $name starts empty");
    }

    $ok($new['login_use_brand'] === '1' && $new['login_enabled'] === '1' && $new['admin_enabled'] === '1', 'scope switches default on');
}

echo $fail === 0 ? "OK: $n manifest assertions passed\n" : "$fail of $n FAILED\n";
exit($fail === 0 ? 0 : 1);
