<?php

/**
 * @package     plg_system_fgadmincustom
 * @version     1.0.8
 * @license     GNU General Public License version 2 or later
 *
 * Turns the stored settings into the plain-key Registry one context's CSS
 * builder reads, and applies the Brand inheritance:
 *
 *  - login: own "login_*" value wins; where it is empty the Brand value is
 *    used (unless "login_use_brand" is off, then Brand is ignored).
 *  - admin: always the Brand values.
 *
 * No Joomla dependency besides the Registry.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminCustom\Helper;

use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

final class StyleContext
{
    /**
     * Settings of the login page under the plain key names of its builders.
     */
    public static function login(Registry $params): Registry
    {
        $out = [];

        foreach (Schema::LOGIN as $key) {
            $value = $params->get('login_' . $key, null);

            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        if ((int) $params->get('login_use_brand', 1) !== 0) {
            foreach (Schema::BRAND as $key) {
                if (self::hasValue($out[$key] ?? null)) {
                    continue;
                }

                $inherited = self::normalise($key, $params->get($key, null));

                if ($inherited !== null) {
                    $out[$key] = $inherited;
                }
            }
        }

        return new Registry($out);
    }

    /**
     * Settings of the logged-in administrator under the plain key names of its builders.
     */
    public static function admin(Registry $params): Registry
    {
        $out = [];

        foreach (Schema::BRAND as $key) {
            $value = $params->get($key, null);

            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        foreach (Schema::ADMIN as $key) {
            $value = $params->get('admin_' . $key, null);

            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        return new Registry($out);
    }

    /**
     * A usable Brand value of $key in its canonical form, or null when it is
     * empty or invalid. Colors are lower-cased, radii become plain integers
     * within the range of the login page.
     *
     * @param mixed $raw
     */
    public static function normalise(string $key, $raw): ?string
    {
        if (!\is_scalar($raw)) {
            return null;
        }

        $kind  = Schema::BRAND_KIND[$key] ?? null;
        $value = trim((string) $raw);

        if ($kind === 'color') {
            $clean = ColorHelper::clean($value);

            return $clean === '' ? null : $clean;
        }

        if ($kind === 'shadow') {
            return \in_array($value, Schema::SHADOWS, true) ? $value : null;
        }

        if (\is_array($kind)) {
            if (preg_match('/^\d{1,3}$/', $value) !== 1) {
                return null;
            }

            $number = (int) $value;

            return $number >= $kind[0] && $number <= $kind[1] ? (string) $number : null;
        }

        return null;
    }

    /**
     * Whether a login override counts as "set" (so it must not be replaced by Brand).
     * Any non-empty text does, valid or not: the builder then treats an invalid
     * value exactly like the original plugin did.
     *
     * @param mixed $value
     */
    private static function hasValue($value): bool
    {
        return $value !== null && trim((string) (\is_scalar($value) ? $value : '')) !== '';
    }
}
