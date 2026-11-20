<?php

/**
 * @package     plg_system_fgadmincustom
 * @version     1.0.7
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminCustom\Helper;

\defined('_JEXEC') or die;

/**
 * Allowlist sanitizer for the footer HTML of the login page (printed raw into
 * the page, and the one field that can arrive through a pasted settings JSON).
 *
 * The HTML is parsed and then REBUILT from the parsed tree: only the elements
 * and attributes below survive, every attribute value is checked after the
 * parser has decoded its entities, and everything is written out again with
 * proper escaping. What is stored is therefore what this class produced, never
 * what the input looked like, and the result is always well balanced - it
 * cannot close the elements around it. Anything outside the allowlist makes
 * the whole value invalid (the import then skips it and says so).
 */
final class FooterSanitizer
{
    private const MAX_LENGTH = 5000;
    private const MAX_DEPTH  = 12;

    /** Attributes allowed on every element. */
    private const GLOBAL_ATTRS = ['class', 'title'];

    /** Allowed elements and their own attributes. */
    private const ELEMENTS = [
        'a'      => ['href', 'target', 'rel'],
        'abbr'   => [],
        'b'      => [],
        'br'     => [],
        'div'    => [],
        'em'     => [],
        'hr'     => [],
        'i'      => [],
        'img'    => ['src', 'alt', 'width', 'height'],
        'li'     => [],
        'ol'     => [],
        'p'      => [],
        'small'  => [],
        'span'   => [],
        'strong' => [],
        'sub'    => [],
        'sup'    => [],
        'u'      => [],
        'ul'     => [],
    ];

    private const VOID = ['br', 'hr', 'img'];

    private const LINK_SCHEMES  = ['http', 'https', 'mailto', 'tel'];
    private const IMAGE_SCHEMES = ['http', 'https'];

    /**
     * @return string|null the rebuilt, safe HTML ('' for an empty footer); null when the value is not acceptable
     */
    public static function sanitize(string $html): ?string
    {
        if ($html === '') {
            return '';
        }

        if (\strlen($html) > self::MAX_LENGTH || !mb_check_encoding($html, 'UTF-8') || preg_match('/\x00/', $html) === 1) {
            return null;
        }

        if (!class_exists(\DOMDocument::class)) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $ok  = $doc->loadHTML(
                '<?xml encoding="UTF-8"><html><body><div id="fgac-root">' . $html . '</div></body></html>',
                LIBXML_NONET
            );

            if ($ok !== true) {
                return null;
            }

            $root = $doc->getElementById('fgac-root');
            $body = $doc->getElementsByTagName('body')->item(0);

            // The wrapper must be the only thing in the body: a stray closing tag in the
            // input could otherwise end it early and leave content where it is not checked.
            if (!$root instanceof \DOMElement || !$body instanceof \DOMElement || $root->parentNode !== $body) {
                return null;
            }

            foreach ($body->childNodes as $child) {
                if ($child !== $root && !($child instanceof \DOMText && trim($child->nodeValue ?? '') === '')) {
                    return null;
                }
            }

            $out = self::children($root, 0);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($out === null || \strlen($out) > self::MAX_LENGTH) {
            return null;
        }

        return $out;
    }

    private static function children(\DOMNode $node, int $depth): ?string
    {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }

        $out = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $out .= htmlspecialchars((string) $child->nodeValue, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue; // comments, processing instructions: dropped
            }

            $element = self::element($child, $depth);

            if ($element === null) {
                return null;
            }

            $out .= $element;
        }

        return $out;
    }

    private static function element(\DOMElement $el, int $depth): ?string
    {
        $name = strtolower($el->nodeName);

        if (!isset(self::ELEMENTS[$name])) {
            return null;
        }

        $allowed = array_merge(self::GLOBAL_ATTRS, self::ELEMENTS[$name]);
        $attrs   = [];

        foreach ($el->attributes ?? [] as $attr) {
            $key = strtolower($attr->nodeName);

            if (!\in_array($key, $allowed, true)) {
                return null;
            }

            $value = self::attribute($name, $key, (string) $attr->nodeValue);

            if ($value === null) {
                return null;
            }

            $attrs[$key] = $value;
        }

        if ($name === 'a' && ($attrs['target'] ?? '') === '_blank') {
            $rel         = array_filter(explode(' ', $attrs['rel'] ?? ''));
            $attrs['rel'] = implode(' ', array_unique(array_merge($rel, ['noopener'])));
        }

        if ($name === 'img' && !isset($attrs['src'])) {
            return null;
        }

        // A fixed order keeps the output stable: sanitizing twice changes nothing.
        $html = '<' . $name;

        foreach (['href', 'src', 'target', 'rel', 'alt', 'width', 'height', 'title', 'class'] as $key) {
            if (isset($attrs[$key])) {
                $html .= ' ' . $key . '="' . htmlspecialchars($attrs[$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
        }

        $html .= '>';

        if (\in_array($name, self::VOID, true)) {
            return $el->hasChildNodes() ? null : $html;
        }

        $inner = self::children($el, $depth + 1);

        return $inner === null ? null : $html . $inner . '</' . $name . '>';
    }

    /**
     * The value of one attribute, already decoded by the parser (so "java&#x73;cript:"
     * arrives as "javascript:"); null when it is not acceptable.
     */
    private static function attribute(string $element, string $key, string $value): ?string
    {
        switch ($key) {
            case 'href':
                return self::url($value, self::LINK_SCHEMES);

            case 'src':
                return self::url($value, self::IMAGE_SCHEMES);

            case 'target':
                return \in_array($value, ['_blank', '_self'], true) ? $value : null;

            case 'rel':
                return preg_match('/^[a-z]+( [a-z]+){0,5}$/', trim($value)) === 1 ? trim($value) : null;

            case 'class':
                return preg_match('/^[A-Za-z0-9_\- ]{1,200}$/', $value) === 1 ? trim($value) : null;

            case 'width':
            case 'height':
                return preg_match('/^\d{1,4}$/', $value) === 1 ? $value : null;

            case 'alt':
            case 'title':
                return \strlen($value) <= 300 && preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value) !== 1 ? $value : null;
        }

        return null;
    }

    /**
     * Absolute URLs only with an allowed scheme; relative URLs (path, query,
     * fragment, protocol-relative) are fine. Control characters, spaces and
     * backslashes - the tricks browsers forgive inside a scheme - are refused.
     *
     * @param string[] $schemes
     */
    private static function url(string $value, array $schemes): ?string
    {
        $value = trim($value, ' ');

        if ($value === '' || \strlen($value) > 500 || preg_match('/[\x00-\x20\x7f-\x9f\\\\]/u', $value) === 1) {
            return null;
        }

        // The parser decodes the entities it knows; one it does not know ("&colon;") would
        // survive and could still be decoded by a browser, so leftovers are refused.
        if (preg_match('/&(#|[a-zA-Z][a-zA-Z0-9]*;)/', $value) === 1) {
            return null;
        }

        // A colon before the first / ? # is a scheme separator.
        if (preg_match('/^([^\/?#]*):/', $value, $m) === 1) {
            $scheme = strtolower($m[1]);

            if (preg_match('/^[a-z][a-z0-9+.\-]*$/', $scheme) !== 1 || !\in_array($scheme, $schemes, true)) {
                return null;
            }
        }

        return $value;
    }
}
