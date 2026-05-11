---
title: Common inquiries
description: Operator-flavoured questions and the fixes we've collected over time.
sidebar:
  order: 2
---

A grab-bag of questions we've answered repeatedly. Each one has the
half it applies to (web panel or plugin) called out, and points at the
relevant troubleshooting / setup page where applicable.

## Plugin: "Ban player" is missing in SourceMod's admin menu

Most likely SourceMod's `basebans.smx` was still loaded when you
installed SourceBans++'s game plugin. The game plugin automatically
disables `basebans.smx`, but your server needs to be restarted before
the **Ban player** option re-appears.

Some hosters disallow plugins from moving files. Make sure
`basebans.smx` ended up in `plugins/disabled/`.

## Web: Player list shows "Max Players" instead of all the players

Add the following to your `server.cfg` (or any startup config file):

```
host_players_show 2
```

This tells the engine to expose the full player list to the panel's
A2S query.

## Web: Why isn't SourceBans++ sending email reports to me (the owner)?

By default, the owner account isn't assigned to any permission group,
which means email-on-event triggers don't fire for it.

Navigate to **Admin Settings**, edit the owner account, and assign
yourself to an admin group with the right notification flags.

## Web: I locked myself out by enabling Steam-only login. How do I fix it?

Use a database query tool (PHPMyAdmin, Adminer, the dev stack's
Adminer at `:8081`, etc.). In the `_settings` table, find
`config.enablesteamlogin` and set its value back to `1`.

That re-enables password login alongside Steam OpenID. Once you can
sign in, fix the configuration the way you intended.

:::caution
Editing `_settings` directly is a foot-gun — bad values can break the
panel's bootstrap. Touch only the row you intend to fix, and back up
the table first if you're not sure.
:::

## Web: Why is the panel showing me a blank white page?

PHP encountered a fatal error and stopped emitting output before any
of the page chrome painted.

To see the actual error, check your webserver's PHP error log. Typical
locations:

- Apache (Debian / Ubuntu): `/var/log/apache2/error.log`
- Apache (Fedora / RHEL): `/var/log/httpd/error_log`
- Nginx + PHP-FPM: `/var/log/php<version>-fpm.log` plus the FPM pool log
- Docker / dev stack: `./sbpp.sh logs web`

The error message will tell you what to fix — usually a missing
extension, a syntax error in `config.php`, or a memory-limit hit.

If you can't find a log file, set `display_errors = On` in `php.ini`
**temporarily**, refresh the page, and read the error in the browser.
Set it back to `Off` immediately afterwards — leaving it on in
production leaks paths and other internals.

## Plugin: "Database failure: Could not find database conf 'sourcebans'"

You forgot to add the `"sourcebans"` section to SourceMod's
`databases.cfg`, as instructed in the
[Quickstart → Plugin setup](/getting-started/quickstart/#plugin-setup).

Add the section, save the file, reload the map, and the plugin will
pick it up.
