<p align="center">
  <img src="assets/logo.webp" alt="plg_system_fgadmincustom logo" width="128" height="128">
</p>

<h1 align="center">FG Admin Customizer plugin for Joomla</h1>

<p align="center">
  <img src="https://img.shields.io/github/v/release/FGcodework/plg_system_fgadmincustom?color=FF6B4A&label=release" alt="Latest release">
  <img src="https://img.shields.io/badge/Joomla-6.x-blue.svg?logo=joomla&logoColor=white" alt="Joomla">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-purple.svg?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/license-GPLv2%2B-green.svg" alt="License">
  <img src="https://img.shields.io/github/downloads/FGcodework/plg_system_fgadmincustom/total?cacheSeconds=3600&color=brown" alt="Downloads">
  <a href="https://ko-fi.com/fgcodework"><img src="https://img.shields.io/badge/support-Ko--fi-F16061.svg?logo=ko-fi&logoColor=white" alt="Support on Ko-fi"></a>
</p>

A Joomla 5/6 system plugin that gives the whole administrator one visual
identity: the `/administrator` **login page** (logo, background, card,
colors, footer, custom CSS/JS) and the **logged-in Atum back-end** (header
and sidebar colors, page background, logo, menu behavior) - both driven by
one shared **brand color** and one set of corner radii and shadows. The Atum
template itself is never modified.

Part of the **FG** series of Joomla extensions. It replaces, and is
independent of, FG Admin Login Customizer and FG Admin Page Customizer.

## Features

- **Brand** (shared): brand color, corner radius of fields/buttons and of cards, shadow presets. The login page inherits them; each value can be overridden there, or the inheritance switched off
- **Login page**: logo (with optional dark-mode logo, size and fit), background (none / color / gradient / image with overlay), card background/text/radius/shadow, button and link colors, header bar, optional footer text (a safe subset of HTML), hiding the sidebar, template branding, language switcher and the "Forgot your login details?" link
- **Automatic contrast**: leave a text color empty and it is computed from the background (WCAG relative luminance)
- **Six built-in login color presets** (Default / Dark / Corporate Blue / Green / Orange / Minimal)
- **Logged-in administrator**: header color, sidebar color (or "follow the header"), page background, expanded and collapsed header logo, optional "open the first menu entry" behavior
- **Separate switches** for the login page and the logged-in administrator
- **Recovery switch** `?fgac=off` (see below) and isolated error handling: a failure in one part is logged and never breaks the other or the login form
- **Export / Import** as JSON; also reads the Export JSON of the two old plugins
- **Custom CSS and JavaScript** for the login page for anything the options do not cover

## Installation

