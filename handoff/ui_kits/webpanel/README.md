# SourceBans++ Web Panel — UI Kit

A click-through React recreation of the SourceBans++ web panel, built from
the upstream PHP/Smarty templates and the default theme CSS
(`web/themes/default/css/main.css`). It is a **visual kit**, not a working
panel — there's no database, no auth, no SourceMod plugin behind it.

## What's covered

- **Dashboard** — server status table + dual-column "latest bans / latest
  blocked" lists.
- **Ban List** — paginated table with state-coloured rows
  (default / banned / unbanned / permanent).
- **Servers** — public server status list.
- **Submit a Ban** — public ban-submission form.
- **Protest a Ban** — appeals form with amber warning banner.
- **Admin Panel** — left rail (Admins, Bans, Comms, Groups, Mods, Servers,
  Settings, Your Account) with stubbed Bans / Admins / Servers / Settings
  panes.
- **Login** — flow shown when you click `LOGOUT` in the top bar.

## Components

| File | Components |
|---|---|
| `Primitives.jsx` | `PageTitle` `SectionHeader` `Card` `Btn` `Msg` `Field` `TextInput` `SelectInput` |
| `TopTabs.jsx` | Dark chocolate top nav (`#tabsWrapper`) with active/hover and rust LOGOUT |
| `Header.jsx` | 984×200 header, logo float-left, search top-right (slate `#5885a2`) |
| `ListTable.jsx` | `.listtable_top` head + `td.listtable_1*` row states |
| `AdminLayout.jsx` | 20% left rail (`#4f463e`) + content (`#e0e0e0`), with raster admin icons |
| `Forms.jsx` | `LoginCard` and `SubmitBanForm` composed views |

## Design fidelity

Visual values come straight from `assets/main.css`:

- Page bg `#bab5b2`, tab bar `#38322c`, active tab / table head `#2a2723`.
- Card surface `#e0e0e0`, soft row `#eaebeb`, h3/section header `#a69e97`.
- Banlist row states: `#c8f7c5` (unbanned), `#f1a9a0` (permanent), `#fde3a7`
  (banned), default `#e0e0e0` / `#eaebeb`.
- Buttons in 6 semantic tones, all flat fills, `border-radius: 3px`.
- Verdana 11px body. TF2 Build for the wordmark + nav row.

## Caveats

- We've used emoji fallbacks (`🐧` for Linux OS, `●` for the blocked-player
  bullet) where the original uses raster sprites we don't have local copies
  of. The mod-icon (`<ModIcon>`) is a coloured 14/18px square with a 2-letter
  label, **as a placeholder** — the upstream uses per-game PNGs from
  `web/images/games/` which were not imported. Flag if you need to swap in
  real assets.
- Country flags (used in some banlist columns upstream) are not represented;
  there are 250+ of them and they're per-row decorative.
- The transition is `all .25s ease` here vs the upstream `all .5s ease` —
  half-second feels too sluggish in a click-through demo. Bump back to
  `0.5s` if matching original feel exactly is required.
