---
title: Customizing the dashboard
description: Replace the default welcome message on the public dashboard with your community's intro text.
sidebar:
  order: 1
  label: Dashboard intro
---

The public dashboard ships with a placeholder welcome message
("Your new SourceBans install"). Most operators want to replace it
with something specific to their community — a link to rules, a
link to Discord, a tagline, etc.

## Replace it

1. Sign into the panel as an admin with the **Web settings**
   permission (owners have this by default).

2. Navigate to **Admin Panel → Settings → Settings → Dashboard**.

3. Edit the **Intro text**. The settings page shows a live preview
   to the right as you type.

4. Click **Save changes**.

## Formatting

The intro text supports **Markdown**:

- Headings (`# Heading`, `## Subheading`, …)
- Bold (`**text**`) and italics (`*text*`)
- Links (`[label](https://example.com)`)
- Lists (numbered and unordered)
- Inline code (`` `code` ``)
- Code blocks (` ```language `)
- Blockquotes (`> quoted text`)

The renderer is [CommonMark](https://commonmark.org/) running in
"safe mode" — raw HTML in the source is escaped instead of rendered,
and `javascript:` / `data:` URLs are stripped. So you can paste
formatting freely without worrying about an admin accidentally (or
deliberately) injecting unsafe markup.

Reach for the **Markdown cheat sheet** link in the help icon next to
the editor if you need a quick reference.

## Examples

A simple welcome:

```markdown
# Welcome to **Example Gaming**

We run public 24/7 Casual TF2 servers in NA/EU. Read the rules
before joining: <https://example.com/rules>

Need to appeal a ban? [Submit an appeal here](/index.php?p=submit).
```

A short rules list:

```markdown
## House rules

1. No cheating — we use SourceBans++ and we will catch you.
2. Respect other players. No slurs, no harassment.
3. Mic spam after a verbal warning = mute.

Banned? [Appeal here](/index.php?p=submit) — we read every one.
```

## Tips

- **Keep it short.** This is the first thing visitors see; long
  walls of text get scrolled past.
- **Link to your community elsewhere.** Discord invite, forum, ban
  appeals page — anything actionable.
- **Test with a fresh browser window.** The live preview shows you
  what the dashboard will render, but it's still worth opening the
  public dashboard in an incognito tab to confirm it reads well
  to a logged-out visitor.

## Other panel settings worth knowing about

While you're under **Admin Panel → Settings**, a few other knobs
are worth a look on first install:

- **Settings → Settings → Sitename** — what the browser tab title
  shows.
- **Settings → Settings → URL** — used in emails the panel sends.
  Set it to your public URL.
- **Settings → Features** — toggles for comm blocks, public ban
  appeals, public report submissions, anonymous telemetry, and so
  on. Skim the list to see what's available.
- **Settings → Themes** — pick a different chrome if you've
  installed one.

For translating the panel's UI into another language, see
[Translating](/customization/translating/).
