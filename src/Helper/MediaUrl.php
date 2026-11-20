<?php

/**
 * @package     plg_system_fgadmincustom
 * @license     GNU General Public License version 2 or later
 *
 * Normalises a media-field value to a usable URL.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminCustom\Helper;

use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

final class MediaUrl
{
    /**
     * Strips the #joomlaImage adapter fragment (and only that) from a media
     * field value and returns an absolute URL, or an empty string. Any other
     * fragment, such as the #icon of an SVG sprite, is kept.
     */
    public static function clean(string $value): string
    {
        $value = trim($value);

        // "images/a.png#joomlaImage://local-images/a.png?width=1&height=1" - appended by the media field.
        $value = (string) preg_replace('/#joomlaImage:.*$/is', '', $value);

        // Quotes, backslashes, brackets and control characters would break out of the
        // url("...") / src="..." they end up in; no real file name has them.
        if ($value === '' || preg_match('/["\'\\\\<>\x00-\x1f]/', $value) === 1) {
            return '';
        }

        // Already absolute?
        if (preg_match('#^(https?:)?//#i', $value) === 1) {
            return $value;
        }

        return Uri::root(true) . '/' . ltrim($value, '/');
    }
}
