<?php

/**
 * @package     plg_system_fgadmincustom
 * @version     1.0.2
 * @license     GNU General Public License version 2 or later
 *
 * Validates settings values coming from an imported JSON, key by key.
 * Custom JavaScript and custom CSS are never importable (Schema::NOT_IMPORTABLE).
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminCustom\Helper;

\defined('_JEXEC') or die;

final class SettingsSanitizer
{
    /**
     * Names of all settings an import may set.
     *
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(Schema::fields());
    }

    /**
     * Returns the valid values (normalised) and the names of the rejected keys.
     * Keys that are not importable (unknown, custom JS/CSS, the preset trigger,
     * the export/import fields) are ignored silently.
     *
     * @param array<string, mixed> $input
     * @return array{values: array<string, string|int>, rejected: string[]}
     */
    public static function sanitize(array $input): array
    {
        $fields   = Schema::fields();
        $values   = [];
        $rejected = [];

        foreach ($input as $key => $value) {
            $key = (string) $key;

            if (!isset($fields[$key])) {
                continue;
            }

            $clean = self::cleanValue($fields[$key], $value);

            if ($clean === null) {
                $rejected[] = $key;
                continue;
            }

            $values[$key] = $clean;
        }

        return ['values' => $values, 'rejected' => $rejected];
    }

    /**
     * @param array{0: string, 1?: mixed, 2?: mixed} $spec
     * @param mixed                                   $value
     * @return string|int|null null = invalid
     */
    private static function cleanValue(array $spec, $value)
    {
        if (!\is_scalar($value) && $value !== null) {
            return null;
        }

        $text = trim((string) $value);

        switch ($spec[0]) {
            case 'color':
                return $text === '' ? '' : (ColorHelper::clean($text) ?: null);

            case 'media':
                return self::cleanPath($text);

            case 'range':
                if ($text === '') {
                    return '';
                }

                if (preg_match('/^\d{1,3}$/', $text) !== 1) {
                    return null;
                }

                $number = (int) $text;

                return $number >= $spec[1] && $number <= $spec[2] ? $number : null;

            case 'flag':
                return $text === '0' || $text === '1' ? (int) $text : null;

            case 'list':
                return \in_array($text, $spec[1], true) ? $text : null;

            case 'footer':
                return self::cleanFooter($text);

            default:
                return null;
        }
    }

    /**
     * A path or URL: no markup, quotes, backslashes, control characters or script schemes.
     */
    private static function cleanPath(string $text): ?string
    {
        if ($text === '') {
            return '';
        }

        if (\strlen($text) > 500 || preg_match('/[<>"\'\\\\\x00-\x1f]/', $text) === 1
            || preg_match('#^\s*(javascript|data|vbscript):#i', $text) === 1) {
            return null;
        }

        return $text;
    }

    /**
     * Footer HTML is printed raw on the login page. It is parsed and rebuilt
     * from an allowlist of elements, attributes and URL schemes (see
     * FooterSanitizer), so an import cannot carry script, handlers, scripting
     * URLs, frames or markup that closes the elements around it.
     */
    private static function cleanFooter(string $text): ?string
    {
        return FooterSanitizer::sanitize($text);
    }
}
