<?php

/**
 * @package     plg_system_fgadmincustom
 * @license     GNU General Public License version 2 or later
 *
 * Swaps the logo images inside the page header of the rendered HTML.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminCustom\Admin;

\defined('_JEXEC') or die;

final class LogoReplacer
{
    /**
     * Returns the body with the header logos replaced, or null when nothing changed.
     *
     * Written for the Atum header:
     *   <header id="header" ...><div class="logo"><img ...><img class="logo-collapsed" ...></div> ...
     * Only that header is looked at, and only its logo images; any other image (avatar,
     * icon, an image of a component) is left alone, also when the markup changes.
     *
     * @param array{expanded: string, collapsed: string} $logos Absolute URLs ('' = leave as is)
     */
    public static function replace(string $body, array $logos): ?string
    {
        if (preg_match('#<header\b[^>]*\bid="header"[^>]*>.*?</header>#is', $body, $header, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        [$block, $start] = $header[0];

        $candidates = self::logoImages($block);

        if ($candidates === []) {
            return null;
        }

        $offset  = 0;
        $changed = false;

        foreach ($candidates as $index => [$tag, $pos]) {
            $classes = self::classes($tag);
            $small   = array_intersect($classes, ['logo-collapsed', 'collapsed', 'logo-small', 'small', 'mini', 'logo-mini']) !== []
                || (array_intersect($classes, ['logo-expanded', 'expanded', 'logo-large', 'large']) === [] && $index > 0);
            $url     = $logos[$small ? 'collapsed' : 'expanded'];

            if ($url === '') {
                continue;
            }

            $src = strtr(htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), ['\\' => '\\\\', '$' => '\\$']);
            $new = preg_replace('#\s(?:srcset|sizes)=("[^"]*"|\'[^\']*\')#i', '', $tag) ?? $tag;
            $new = preg_match('#\ssrc=("[^"]*"|\'[^\']*\')#i', $new) === 1
                ? (preg_replace('#\ssrc=("[^"]*"|\'[^\']*\')#i', ' src="' . $src . '"', $new, 1) ?? $new)
                : (preg_replace('#^<img\b#i', '<img src="' . $src . '"', $new, 1) ?? $new);

            $block   = substr($block, 0, $pos + $offset) . $new . substr($block, $pos + $offset + \strlen($tag));
            $offset += \strlen($new) - \strlen($tag);
            $changed = true;
        }

        if ($changed) {
            return substr($body, 0, $start) . $block . substr($body, $start + \strlen($header[0][0]));
        }

        return null;
    }

    /**
     * The logo images of the header block as [tag, position in the block]:
     * the images inside the element with the class "logo" (a <div> or an <a> in Atum);
     * without such an element, the images that say "logo" in their class or file name.
     * The text of alt and title attributes never counts.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    private static function logoImages(string $block): array
    {
        if (preg_match_all('#<(div|a)\b[^>]*>#i', $block, $opens, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($opens[0] as $i => [$openTag, $openPos]) {
                if (!\in_array('logo', self::classes($openTag), true)) {
                    continue;
                }

                $from  = $openPos + \strlen($openTag);
                $close = stripos($block, '</' . strtolower($opens[1][$i][0]) . '>', $from);
                $inner = $close === false ? '' : substr($block, $from, $close - $from);

                if ($inner !== '' && preg_match_all('#<img\b[^>]*>#i', $inner, $found, PREG_OFFSET_CAPTURE) > 0) {
                    return array_map(static fn (array $img): array => [$img[0], $from + $img[1]], $found[0]);
                }

                break;
            }
        }

        if (preg_match_all('#<img\b[^>]*>#i', $block, $images, PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }

        return array_values(array_filter(
            $images[0],
            static function (array $img): bool {
                foreach (self::classes($img[0]) as $class) {
                    if (str_starts_with($class, 'logo')) {
                        return true;
                    }
                }

                // The file name of the image, not the whole tag.
                return preg_match('#\ssrc=["\'](?:[^"\'?\#]*/)?[^/"\'?\#]*(?:logo|brand)[^/"\'?\#]*(?:[?\#][^"\']*)?["\']#i', $img[0]) === 1;
            }
        ));
    }

    /**
     * @return string[] the class names of a tag, lower-cased
     */
    private static function classes(string $tag): array
    {
        if (preg_match('#\sclass=(?:"([^"]*)"|\'([^\']*)\')#i', $tag, $m) !== 1) {
            return [];
        }

        return array_values(array_filter(preg_split('#\s+#', strtolower($m[1] !== '' ? $m[1] : ($m[2] ?? ''))) ?: []));
    }
}
