<?php

/**
 * @package     plg_system_fgadmincustom
 * @version     1.0.8
 * @license     GNU General Public License version 2 or later
 *
 * Finds text/background pairs of the login page that were typed in by hand and
 * do not reach the WCAG AA contrast of 4.5:1. Colors left empty are chosen by the
 * plugin itself (the better of black and white) and are not checked here.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminCustom\Helper;

\defined('_JEXEC') or die;

final class ContrastCheck
{
    public const MINIMUM = 4.5;

    /** Text setting => background setting, and the language key of the text setting's label. */
    public const PAIRS = [
        'login_card_text'   => ['login_card_bg', 'PLG_SYSTEM_FGADMINCUSTOM_LOGIN_CARD_TEXT_LABEL'],
        'login_btn_text'    => ['login_btn_bg', 'PLG_SYSTEM_FGADMINCUSTOM_LOGIN_BTN_TEXT_LABEL'],
        'login_header_text' => ['login_header_bg', 'PLG_SYSTEM_FGADMINCUSTOM_LOGIN_HEADER_TEXT_LABEL'],
    ];

    /**
     * @param array<string, mixed> $params settings as stored / shown in the form
     * @return array<int, array{label: string, ratio: float}> label = language key; only pairs below the minimum
     */
    public static function low(array $params): array
    {
        $low = [];

        foreach (self::PAIRS as $textKey => [$backgroundKey, $label]) {
            $text       = ColorHelper::clean(self::scalar($params[$textKey] ?? ''));
            $background = ColorHelper::clean(self::scalar($params[$backgroundKey] ?? ''));

            if ($text === '' || $background === '') {
                continue;
            }

            // A translucent background is judged as it looks over white, as the automatic text color does.
            $ratio = ColorHelper::contrast(ColorHelper::flatten($text, ColorHelper::flatten($background)), ColorHelper::flatten($background));

            if ($ratio < self::MINIMUM) {
                $low[] = ['label' => $label, 'ratio' => floor($ratio * 10) / 10];
            }
        }

        return $low;
    }

    /**
     * @param mixed $value
     */
    private static function scalar($value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
