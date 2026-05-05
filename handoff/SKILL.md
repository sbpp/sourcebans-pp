---
name: sourcebans-pp-design
description: Use this skill to generate well-branded interfaces and assets for SourceBans++, either for production or throwaway prototypes/mocks/etc. Contains essential design guidelines, colors, type, fonts, assets, and UI kit components for prototyping the SourceBans++ web panel.
user-invocable: true
---

Read the README.md file within this skill, and explore the other available files.

If creating visual artifacts (slides, mocks, throwaway prototypes, etc), copy assets out and create static HTML files for the user to view. If working on production code, you can copy assets and read the rules here to become an expert in designing with this brand.

If the user invokes this skill without any other guidance, ask them what they want to build or design, ask some questions, and act as an expert designer who outputs HTML artifacts _or_ production code, depending on the need.

Key things to know about SourceBans++:

- It is an **operator tool** for community game-server admins (TF2 / CS / GMod / etc). Voice is terse, second-person, never marketing-flavoured.
- Visual DNA: **greige body `#bab5b2`** + **chocolate chrome `#38322c` / `#2a2723`** + **rust accent `#b05015`** + secondary slate-blue `#5885a2`. All-flat fills, no gradients, no shadows. Radius is 0 or 3px. Verdana 11px body, TF2 Build display face for nav.
- Iconography is **raster PNG** (Vista/Win7-era, 32×32, lifted from `assets/admin/`). Don't replace with vector icon libraries. Inline glyphs use Font Awesome 5 (free).
- Banlist rows have four state pastels: `#c8f7c5` (unbanned), `#f1a9a0` (permanent), `#fde3a7` (banned), `#e0e0e0`/`#eaebeb` (default).
- All transitions are `all 0.5s ease`. Tabs and table heads are `text-transform: uppercase`.

Use `colors_and_type.css` for tokens. Use `ui_kits/webpanel/` for ready-made React components recreating the panel. Copy `assets/admin/*.png` directly into any new design rather than substituting.
