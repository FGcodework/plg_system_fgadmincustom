<?php

/**
 * @package     plg_system_fgadmincustom
 * @version     1.0.9
 * @license     GNU General Public License version 2 or later
 *
 * Translates the settings of the two plugins this one replaces
 * (plg_system_fgadminlogincustom 1.x and plg_system_fgadminpagecustom 1.x)
 * into the settings of this plugin, so that - for the login page and for the
 * administrator - the generated CSS stays exactly the same.
 *
 * Pure PHP (no Joomla, no database): used by the install script, which reads
 * the old plugins' params, and by the Import field for old JSON exports.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminCustom\Helper;

\defined('_JEXEC') or die;

final class LegacyMapper
{
    /** Keys found only in the export of the old login plugin. */
    private const LOGIN_ONLY = ['bg_type', 'card_bg', 'btn_bg', 'hide_sidebar', 'hide_chrome', 'logo_dark', 'footer_text', 'header_bg', 'custom_css'];

    /** Keys found only in the export of the old page plugin. */
    private const PAGE_ONLY = ['header_color', 'sidebar_follow', 'sidebar_color', 'page_bg', 'logo_small', 'menu_open_first'];

    /**
     * Which old plugin a settings JSON comes from: 'login', 'page' or null
     * (unknown, or already a JSON of this plugin).
     *
     * @param array<string, mixed> $json
     */
    public static function detect(array $json): ?string
    {
        if (isset($json['_schema'])) {
            return null;
        }

        $isLogin = array_intersect(self::LOGIN_ONLY, array_keys($json)) !== [];
        $isPage  = array_intersect(self::PAGE_ONLY, array_keys($json)) !== [];

        if ($isLogin === $isPage) {
            return null;
        }

        return $isLogin ? 'login' : 'page';
    }

    /**
     * New settings from the old ones.
     *
     * $login / $page are the params of the old plugins, null when that plugin
     * is not installed. $loginOn / $pageOn tell whether it was enabled (a
     * disabled plugin did nothing, so its context is switched off, and the
     * login plugin took no shared style from a disabled page plugin).
     * $setScope = false leaves login_enabled / admin_enabled out (used when
     * the settings arrive through the Import field).
     *
     * Notes (stable codes, translated by the caller):
     *  - GENERATOR_WAS_ON: the old login plugin removed the generator tag; this one does not.
     *  - LOGIN_BRAND_ISOLATED: the login page had no brand value of its own while the
     *    administrator has one; "Use Brand settings" is switched off for the login page
     *    so that it looks as before.
     *
     * @param array<string, mixed>|null $login
     * @param array<string, mixed>|null $page
     * @return array{params: array<string, mixed>, notes: string[]}
     */
    public static function fromLegacy(?array $login, ?array $page, bool $loginOn = true, bool $pageOn = true, bool $setScope = true): array
    {
        $out   = [];
        $notes = [];

        // ------------------------------------------------------------------
        // Effective brand values of the old login page.
        // ------------------------------------------------------------------
        $effective = [];

        if ($login !== null) {
            $sharing = $page !== null && $pageOn && (int) ($login['use_page_style'] ?? 0) === 1;

            foreach (Schema::BRAND as $key) {
                $shared = $sharing ? StyleContext::normalise($key, $page[$key] ?? null) : null;
                $own    = $login[$key] ?? null;

                $effective[$key] = $shared !== null ? $shared : $own;
            }
        }

        // ------------------------------------------------------------------
        // Brand: what the page plugin had, otherwise what the login page showed.
        // ------------------------------------------------------------------
        $brand = $page !== null ? self::pick($page, Schema::BRAND) : self::pick($effective, Schema::BRAND);

        foreach ($brand as $key => $value) {
            $out[$key] = $value;
        }

        // ------------------------------------------------------------------
        // Login page: everything under "login_", brand keys as overrides.
        // ------------------------------------------------------------------
        if ($login !== null) {
            foreach (Schema::LOGIN as $key) {
                // Brand keys become overrides below; hide_generator and use_page_style
                // are not part of the schema and are deliberately not carried over.
                if (\in_array($key, Schema::BRAND, true)) {
                    continue;
                }

                if (\array_key_exists($key, $login)) {
                    $out['login_' . $key] = $login[$key];
                }
            }

            // The old plugin hid the recovery link unless told otherwise; this one does not.
            // A site that never saved the option keeps the link hidden, as it was.
            if (!\array_key_exists('hide_forgot', $login)) {
                $out['login_hide_forgot'] = 1;
            }

            $isolate   = false;
            $overrides = [];

            foreach (Schema::BRAND as $key) {
                $raw        = $effective[$key] ?? null;
                $text       = \is_scalar($raw) ? trim((string) $raw) : '';
                $brandValue = StyleContext::normalise($key, $brand[$key] ?? null);

                if (Schema::BRAND_KIND[$key] === 'shadow') {
                    // The login page had a preset or, with nothing valid set, none.
                    $want            = StyleContext::normalise($key, $raw) ?? 'none';
                    $overrides[$key] = $want === ($brandValue ?? 'none') ? '' : $want;
                    continue;
                }

                if ($text === '') {
                    // Nothing set on the login page: "inherit" only says so while Brand is empty too.
                    $isolate         = $isolate || $brandValue !== null;
                    $overrides[$key] = '';
                    continue;
                }

                // Same clean value as Brand: inherit. Anything else (a different value, or text the
                // builder would read its own way) stays an explicit override, passed through as it was.
                $own             = StyleContext::normalise($key, $raw);
                $overrides[$key] = $own !== null && $own === $brandValue ? '' : $raw;
            }

            if ($isolate) {
                $notes[] = 'LOGIN_BRAND_ISOLATED';

                foreach (Schema::BRAND as $key) {
                    $raw  = $effective[$key] ?? null;
                    $text = \is_scalar($raw) ? trim((string) $raw) : '';

                    if (Schema::BRAND_KIND[$key] === 'shadow') {
                        $overrides[$key] = StyleContext::normalise($key, $raw) ?? 'none';
                    } else {
                        $overrides[$key] = $text === '' ? '' : $raw;
                    }
                }

                $out['login_use_brand'] = 0;
            }

            foreach ($overrides as $key => $value) {
                $out['login_' . $key] = $value;
            }

            if ((int) ($login['hide_generator'] ?? 1) === 1) {
                $notes[] = 'GENERATOR_WAS_ON';
            }
        }

        // ------------------------------------------------------------------
        // Administrator.
        // ------------------------------------------------------------------
        if ($page !== null) {
            foreach (Schema::ADMIN as $key) {
                if (\array_key_exists($key, $page)) {
                    $out['admin_' . $key] = $page[$key];
                }
            }
        }

        if ($setScope) {
            $out['login_enabled'] = $login !== null && $loginOn ? 1 : 0;
            $out['admin_enabled'] = $page !== null && $pageOn ? 1 : 0;
        }

        return ['params' => $out, 'notes' => $notes];
    }

    /**
     * Settings of this plugin from the JSON export of one old plugin
     * (for the Import field). The scope switches are left alone.
     *
     * @param array<string, mixed> $json
     * @return array{params: array<string, mixed>, notes: string[]}|null null = not an old export
     */
    public static function fromLegacyJson(array $json): ?array
    {
        $kind = self::detect($json);

        if ($kind === null) {
            return null;
        }

        return $kind === 'login'
            ? self::fromLegacy($json, null, true, true, false)
            : self::fromLegacy(null, $json, true, true, false);
    }

    /**
     * @param array<string, mixed> $source
     * @param string[]             $keys
     * @return array<string, mixed>
     */
    private static function pick(array $source, array $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            if (\array_key_exists($key, $source) && $source[$key] !== null) {
                $out[$key] = $source[$key];
            }
        }

        return $out;
    }
}
