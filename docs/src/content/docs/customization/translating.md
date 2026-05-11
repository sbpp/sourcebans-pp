---
title: Translating
description: Translating the SourceBans++ web panel and the SourceMod plugin into other languages.
sidebar:
  order: 2
---

The fundamentals of translating the panel + plugin. The panel side
is theme-based (copy + edit the default theme); the plugin side
follows SourceMod's standard translation file layout.

## Web panel

1. Navigate to the `themes/` folder under your SourceBans++ web
   installation.

2. Make a copy of the `default` theme. Rename your copy to a
   distinctive name and `cd` into it.

3. Edit `theme.conf.php` to reflect your new theme:

   ```php
   <?php
   define('theme_name', "SourceBans++ Default Theme");

   define('theme_author', "IceMan, SourceBans++ Dev Team");

   define('theme_version', "1.8.0-dev");

   define('theme_link', "https://github.com/sbpp/sourcebans-pp");

   define('theme_screenshot', "screenshot.jpg");
   ?>
   ```

4. Edit **each** `.tpl` file in your theme.

5. Modify the body content of each file with the translated copy.

:::caution
SourceBans++ 1.7.0+ uses **Smarty 5**, which dropped the `{php}` tag.
Custom themes that previously used `{php}` must switch to the
[`{load_template}`](https://github.com/sbpp/sourcebans-pp/blob/main/web/includes/SmartyCustomFunctions.php)
tag. The panel will refuse to render templates that still use `{php}`.
:::

## Plugin

1. Navigate to SourceMod's `translations/` directory.

2. The files you'll modify are:
   - `sbpp_main.phrases.txt`
   - `sbpp_comms.phrases.txt`
   - `sbpp_sleuth.phrases.txt`
   - `sbpp_report.phrases.txt`
   - `sbpp_checker.phrases.txt`

3. Inside the `Phrases` key block, for each sub-key section, append a
   key-value pair: the two-letter language code is the key and the
   value is the translation. If a `#format` k/v pair is present in the
   sub-key section, follow its formatter spec.

For more on SourceMod translation, see SourceMod's
[Translation](https://wiki.alliedmods.net/Translations_(SourceMod_Scripting))
wiki article.