1. Download the latest `plg_system_fgadmincustom_vX.Y.Z.zip` from the
   [Releases](https://github.com/FGcodework/plg_system_fgadmincustom/releases) tab.
2. **System → Install → Extensions**, upload the ZIP.
3. **System → Manage → Plugins** → "System - FG Admin Customizer" → publish and configure.

### Coming from FG Admin Login Customizer / FG Admin Page Customizer

On a fresh installation the installer copies the settings of the old
plugins into this one, switches this plugin on and the old plugins **off**
(they are not uninstalled - remove them yourself once everything works).
What is converted:

| Old plugin | New settings |
| --- | --- |
| Both: brand color, corner radii, shadows | **Brand** tab (the logged-in administrator's values win; the login page keeps its own as an override when it differed) |
| Login plugin | **Login** tabs, same options |
| Page plugin | **Administrator** and **Behavior** tabs |
| Login: "Hide generator meta tag" | dropped - use [FG Remove Generator](https://github.com/FGcodework/plg_system_fgremovegenerator) |

Only an installation migrates; an update never touches saved settings. If
the migration cannot run, the installation still succeeds, nothing is
disabled and a message tells you to paste the old plugin's Export JSON into
the **Import** field instead. Please do not run an old plugin next to this
one. If you do, nothing breaks (both add their styles, which look the same after the migration), but changes made here may be overridden by the old plugin's CSS, and `?fgac=off` does not switch the old plugins off (they have `?fgalc=off` and `?fgapc=off`). The administrator shows a warning once per session, and the settings page every time, while one of them is still enabled.

## Plugin settings

| Tab | What it controls |
| --- | --- |
| **Plugin** | Extension info and the "Support this plugin" note - no settings. |
| **Brand** | Brand color, field/button radius, card radius, button and card shadow - shared by both parts. |
| **Administrator** | Switch, header color, sidebar color / follow header, page background, logo (expanded, collapsed) and its height. |
| **Behavior** | "Open the first menu entry" behavior of the logged-in administrator. |
| **Login: Logo & Layout** | Switch for the login page, logo (+ dark mode), width/height/fit, template logo/sidebar/chrome visibility, footer text, "Forgot your login details?" link. |
| **Login: Theme Preset** | One of six color presets in one go (colors/background only). Pick → Save → review → Save again. |
| **Login: Background** | Type, colors, gradient angle, image overlay. |
| **Login: Card & colors** | "Use Brand settings", the login page's own overrides of the Brand values, card and button colors, link color. |
| **Login: Header bar** | Header colors, language switcher, frontend-link button. |
| **Login: Advanced** | Custom CSS and JavaScript (see the note below). |
| **Export / Import** | Read-only JSON export; paste JSON + Save to import. Custom JavaScript and Custom CSS are never imported. |

Every color field left empty falls back to the Atum template's own styling.

**Translucent colors.** Colors may carry an alpha byte (`#rrggbbaa`). The automatic
text color is chosen as if the color lay over white, because the plugin cannot know
what is behind it; with a translucent background over something dark, set the text
color yourself.

**"Forgot your login details?"** The link below the login form only points to a page
of the Joomla documentation. It is shown by default; switch *Hide ...* on (tab
*Login: Logo & Layout*) to hide it. Sites migrated from FG Admin Login Customizer keep
the old behaviour (hidden) unless they had saved the option as off.

## Footer text

The login footer accepts simple HTML: links (`http`, `https`, `mailto`, `tel` or relative), `strong`, `em`, `b`, `i`, `u`, `small`, `span`, `div`, `p`, `br`, `hr`, lists, `sub`/`sup`, `abbr` and `img`, with the attributes `class`, `title` and the link/image attributes. When settings are imported, anything else (scripts, inline `style`, event handlers, frames, forms ...) makes the footer invalid and it is skipped with a warning; style it with a `class` and the Custom CSS field instead. Links opened in a new tab automatically get `rel="noopener"`.

## Recovery switch

If a custom rule ever breaks a page, add `?fgac=off`:

- **Login page**: `/administrator?fgac=off` skips every customization for that request.
- **Logged in**: any back-end URL with `?fgac=off` switches the customization off for the whole session; `?fgac=on` switches it back.

Nothing is saved or changed by the switch.

## A note on Custom CSS and JavaScript

The **Custom JavaScript** field runs on the administrator login page - the
page that carries the username and password inputs. Treat it like editing a
template file: only paste code you wrote or have read and understood.
**Custom CSS** is just as powerful in its own way: it can turn the login page
into a look-alike of it, and no filter can tell a harmless rule from a hostile
one. **Import deliberately never carries either of them over**, so pasting a
settings JSON from somewhere else cannot execute foreign code or restyle your
login screen. The Export JSON still contains both, as a backup; copy them across
by hand if you want them. (The installer's one-time migration from the old
plugins does copy them - same site, same administrator.)

## Requirements

- Joomla 5 or 6
- PHP 8.1+

The login-page and header rules, and the logo replacement, are written against the
HTML of the Atum template (`.view-login`, `body.com_login`, the logo slot and the
header markup). A larger Atum change in a future Joomla release may need an update of
this plugin; after each new Joomla minor version, open the login page and a logged-in
page once and compare (the `?fgac=off` switch shows the page without the plugin).

## Support this project

This plugin is free, open source, and always will be - no feature is locked behind a paywall. If it has saved you time, a one-off tip on Ko-fi is welcome, but entirely optional.

<a href="https://ko-fi.com/fgcodework"><img src="https://storage.ko-fi.com/cdn/kofi3.png?v=4" alt="ko-fi" width="180"></a>

## License

GNU General Public License v2.0 or later - see [LICENSE](LICENSE).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Development

Everything except the Joomla glue runs without Joomla, in plain PHP:

```
php tests/run.php
```

`php tests/golden.php <old-login-dir> <old-page-dir>` compares the output
against the two old plugins for random configurations, and
`php tests/coexist.php <old-login-dir> <old-page-dir>` runs old and new
plugin together (development only).
