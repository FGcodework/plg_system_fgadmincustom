// Re-parses sanitized footers with parse5 (browser-grade HTML5 parsing) and checks the invariants.
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire((process.env.PARSE5_DIR ?? '/tmp/p5') + '/');
const parse5 = require('parse5');

const ELEMENTS = {
  a: ['href', 'target', 'rel'], abbr: [], b: [], br: [], div: [], em: [], hr: [], i: [], img: ['src', 'alt', 'width', 'height'],
  li: [], ol: [], p: [], small: [], span: [], strong: [], sub: [], sup: [], u: [], ul: [],
};
const GLOBAL = ['class', 'title'];
const LINK = ['http', 'https', 'mailto', 'tel'];
const IMG = ['http', 'https'];

function urlOk(value, schemes) {
  // what a browser does to a URL before reading the scheme: strip leading/trailing C0+space, drop tab/newline
  const v = value.replace(/^[\u0000- ]+|[\u0000- ]+$/g, '').replace(/[\t\n\r]/g, '');
  const m = /^([a-zA-Z][a-zA-Z0-9+.\-]*):/.exec(v);
  return !m || schemes.includes(m[1].toLowerCase());
}

let bad = 0;
const check = (node, ctx) => {
  for (const c of node.childNodes ?? []) {
    if (c.nodeName === '#text') continue;
    if (c.nodeName === '#comment') { console.log('COMMENT in', ctx); bad++; continue; }
    const allowed = ELEMENTS[c.nodeName];
    if (!allowed) { console.log('ELEMENT', c.nodeName, 'in', ctx); bad++; continue; }
    for (const a of c.attrs) {
      if (![...GLOBAL, ...allowed].includes(a.name)) { console.log('ATTR', a.name, 'in', ctx); bad++; }
      if (a.name === 'href' && !urlOk(a.value, LINK)) { console.log('HREF', a.value, 'in', ctx); bad++; }
      if (a.name === 'src' && !urlOk(a.value, IMG)) { console.log('SRC', a.value, 'in', ctx); bad++; }
      if (/^on/i.test(a.name)) { console.log('HANDLER', a.name); bad++; }
    }
    if (c.nodeName === 'a' && c.attrs.some((x) => x.name === 'target' && x.value === '_blank')
        && !(c.attrs.find((x) => x.name === 'rel')?.value ?? '').split(' ').includes('noopener')) { console.log('NOOPENER missing in', ctx); bad++; }
    check(c.content ?? c, ctx);
  }
};

const items = JSON.parse(readFileSync(process.argv[2], 'utf8'));
let n = 0;
for (const { in: input, out } of items) {
  n++;
  // parsed as the fragment inside <div class="alc-footer"> - exactly where the page puts it
  const root = parse5.parse('<!doctype html><body><div class="alc-footer">' + out + '</div><form id="after"></form>');
  const body = root.childNodes[1].childNodes.find((x) => x.nodeName === 'body');
  const real = body.childNodes.filter((x) => x.nodeName !== '#text');
  if (real.length !== 2 || real[0].nodeName !== 'div' || real[1].nodeName !== 'form') { console.log('BREAKOUT', JSON.stringify(input), '->', JSON.stringify(out)); bad++; continue; }
  check(real[0], JSON.stringify(out));
  // stable through serialize + parse (no mutation-XSS style drift)
  const frag = parse5.parseFragment(out);
  const again = parse5.serialize(parse5.parseFragment(parse5.serialize(frag)));
  if (parse5.serialize(frag) !== again) { console.log('UNSTABLE', JSON.stringify(out)); bad++; }
}
console.log(bad === 0 ? `parse5 check OK: ${n} accepted footers, all inside the allowlist` : `${bad} PROBLEMS`);
process.exit(bad === 0 ? 0 : 1);
