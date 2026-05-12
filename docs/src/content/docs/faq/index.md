---
title: Frequently asked questions
description: Common SourceBans++ questions, grouped by topic.
sidebar:
  order: 1
---

The questions we answer most often in `#help-support`, grouped by
topic. If yours isn't here, drop into our
[Discord](https://discord.gg/tzqYqmAtF5) and ask.

## General

### What is SourceBans++?

A free, open-source admin and ban-management system for Source-engine
game servers. It has a web panel your admins log into and a set of
SourceMod plugins running on each of your game servers. They share a
database and coordinate through it.

The [Overview](/getting-started/overview/) has the longer answer.

### Is it free?

Yes. SourceBans++ is open source under CC BY-NC-SA 3.0 for the web
panel and GPLv3 for the plugins. We don't sell hosting, support, or
plugins.

### Where can I get help?

- **First:** the docs you're reading. The
  [Quickstart](/getting-started/quickstart/) and
  [Troubleshooting](/troubleshooting/panel-not-loading/) sections
  cover the things we answer most often.
- **Then:** our [Discord](https://discord.gg/tzqYqmAtF5)
  `#help-support` channel.
- **Bug reports:** open a
  [GitHub issue](https://github.com/sbpp/sourcebans-pp/issues) with
  reproduction steps.

### I want to contribute, where do I start?

Take a stab at the issues labelled `good first issue` or `help wanted`
on the [issue tracker](https://github.com/sbpp/sourcebans-pp/issues).
The
[`AGENTS.md`](https://github.com/sbpp/sourcebans-pp/blob/main/AGENTS.md)
file in the repo root is the contributor cheatsheet — conventions,
the local Docker dev stack, and the "where to find what" index.

Translation PRs are always welcome too — see
[Translating](/customization/translating/).

## Installing

### Can I use SourceBans++ without the web panel?

Not really. You need the web panel at minimum to install, configure,
and add servers. In theory you could stop using the panel after that
and the in-game half would keep enforcing bans — but you'd lose
access to most of what makes SourceBans++ useful: adding admins,
managing bans, processing appeals, viewing the audit log.

We recommend running the entire package, panel included.

### Does it work with [game]?

Anything built on Source / Source 2 with a SourceMod release: TF2,
Counter-Strike (Source / GO / 2), Garry's Mod, DoD:S, L4D / L4D2,
Insurgency, NMRiH, Synergy, and friends.

If SourceMod runs on it, SourceBans++ usually runs on it.

### Can I run the panel and game servers on different hosts?

Yes. The panel and plugin halves only need to share a database; they
can be on different hosts, different networks, even different
continents. The DB user's grant has to allow connections from both
hosts, which is the most common stumbling block — see
[Database setup → Granting permission](/setup/mariadb/#granting-permission).

### Will it work on shared hosting?

Yes, with two caveats:

- The host must run **PHP >= 8.5** with `pdo_mysql`, `gmp`,
  `openssl`, `xml`, and `mbstring`. Most modern shared hosts do.
- The host must allow **remote database connections** if your
  game servers aren't on the same host. Many shared hosts
  disable this by default; check the control panel for a
  "Remote MySQL" or "Allowed hosts" setting.

## The web panel

### Why am I seeing a blank white page?

PHP hit a fatal error before any output was sent. The actual error
goes to your webserver's PHP error log — see
[Panel won't load](/troubleshooting/panel-not-loading/#1-server-level-errors-blank-white-page)
for the typical log paths and how to read them.

### Why isn't the panel emailing me as the owner?

The owner account isn't assigned to any permission group by default,
so email-on-event triggers don't fire for it.

Edit the owner account under **Admin Panel → Admins** and assign
yourself to an admin group with the relevant notification flags
(typically the group that gets emails for new ban submissions,
protests, etc.).

### I locked myself out by enabling Steam-only login

If you flipped the Steam-only login switch and you no longer have
Steam access, the panel won't accept your password.

The fix is a one-row database update. Sign into your DB with any
client (PHPMyAdmin, Adminer, the `mysql` CLI):

1. Open the `_settings` table.
2. Find the row where `setting = 'config.enablesteamlogin'`.
3. Set its `value` back to `1`.

That re-enables password login alongside Steam OpenID. Sign in,
then fix the configuration the way you intended.

:::caution
Editing `_settings` directly is a foot-gun — bad values can break
the panel's bootstrap. Touch only the row you intend to fix, and
back up the table first if you're not sure.
:::

### How do I customize the dashboard?

See [Customizing the dashboard](/customization/removing-default-message/).
Short version: **Admin Panel → Settings → Settings → Dashboard**,
edit the intro text (Markdown supported), save.

## The plugin

### Why is "Ban player" missing from SourceMod's admin menu?

SourceMod's built-in `basebans.smx` was still loaded when you
installed SourceBans++. The plugin automatically disables
`basebans.smx`, but the menu doesn't refresh until the **server is
restarted**.

After the restart, check that `basebans.smx` moved to
`plugins/disabled/`. Some hosters don't allow plugins to move
files; if `basebans.smx` is still in `plugins/`, move it manually.

### Why does the panel say "Database failure: Could not find database conf 'sourcebans'"?

The `"sourcebans"` block is missing from SourceMod's
`databases.cfg`. The [Quickstart](/getting-started/quickstart/#databasescfg)
shows the canonical block — add it, save, reload the map, and the
plugin will pick it up.

### Why does the panel only show "Max Players" instead of the actual player list?

The game server is hiding the player list from external A2S
queries. Add this to `server.cfg` (or any startup config):

```
host_players_show 2
```

Reload the map and the panel will see the real player list on its
next poll.

### Can I forward bans to Discord?

Yes, via the separate
[`sbpp/discord-forward`](https://github.com/sbpp/discord-forward)
plugin. Setup is in
[Discord notifications](/integrations/discord-forward-setup/).

Note: the forwarder currently only fires for actions taken
**in-game**. Bans applied from the web panel won't be forwarded
(this is a [tracked feature
request](https://github.com/sbpp/discord-forward/issues)).

## Updating

### How do I upgrade SourceBans++?

The full upgrade path is in [Updating](/updating/). Short version:
back up your database, upload the new files, visit `/updater/`,
delete the `install/` and `updater/` directories when done.

### Is upgrading to 2.0.x different?

Yes — read [Upgrading from 1.8.x to 2.0.x](/updating/1-8-to-2-0/)
first. v2.0 raises the PHP version floor, resets the active theme,
and ships default-on anonymous telemetry. The upgrade itself is
otherwise normal.

### Will my custom theme survive an upgrade?

For minor / patch upgrades, yes. For **major** version jumps
(like 1.8.x → 2.0.x), custom themes are usually broken because the
template signatures change. The v2.0 updater specifically resets
the active theme to `default` to keep the panel loadable; you can
re-select your fork after porting it.

See [Upgrading from 1.8.x → 2.0.x](/updating/1-8-to-2-0/#theme-reset)
for the v2.0 details.
