---
title: Translating
description: Translate the SourceBans++ web panel and SourceMod plugin into another language.
sidebar:
  order: 2
---

SourceBans++ ships with English as the default. Translating into
another language has two halves — the web panel (theme-based) and
the in-game plugin (SourceMod's standard `.phrases.txt` files).

## Web panel

The web panel's UI strings live inside the active theme's templates,
not in a separate translation file. To translate the panel, you make
a copy of the default theme, swap the English copy for your
translations, and select your new theme in the panel's settings.

1. **Copy the default theme.** Navigate to `themes/` under your
   SourceBans++ web install, copy the `default/` folder, and rename
   the copy to something distinctive (e.g. `german`, `default-fr`).

2. **Edit `theme.conf.php`** in your new theme. Update the metadata
   so the panel's theme picker displays your theme correctly:

   ```php
   <?php
   define('theme_name',       "SourceBans++ — Deutsch");
   define('theme_author',     "Your name");
   define('theme_version',    "1.0.0");
   define('theme_link',       "https://your-site.example.com");
   define('theme_screenshot', "screenshot.jpg");
   ?>
   ```

3. **Translate each `.tpl` file.** Open the `.tpl` files in your
   theme directory and replace the English copy with your
   translation. Don't touch the parts wrapped in `{...}` —
   those are template variables Smarty fills in at render time.

4. **Activate your theme.** Sign in as an admin with **Web settings**
   permission, navigate to **Admin Panel → Settings → Themes**,
   and pick your new theme.

:::caution
SourceBans++ uses **Smarty 5** under the hood, which dropped the
`{php}` tag. If you find a `{php}` block in an old fork theme,
you'll need to replace it with
[`{load_template}`](https://github.com/sbpp/sourcebans-pp/blob/main/web/includes/SmartyCustomFunctions.php)
or move the logic into a PHP helper — the panel refuses to render
templates that still contain `{php}`.
:::

## SourceMod plugin

The plugin uses SourceMod's standard translation files (per-plugin
`.phrases.txt` under `addons/sourcemod/translations/`).

1. Open SourceMod's `translations/` directory on your game server.

2. The plugin translation files are:

   - `sbpp_main.phrases.txt`
   - `sbpp_comms.phrases.txt`
   - `sbpp_sleuth.phrases.txt`
   - `sbpp_report.phrases.txt`
   - `sbpp_checker.phrases.txt`

3. Each file has a `Phrases` block with named entries:

   ```
   "MyPhrase"
   {
       "en"     "Hello, {1}"
       "#format"  "{1:s}"
   }
   ```

   Add a line for your language using the
   [two-letter language code](https://wiki.alliedmods.net/Translations_(SourceMod_Scripting)#Language_Codes):

   ```
   "MyPhrase"
   {
       "en"     "Hello, {1}"
       "fr"     "Bonjour, {1}"
       "#format"  "{1:s}"
   }
   ```

   - Keep the `{1}`, `{2}`, … placeholders in the same positions —
     they're how SourceMod injects player names, durations, etc.
   - If `#format` is present, follow its formatter spec (see
     SourceMod's
     [Translations](https://wiki.alliedmods.net/Translations_(SourceMod_Scripting))
     docs).

4. **Reload the map** (or restart the server) so SourceMod reloads
   its translation cache.

Each connecting player gets phrases in their Steam client's
language. If their language has no translation entry, SourceMod
falls back to English.

## Contributing translations back

We always welcome translation PRs against
[`sbpp/sourcebans-pp`](https://github.com/sbpp/sourcebans-pp) — both
panel theme translations and plugin phrase translations.

For the panel, the practical path is to keep the default theme's
structure and only swap the visible text, so the PR is a clean
diff. For the plugin, add your language entries inside the existing
phrase blocks.
