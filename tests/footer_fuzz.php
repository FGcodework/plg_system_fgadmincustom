<?php

/**
 * Development-only fuzz test of the footer sanitizer: random hostile HTML goes
 * through FooterSanitizer, and every ACCEPTED result is re-parsed by parse5 (the
 * HTML5 parser algorithm browsers use) and checked: only allowlisted elements,
 * attributes and URL schemes, the tree is stable on a second parse, and the
 * footer cannot close the elements around it.
 *   php tests/footer_fuzz.php [iterations] [seed]      (needs node; PARSE5_DIR = a directory where `npm i parse5@7` was run (default /tmp/p5))
 */

declare(strict_types=1);

define('_JEXEC', 1);

require __DIR__ . '/../src/Helper/FooterSanitizer.php';

use FG\Plugin\System\AdminCustom\Helper\FooterSanitizer;

$n    = (int) ($argv[1] ?? 20000);
$seed = (int) ($argv[2] ?? 1);
mt_srand($seed);

$tags   = ['a', 'b', 'i', 'p', 'div', 'span', 'img', 'br', 'script', 'svg', 'math', 'style', 'iframe', 'form', 'input', 'button', 'details', 'noscript', 'textarea', 'title', 'template', 'table', 'select', 'object', 'embed', 'base', 'link', 'meta', 'video', 'audio', 'marquee', 'xmp', 'plaintext', 'li', 'ul', 'strong', 'small', 'A', 'SCRIPT', 'sVg'];
$attrs  = ['href', 'src', 'class', 'title', 'target', 'rel', 'alt', 'style', 'onclick', 'onerror', 'onload', 'ontoggle', 'id', 'name', 'formaction', 'srcset', 'data-x', 'xlink:href', 'action', 'HREF', 'width', 'height'];
$values = [
    'https://example.com/', '/relative', '#frag', 'mailto:a@b.sk', 'tel:+421900', 'javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'java&#x73;cript:alert(1)',
    'java&#115;cript:alert(1)', 'java&Tab;script:alert(1)', "java\tscript:alert(1)", "java\nscript:alert(1)", 'javascript&colon;alert(1)', '&#x6A;avascript:alert(1)',
    ' javascript:alert(1)', "\x01javascript:alert(1)", 'data:text/html,<script>alert(1)</script>', 'vbscript:msgbox(1)', '//evil.example/x', '\\\\evil.example', 'http://a.b/?x=1&y=2',
    'x', '_blank', 'noopener', 'a b c', '"><script>alert(1)</script>', "' onmouseover='alert(1)", 'blob:https://x', 'file:///etc/passwd', 'jav&#x09;ascript:x', 'java&NewLine;script:x',
];
$texts  = ['Made by', '&copy; 2026', '&lt;b&gt;', '&amp;', 'x < y', 'a > b', '</div>', '</form>', '<!-- c -->', '<!--[if IE]><script>x</script><![endif]-->', '<?php echo 1 ?>', '<![CDATA[x]]>', "\0", '</a></p>', '<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>', '<svg><style><img src=x onerror=alert(1)>', "\xff"];

$piece = static function () use ($tags, $attrs, $values, $texts): string {
    $r = mt_rand(0, 9);

    if ($r < 4) {
        $t = $tags[array_rand($tags)];
        $a = '';

        for ($i = mt_rand(0, 3); $i > 0; $i--) {
            $q = ['"', "'", ''][mt_rand(0, 2)];
            $v = $values[array_rand($values)];
            $a .= ' ' . $attrs[array_rand($attrs)] . '=' . $q . ($q === '' ? str_replace([' ', '>', '"', "'"], '', $v) : str_replace($q, '', $v)) . $q;
        }

        return '<' . $t . $a . ([' /', '', '/'][mt_rand(0, 2)]) . '>';
    }

    if ($r < 6) {
        return '</' . $tags[array_rand($tags)] . '>';
    }

    return $texts[array_rand($texts)];
};

$inputs  = [];
$outputs = [];
$accepted = 0;

for ($i = 0; $i < $n; $i++) {
    $html = '';

    if (mt_rand(0, 1) === 1) {
        // Focused mode: a well-formed allowed element carrying a hostile URL, so the URL checks are what decides.
        $v    = $values[array_rand($values)];
        $html = ['Text ', '<b>', '', '<p>'][mt_rand(0, 3)]
            . (mt_rand(0, 3) === 0
                ? '<img src="' . str_replace('"', '', $v) . '" alt="x">'
                : '<a href="' . str_replace('"', '', $v) . '"' . (mt_rand(0, 1) ? ' target="_blank"' : '') . '>link</a>')
            . ['', ' more', '</b>', '</p>'][mt_rand(0, 3)];
    } else {
        for ($k = mt_rand(1, 8); $k > 0; $k--) {
            $html .= $piece();
        }
    }

    $inputs[] = $html;
    $out      = FooterSanitizer::sanitize($html);

    if ($out !== null) {
        $accepted++;
        $outputs[] = ['in' => $html, 'out' => $out];

        if (FooterSanitizer::sanitize($out) !== $out) {
            echo "NOT IDEMPOTENT: " . json_encode($html) . "\n -> " . json_encode($out) . "\n -> " . json_encode(FooterSanitizer::sanitize($out)) . "\n";
            exit(1);
        }
    }
}

$file = sys_get_temp_dir() . '/footer_fuzz_' . $seed . '.json';
file_put_contents($file, json_encode($outputs, JSON_INVALID_UTF8_SUBSTITUTE));
echo "inputs: $n, accepted: $accepted (all idempotent)\n";

$verifier = __DIR__ . '/footer_verify.mjs';
$cmd      = 'PARSE5_DIR=' . escapeshellarg(getenv('PARSE5_DIR') ?: '/tmp/p5') . ' node ' . escapeshellarg($verifier) . ' ' . escapeshellarg($file);
passthru($cmd, $code);
exit($code);
