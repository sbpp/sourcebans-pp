# Vendored web fonts (SourceBans++ 2026 theme)

Self-hosters install offline; the panel never reaches out to Google
Fonts or any other CDN. To refresh these files, fetch the upstream
WOFF2s at the URLs below and overwrite in place.

| File | Source URL | Notes |
| --- | --- | --- |
| `Inter-Variable.woff2` | https://rsms.me/inter/font-files/InterVariable.woff2 | Variable font; covers 100–900 weights in one file. Upstream: [rsms/inter](https://github.com/rsms/inter). |
| `JetBrainsMono-Regular.woff2` | https://github.com/JetBrains/JetBrainsMono/raw/v2.304/fonts/webfonts/JetBrainsMono-Regular.woff2 | Pinned to JetBrainsMono v2.304. Used for SteamIDs / IPs / ban IDs. |
| `JetBrainsMono-Bold.woff2` | https://github.com/JetBrains/JetBrainsMono/raw/v2.304/fonts/webfonts/JetBrainsMono-Bold.woff2 | Pinned to JetBrainsMono v2.304. Used by `.font-mono.font-semibold`. |

`@font-face` declarations live at the top of `../css/theme.css` and
reference these files via `url('../fonts/<file>.woff2')`.
