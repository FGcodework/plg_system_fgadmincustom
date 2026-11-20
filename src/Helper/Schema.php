<?php

/**
 * @package     plg_system_fgadmincustom
 * @version     1.0.2
 * @license     GNU General Public License version 2 or later
 *
 * The one place that knows how the settings of this plugin are named.
 *
 * Three groups of keys:
 *  - Brand (no prefix): brand_color, ctrl_radius, card_radius, btn_shadow,
 *    card_shadow - set once, used by the login page and the administrator.
 *  - login_*: settings of the login page. For the five brand keys they are
 *    optional overrides (empty = taken from Brand).
 *  - admin_*: settings of the logged-in administrator.
 *
 * The CSS builders keep the plain key names of the two plugins this one
 * replaced (logo, bg_type, header_color ...); StyleContext translates.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminCustom\Helper;

\defined('_JEXEC') or die;

final class Schema
{
    public const SHADOWS = ['none', 'subtle', 'medium', 'strong'];

    /** Settings shared by both contexts. */
    public const BRAND = ['brand_color', 'ctrl_radius', 'card_radius', 'btn_shadow', 'card_shadow'];

    /**
     * Brand settings that the login page may override: key => [min, max] for
     * radii, 'color' for the brand color, 'shadow' for the shadow presets.
     */
    public const BRAND_KIND = [
        'brand_color' => 'color',
        'ctrl_radius' => [0, 40],
        'card_radius' => [0, 60],
        'btn_shadow'  => 'shadow',
        'card_shadow' => 'shadow',
    ];

    /** Plain names of the login settings (without the "login_" prefix), brand overrides included. */
    public const LOGIN = [
        'logo', 'logo_dark', 'logo_width', 'logo_height', 'logo_fit',
        'hide_template_logo', 'hide_sidebar', 'hide_forgot', 'hide_chrome', 'footer_text',
        'bg_type', 'bg_color', 'grad_start', 'grad_end', 'grad_angle',
        'bg_image', 'bg_overlay', 'bg_overlay_opacity',
        'brand_color', 'card_bg', 'card_text', 'card_radius', 'card_shadow',
        'ctrl_radius', 'btn_shadow', 'btn_bg', 'btn_hover', 'btn_text', 'link_color',
        'header_bg', 'header_text', 'hide_lang_switcher', 'header_match_btn',
        'custom_css', 'custom_js',
    ];

    /**
     * Settings that can only be typed in by hand, never arrive through an import: script
     * runs on the login page next to the password field, and style can turn that page into
     * a look-alike without a single suspicious character, so no filter could make it safe.
     * (Legacy names of the old plugins included.)
     */
    public const NOT_IMPORTABLE = ['login_custom_js', 'login_custom_css', 'custom_js', 'custom_css'];

    /** Plain names of the administrator settings (without the "admin_" prefix); brand keys excluded. */
    public const ADMIN = [
        'header_color', 'sidebar_follow', 'sidebar_color', 'page_bg',
        'logo', 'logo_small', 'logo_height', 'menu_open_first',
    ];

    /**
     * How an imported value of each key is validated.
     * Types: color, media, flag, range [min, max], list [allowed...], footer.
     * "login_custom_js" and "login_custom_css" are deliberately absent: custom code and
     * custom styling are never importable (see NOT_IMPORTABLE).
     *
     * @return array<string, array{0: string, 1?: mixed, 2?: mixed}>
     */
    public static function fields(): array
    {
        $shadowOrInherit = ['', ...self::SHADOWS];

        return [
            // Brand
            'brand_color' => ['color'],
            'ctrl_radius' => ['range', 0, 40],
            'card_radius' => ['range', 0, 40],
            'btn_shadow'  => ['list', self::SHADOWS],
            'card_shadow' => ['list', self::SHADOWS],

            // Scope
            'login_enabled'   => ['flag'],
            'login_use_brand' => ['flag'],
            'admin_enabled'   => ['flag'],

            // Login: logo and layout
            'login_logo'               => ['media'],
            'login_logo_dark'          => ['media'],
            'login_logo_width'         => ['range', 40, 800],
            'login_logo_height'        => ['range', 20, 600],
            'login_logo_fit'           => ['list', ['contain', 'cover']],
            'login_hide_template_logo' => ['flag'],
            'login_hide_sidebar'       => ['flag'],
            'login_hide_forgot'        => ['flag'],
            'login_hide_chrome'        => ['flag'],
            'login_footer_text'        => ['footer'],

            // Login: background
            'login_bg_type'            => ['list', ['none', 'color', 'gradient', 'image']],
            'login_bg_color'           => ['color'],
            'login_grad_start'         => ['color'],
            'login_grad_end'           => ['color'],
            'login_grad_angle'         => ['range', 0, 360],
            'login_bg_image'           => ['media'],
            'login_bg_overlay'         => ['color'],
            'login_bg_overlay_opacity' => ['range', 0, 100],

            // Login: card and colors (brand overrides first)
            'login_brand_color' => ['color'],
            'login_ctrl_radius' => ['range', 0, 40],
            'login_card_radius' => ['range', 0, 60],
            'login_btn_shadow'  => ['list', $shadowOrInherit],
            'login_card_shadow' => ['list', $shadowOrInherit],
            'login_card_bg'     => ['color'],
            'login_card_text'   => ['color'],
            'login_btn_bg'      => ['color'],
            'login_btn_hover'   => ['color'],
            'login_btn_text'    => ['color'],
            'login_link_color'  => ['color'],

            // Login: header bar
            'login_header_bg'          => ['color'],
            'login_header_text'        => ['color'],
            'login_hide_lang_switcher' => ['flag'],
            'login_header_match_btn'   => ['flag'],

            // Administrator
            'admin_header_color'    => ['color'],
            'admin_sidebar_follow'  => ['flag'],
            'admin_sidebar_color'   => ['color'],
            'admin_page_bg'         => ['color'],
            'admin_logo'            => ['media'],
            'admin_logo_small'      => ['media'],
            'admin_logo_height'     => ['range', 16, 120],
            'admin_menu_open_first' => ['flag'],
        ];
    }
}
