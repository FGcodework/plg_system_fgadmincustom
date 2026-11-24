<?php

/**
 * @package     plg_system_fgadmincustom
 * @version     1.0.9
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminCustom\Helper;

\defined('_JEXEC') or die;

/**
 * Custom CSS and JavaScript run on every login page (and the script in the page head
 * of a page nobody is logged in to), so whoever can write them can read a password or
 * act as the next person who logs in. In Joomla an Administrator may edit plugins, so
 * the two fields are limited to users with the global "core.admin" right (Super Users).
 *
 * This class is the pure part: given the params a user is about to save and the params
 * stored now, it puts the stored custom code back.
 */
final class CustomCodeGuard
{
    /** Settings that hold code. */
    public const KEYS = ['login_custom_css', 'login_custom_js'];

    /**
     * @param string $newJson    params as they are about to be stored
     * @param string $storedJson params as they are stored now
     *
     * @return array{0: string, 1: bool} the params to store, and whether a change of the custom code was undone
     */
    public static function restore(string $newJson, string $storedJson): array
    {
        $new    = self::decode($newJson);
        $stored = self::decode($storedJson);

        $reverted = false;

        foreach (self::KEYS as $key) {
            $before = isset($stored[$key]) && \is_string($stored[$key]) ? $stored[$key] : '';
            $after  = isset($new[$key]) && \is_string($new[$key]) ? $new[$key] : '';

            // A browser sends line breaks of a textarea as CRLF; that alone is not a change.
            if (self::normalize($before) !== self::normalize($after)) {
                $reverted = true;
            }

            if ($before === '') {
                unset($new[$key]);
            } else {
                $new[$key] = $before;
            }
        }

        $json = $new === [] ? '{}' : json_encode($new, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [$json === false ? $newJson : $json, $reverted];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $json): array
    {
        $data = json_decode($json, true);

        return \is_array($data) ? $data : [];
    }

    private static function normalize(string $text): string
    {
        return trim(str_replace("\r\n", "\n", $text));
    }
}
