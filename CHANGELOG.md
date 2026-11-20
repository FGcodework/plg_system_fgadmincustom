# Changelog - plg_system_fgadmincustom

## 1.0.5 (2026-11-20)
- NEW: the plugin's settings page shows a notice when the administrator template in use is not Atum (or a child of Atum). The plugin only works with Atum and used to stay silent on any other template. New string `NOT_ATUM`.
- README: how Custom CSS/JavaScript are emitted (Joomla's asset manager, so CSP nonces apply), that they are off while empty, that changes appear in Joomla's User Actions Log, and how to scope your own CSS.

## 1.0.4 (2026-11-20)
- FIX: the automatic text color now takes the alpha byte of `#rrggbbaa` colors into account (the color is laid over white first, instead of its alpha being ignored). A faint translucent background no longer gets light text. New `ColorHelper::alpha()` and `flatten()`.
- FIX: media URLs lose only the `#joomlaImage://...` fragment of the Joomla media field; any other fragment (the `#icon` of an SVG sprite, for example) is kept. Values with quotes, backslashes, angle brackets or control characters are refused before they can reach `url("...")` or `src="..."`.
- CHANGE: "Hide 'Forgot your login details?' link" is off by default for new installations (the link only points to a documentation page, but a plugin should not remove Joomla interface elements unless asked). Existing settings are untouched, and the installer's migration keeps the link hidden for sites whose old login plugin never saved the option.
- README: translucent colors, the recovery link, and the dependency on the Atum markup.
- Tests: alpha and flatten, fragment handling, the migration default; the golden test leaves translucent colors out (the old plugins ignored alpha, which is exactly what changed).

## 1.0.3 (2026-11-20)
- FIX: the installer's migration is now one database transaction. Storing the copied settings, switching this plugin on and switching the old FG Admin Login/Page Customizer plugins off either all happen or none does. Before, a database error between the steps could leave this plugin and an old one enabled together (verified in Joomla 5.4 with a database that refused the last step: 1.0.2 left both on, 1.0.3 changes nothing).
- If the database also cannot undo the steps, the warning says so and asks to check the plugin list, instead of claiming that nothing was changed (new string `MIGRATION_ROLLBACK_FAILED`).
- A problem with an installer message after the data is saved can no longer be reported as a failed migration.
- Tests: the installer tests cover commit, rollback after each step, a failing commit and a failing rollback (34 -> 43 installer assertions).

## 1.0.2 (2026-11-20)
- SECURITY: Custom CSS is no longer imported (Custom JavaScript never was). Imported style could not be made safe by any filter - a `position:fixed` rule is enough to cover the login page with a fake one - and the import is meant for passing settings between people and sites. The import skips both and names them in a warning; the Export JSON still contains them as a backup, and the installer's migration from the old plugins still copies them.
- Tests: the import tests cover both keys (and the legacy names `custom_css` / `custom_js` of the old plugins' exports).

## 1.0.1 (2026-11-20)
- SECURITY: the footer HTML of an imported settings file is now checked against an allowlist instead of a blacklist. Before, a link such as `<a href="java&#x73;cript:...">` passed the check (the HTML entity was not decoded before the URL was examined) and, once saved, ran script when clicked on the login page. The footer is now parsed and rebuilt from the allowed elements (`a`, `abbr`, `b`, `br`, `div`, `em`, `hr`, `i`, `img`, `li`, `ol`, `p`, `small`, `span`, `strong`, `sub`, `sup`, `u`, `ul`) and attributes (`class`, `title`; `href`/`target`/`rel` on links; `src`/`alt`/`width`/`height` on images). Links are accepted only with `http`, `https`, `mailto`, `tel` or as a relative address, checked after the entities are decoded; everything is written back escaped and balanced, so a footer can never close the elements around it. Anything else (scripts, inline `style`, event handlers, frames, forms, SVG ...) makes the footer invalid and the import skips it with a warning. Style a footer with a `class` and the Custom CSS field instead of `style`. Footers copied by the installer from the old plugin are not checked (same site, same administrator).
- FIX: the warning about a still enabled replaced plugin (FG Admin Login/Page Customizer) was only visible in this plugin's own settings. It now appears on the first administrator page after such a plugin is switched on, once per plugin and session. The same fix makes the message of the recovery switch (`?fgac=off`) appear for a logged-in administrator; Joomla draws the message area before the page head, so messages have to be queued earlier than where the CSS is added.
- FIX: the login footer is no longer added a second time when another plugin that adds the same footer runs on the same page.
- Tests: footer sanitizer (27 attack variants, 13 legitimate footers, idempotence) and a development-only fuzz test (`php tests/footer_fuzz.php`) that re-parses accepted footers with an HTML5 parser; the plugin tests now follow the request order of Joomla (messages before head). Verified in Joomla 5.4: installation with migration, update, warnings, recovery switch and the import of a hostile footer through the plugin form.

## 1.0.0 (2026-11-20)
First release. FG Admin Customizer is one plugin that replaces **FG Admin Login Customizer** (`plg_system_fgadminlogincustom`) and **FG Admin Page Customizer** (`plg_system_fgadminpagecustom`); it is independent of both and has its own update server.

- NEW: one plugin for the whole administrator - the login page and the logged-in Atum back-end - with a **Brand** tab (brand color, corner radii of fields/buttons and cards, shadow presets) shared by both. The login page inherits the Brand values; each can be overridden there ("Login: Card & colors"), and "Use Brand settings" turns the inheritance off.
- NEW: separate switches for the login page and the logged-in administrator.
- NEW: one recovery switch for both parts. `/administrator?fgac=off` skips every customization for that request on the login page; for a logged-in administrator it switches the customization off for the whole session (`?fgac=on` brings it back). Nothing is saved by the switch.
- NEW: errors in one part cannot take the other down. Every handler is isolated; a failure is logged (category `plg_system_fgadmincustom`) and the page is left as Joomla made it, so the login form always stays reachable.
- NEW: the installer copies the settings of the two old plugins into this one, switches this plugin on and the old ones off (they are disabled, never uninstalled). Only a fresh installation does this; an update never touches saved settings. If anything fails, the installation still succeeds, nothing is disabled and a message explains how to import the settings by hand.
- NEW: Export / Import accepts the Export JSON of both old plugins and converts it. Custom JavaScript is never imported.
- NEW: a warning in the plugin settings while one of the replaced plugins is still enabled.
- CHANGE: the generator meta tag option of the old login plugin is dropped. Use FG Remove Generator (`plg_system_fgremovegenerator`) for that; the installer says so when the old setting was on and that plugin is not enabled.
- CHANGE: all options are grouped in tabs: Brand, Administrator, Behavior, Login (logo & layout, theme preset, background, card & colors, header bar, advanced), Export / Import.
- Verified against the old plugins: thousands of randomly generated old configurations (including invalid values and a login page that had no brand color while the administrator had one) were converted by the settings migration, and the login CSS, the modified login page and the administrator CSS of this plugin are byte-identical to the output of the old plugins (`php tests/golden.php`, development only).
- Test suites in `tests/` (`php tests/run.php`): manifest and language consistency, helpers, plugin class with Joomla stand-ins, installer with a fake database. Development-only: `tests/golden.php` (output identical to the old plugins).
