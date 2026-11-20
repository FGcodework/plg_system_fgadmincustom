<?php

/**
 * @package     plg_system_fgadmincustom
 * @license     GNU General Public License version 2 or later
 *
 * Pure color maths (hex only), free of any Joomla dependency so it can be unit tested.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminCustom\Helper;

\defined('_JEXEC') or die;

final class ColorHelper
{
    /**
     * Validates a hex color (#rgb, #rrggbb, #rrggbbaa); returns it lower-cased or ''.
     */
    public static function clean(string $value): string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) !== 1) {
            return '';
        }

        return strtolower($value);
    }

    /**
     * Opacity of a hex color: 1.0 for #rgb / #rrggbb, the alpha byte of #rrggbbaa as 0..1.
     */
    public static function alpha(string $hex): float
    {
        $hex = ltrim($hex, '#');

        return \strlen($hex) === 8 ? hexdec(substr($hex, 6, 2)) / 255 : 1.0;
    }

    /**
     * Lays a (possibly translucent) color over an opaque base and returns the
     * resulting opaque #rrggbb. The real base is not known to this class, so
     * white is the documented default; an opaque color comes back unchanged.
     */
    public static function flatten(string $hex, string $base = '#ffffff'): string
    {
        $alpha = self::alpha($hex);
        $rgb   = self::toRgb($hex);

        if ($alpha < 1.0) {
            $under = self::toRgb($base);

            foreach ($rgb as $i => $channel) {
                $rgb[$i] = (int) round($channel * $alpha + $under[$i] * (1 - $alpha));
            }
        }

        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * More readable of black/white on a hex background (WCAG contrast ratio).
     * A translucent background is judged as it looks over $base (white by default).
     */
    public static function contrastText(string $hexBg, string $base = '#ffffff'): string
    {
        $luminance = self::luminance($hexBg, $base);

        return 1.05 / ($luminance + 0.05) >= ($luminance + 0.05) / 0.05 ? '#ffffff' : '#000000';
    }

    /**
     * WCAG relative luminance (0..1); a translucent color is first laid over $base.
     */
    public static function luminance(string $hex, string $base = '#ffffff'): float
    {
        [$r, $g, $b] = self::toRgb(self::flatten($hex, $base));

        $linearize = static function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $linearize($r) + 0.7152 * $linearize($g) + 0.0722 * $linearize($b);
    }

    /**
     * WCAG contrast ratio (1..21) between two hex colors.
     */
    public static function contrast(string $hexA, string $hexB): float
    {
        $a = self::luminance($hexA);
        $b = self::luminance($hexB);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Darkens (negative $amount) or lightens (positive) a color by mixing it
     * with black or white; any alpha channel is dropped.
     */
    public static function shade(string $hex, float $amount): string
    {
        $target = $amount < 0 ? 0 : 255;
        $weight = min(1.0, abs($amount));
        $out    = '#';

        foreach (self::toRgb($hex) as $channel) {
            $out .= sprintf('%02x', (int) round($channel + ($target - $channel) * $weight));
        }

        return $out;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function toRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (\strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Hex color + opacity percentage as an rgba() string.
     */
    public static function toRgba(string $hex, int $opacityPercent): string
    {
        [$r, $g, $b] = self::toRgb($hex);

        return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, max(0, min(100, $opacityPercent)) / 100);
    }
}
