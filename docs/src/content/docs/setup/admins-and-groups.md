---
title: Admins & groups
description: Create admin accounts, group them, and hand out permissions in SourceBans++.
sidebar:
  order: 3
---

Beyond the first admin account you created during install, SourceBans++
expects you to add the rest of your moderation team and assign each
person the right permissions. This page walks through the moving
parts.

## How permissions work

SourceBans++ uses a two-layer model:

- **Groups** are named bundles of permission flags (e.g. "Junior
  admin", "Moderator", "Senior admin", "Owner").
- **Admins** are individuals — each has a SteamID, email, optional
  password, and is assigned to one or more groups.

In practice most installs only ever edit groups: you set up three or
four group templates that match your community's hierarchy, then
each new admin just gets added to the right group. Per-admin
permission overrides exist for edge cases.

There are also **server groups**, separate from web groups — these
control which game servers an admin can use in-game. An admin who
moderates Server A but not Server B gets added to a server group
scoped to Server A.

## Add a new admin

1. Sign in as an account with the **Add admin** permission (owners
   have this by default).

2. Navigate to **Admin Panel → Admins → Add admin**.

3. Fill in:

   - **Username** — what the panel displays.
   - **SteamID** — the admin's SteamID in `STEAM_0:0:…` form.
     [SteamID I/O](https://steamid.io/) converts other formats.
   - **Email** — the admin's email. Required if they'll use
     password login; optional if they'll use Steam OpenID only.
   - **Password** — set one or leave empty to force Steam-only
     login.
   - **Web group** — which group's flags they get on the panel.
   - **Server group(s)** — which game servers they can moderate
     in-game.

4. Click **Add admin**.

The new admin can now sign in. If you set a password, share it with
them out-of-band (DM, password manager, etc.) and ask them to change
it on first login.

## Create or edit groups

Groups live under **Admin Panel → Groups**.

### Web groups

Web groups control panel access — who can do what *inside the web
UI*. Common flags:

- **Owner** — full access to everything.
- **Add ban** — can apply bans from the panel.
- **Edit ban / Unban** — can lift or amend bans.
- **Add admin** — can create new admin accounts.
- **Web settings** — can edit panel settings.
- **Settings → Servers / Mods / Groups** — granular admin-area
  access.

For a small community two or three groups is usually enough:

- **Senior admin / Owner** — everything.
- **Admin** — ban / unban / edit comms, add new admins, but no
  settings access.
- **Junior admin** — ban / unban only.

### Server groups

Server groups control in-game permissions — who can use what
SourceMod commands on which servers. These flags follow SourceMod's
standard letter codes (`a`-`z`):

| Letter | Meaning |
| --- | --- |
| `a` | reserved slot |
| `b` | generic admin (no commands by default) |
| `c` | kick |
| `d` | ban |
| `e` | unban |
| `f` | slay |
| `g` | change map |
| `h` | change convars |
| `i` | exec configs |
| `j` | admin chat |
| `k` | slay non-admins |
| `l` | adjust voting |
| `m` | password servers |
| `n` | adjust RCON |
| `o` | cheats |
| `p` | custom 1 (overrides) |
| `q` | custom 2 |
| `r` | custom 3 |
| `s` | custom 4 |
| `t` | custom 5 |
| `u` | custom 6 |
| `z` | root (all flags) |

Bundle these into server groups that match what each tier of admin
should be able to do in-game.

## Assigning admins to game servers

A panel admin has to be in at least one **server group** before they
appear as an admin on any game server. Server groups also let you
say "this admin moderates the public servers but not the
competitive ones".

To assign:

1. Edit the admin's profile under **Admin Panel → Admins**.
2. In **Server groups**, tick the groups they should belong to.
3. Save.

The SourceMod plugin re-reads admin assignments on every map
change. After saving, ask the admin to wait for a map change or
manually reload admins in-game with `sm_reloadadmins`.

## SteamID-only vs password login

Each admin can sign in two ways:

- **SteamID + password** — they enter their SteamID and the
  password you set, just like a normal login form.
- **Steam OpenID** — they click "Sign in with Steam" and Steam
  confirms their identity.

The Steam OpenID flow has no password to forget or leak, so it's
the recommended default for most communities. You can disable
password login site-wide under **Admin Panel → Settings → Features
→ Steam-only login**, but **think twice** — if you lose Steam access
later you'll need a database query to get back in (covered in the
[FAQ](/faq/#i-locked-myself-out-by-enabling-steam-only-login)).

## What admins see

Each admin only sees the parts of the panel their permissions allow.
Junior admins might only see Bans + Communications; senior admins
see everything including Settings and Audit Log.

The **Audit Log** under **Admin Panel → Settings → Audit log**
records every admin action (bans, unbans, settings changes) with
timestamp and admin name. It's the first place to look if something
unexpected changed.

## Removing an admin

Under **Admin Panel → Admins**, find the row and click the trash icon.
You'll be asked for a reason — it goes into the audit log alongside
the removal.

Removed admins lose panel access immediately. In-game admin status
clears on the next map change.

## Common pitfalls

- **Owner account has no email notifications.** The owner account
  isn't assigned to any group by default, so email-on-event triggers
  don't fire for it. Add yourself to an admin group with the right
  notification flags.

- **Admin can sign in but can't ban from in-game.** They're in a web
  group but no server group. Add them to a server group that
  includes the relevant servers and flag `d`.

- **Admin can ban from in-game but not the panel.** Mirror image of
  the above — they're in a server group but no web group with the
  Add ban flag.
