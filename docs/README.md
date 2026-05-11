# SourceBans++ docs

Source of truth for the SourceBans++ documentation site published at
<https://sbpp.github.io/>. Built with [Astro](https://astro.build/) +
[Starlight](https://starlight.astro.build/) and deployed via the
sibling `sbpp/sbpp.github.io` repo (which is now a thin deploy
shell — all authoring happens here).

## Where things live

| Path | What it is |
| ---- | ---------- |
| `astro.config.mjs` | Site config, sidebar tree, social links, custom CSS wiring. |
| `src/content/docs/` | All page content, organised by sidebar group (`getting-started/`, `setup/`, `troubleshooting/`, …). `.md` for plain pages, `.mdx` for pages that use Starlight components (Tabs, LinkCard, Card, etc.). |
| `src/styles/sbpp.css` | Panel-parity overrides — brand orange, zinc neutrals, semantic asides, geometry, focus ring. Mirrors `web/themes/default/css/theme.css` token-for-token. **When the panel's `:root` / `html.dark` blocks change, mirror the change here in the same PR** (AGENTS.md "Keep the docs in sync"). |
| `src/components/ThemeProvider.astro` | Override of Starlight's stock dark-leaning theme provider so the docs site boots **light** to match the panel's first-paint behavior. The user toggle still wins on subsequent visits via `localStorage['starlight-theme']`. |
| `src/assets/logo.svg` + `public/favicon.svg` | The panel's brand mark, copied verbatim from `web/themes/default/images/favicon.svg`. |
| `src/assets/auto/{install,panel}/` | Auto-captured screenshots from `docs/scripts/capture.mjs`. **These ARE committed** so the screenshot diff lands with the UI change. |
| `scripts/capture.mjs` | Playwright-driven capture script (see [Capturing screenshots](#capturing-screenshots) below). |
| `tsconfig.json` | Extends `astro/tsconfigs/strict`. |

## Local dev

Standard Astro dev loop. Node 20 LTS or newer.

```sh
cd docs
npm install
npm run dev
```

The dev server prints a localhost URL (default `http://localhost:4321`).
Edits to anything under `src/` hot-reload without a restart.

To produce a production build:

```sh
cd docs
npm run build
npm run preview            # serve the built site locally
```

The production build runs Pagefind under the hood and writes the
search index into `dist/pagefind/`. The deploy shell in
sbpp.github.io picks this up and serves it as-is.

## Capturing screenshots

Auto-captured screenshots live under `src/assets/auto/`. The capture
script needs the dev stack running:

```sh
# from the repo root
./sbpp.sh up

# wait for the panel to come up at http://localhost:8080
# (admin/admin login is seeded automatically)

cd docs
npm install                # first time only
npx playwright install chromium      # first time only
npm run capture
```

The script writes PNGs into `src/assets/auto/install/` and
`src/assets/auto/panel/`. Inspect `git diff src/assets/auto/` to see
what changed; commit the deltas alongside the UI change that produced
them.

The hardcoded `STEAM_API_KEY` is `00000000000000000000000000000000`
(an all-zero dummy) — the dev seed never round-trips back to Steam,
so the zero key is safe and avoids leaking real keys into screenshots.

To override the panel URL (e.g. running a parallel stack on a
different port — see AGENTS.md "Parallel stacks"):

```sh
PANEL_URL=http://localhost:8189 npm run capture
```

## CI

Three workflows under `.github/workflows/` cover the docs site:

| Workflow | Trigger | What it does |
| -------- | ------- | ------------ |
| `docs-build.yml` | PRs + main pushes touching `docs/**` | Runs `npm run build`. Uploads the built `dist/` as an artifact. |
| `docs-deploy-trigger.yml` | main pushes touching `docs/**` | Fires a `repository_dispatch` (event_type=`docs-changed`) into `sbpp/sbpp.github.io`, which kicks the actual GitHub Pages deploy. Requires the `DOCS_DEPLOY_APP_ID` repo variable + `DOCS_DEPLOY_APP_KEY` repo secret to be configured (one-time cutover step). |
| `docs-screenshots.yml` | PRs labelled `affects-ui` + `workflow_dispatch` | Boots the dev stack, runs `npm run capture`, commits any PNG deltas back to the PR branch via `stefanzweifel/git-auto-commit-action`. |

### The `affects-ui` label

When a PR changes UI under `web/install/` or the panel chrome that's
screenshotted in docs, **apply the `affects-ui` label** so
`docs-screenshots.yml` regenerates the captures and commits them
back to your branch. Alternatively, run `npm run capture` locally and
commit the diff yourself.

The label needs to exist in the repo first — until that happens (one-
time repo setup), the workflow runs but its `if:` gate silently
returns false. Create the label via the repo's Issues → Labels page
(or `gh label create affects-ui --description "Triggers docs-screenshots.yml" --color 'EA580C'`).

## Authoring conventions

- Plain Markdown by default. Use `.mdx` only when the page needs
  Starlight components (`Tabs`, `LinkCard`, `CardGrid`, `Aside` as a
  component, `Steps`, etc.).
- For prose asides, prefer the Markdown-native `:::note` /
  `:::tip` / `:::caution` / `:::danger` syntax over `<Aside>`. Both
  render the same; the prose form keeps Markdown files readable in
  plain editors.
- Cross-link to the most relevant troubleshooting / setup page on
  any step that has a known failure mode (DB step → Database errors
  + Could not find driver, write-perms step → Browser freeze /
  Cloudflare, etc.). Cross-link asides are not optional polish —
  they're the difference between "you're stuck" and "click here".
- Internal links use **absolute paths** with a trailing slash:
  `[Quickstart](/getting-started/quickstart/)`. Starlight's link
  resolver rewrites these onto the configured `base`.
- External links open in the same tab; Starlight applies
  `rel="noopener"` + an external-link affordance automatically.
- Code-block languages use Shiki names (`sh`, `php`, `sql`, `ini`,
  `yaml`, `json`, `text`). For SourceMod KeyValues files, use `ini`
  — it's structurally close enough to highlight cleanly.
- Headings: each page has exactly one `# H1` (set via the
  front-matter `title`); body content starts at `## H2`. Skipping
  levels (e.g. `## H2` → `#### H4`) breaks Starlight's auto-ToC.

## Source of truth

These docs live in [`sbpp/sourcebans-pp` under `docs/`](https://github.com/sbpp/sourcebans-pp/tree/main/docs).
The site at <https://sbpp.github.io/> is published from there by CI
on every merge to `main`. Open PRs against this directory; the deploy
shell repo doesn't accept content PRs anymore.
