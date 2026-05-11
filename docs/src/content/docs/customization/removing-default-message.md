---
title: Removing the default message
description: Customizing the dashboard intro text that ships with a fresh SourceBans++ install.
sidebar:
  order: 1
---

Removing the **"Your new SourceBans install SourceBans successfully
installed!"** copy that ships on a fresh install.

## Removal

1. Navigate to **Admin Panel** → **Webpanel Settings**.

2. Edit the **Intro Text**.

3. **Save Changes** and you're done.

:::tip
The intro text is rendered through SB++'s safe Markdown pipeline
(CommonMark with `html_input: 'escape'`), so you can drop in formatting
— headings, links, lists — and the panel will render it as HTML on the
public dashboard. The settings page ships a live preview pane so you
can see how it'll look before you save.
:::
