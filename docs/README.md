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
| `src/content.config.ts` | Astro content-collection schema. Re-exports Starlight's `docsLoader` + `docsSchema` so the `src/content/docs/` collection is wired with the right loader and front-matter validation. Edit when adding a new collection (none currently). |
| `src/content/docs/` | All page content, organised by sidebar group (`getting-started/`, `setup/`, `troubleshooting/`, …). `.md` for plain pages, `.mdx` for pages that use Starlight components (Tabs, LinkCard, Card, etc.). |
| `src/styles/sbpp.css` | Panel-parity overrides — brand orange, zinc neutrals, semantic asides, geometry, focus ring. Mirrors `web/themes/default/css/theme.css` token-for-token. **When the panel's `:root` / `html.dark` blocks change, mirror the change here in the same PR** (AGENTS.md "Keep the docs in sync"). |
| `src/components/ThemeProvider.astro` | Override of Starlight's stock dark-leaning theme provider. Defaults the unset preference to `'auto'` (resolves via `prefers-color-scheme`) to match the panel's `'system'` first-paint default, AND ships a `<noscript><style>` block that re-applies the LIGHT-mode tokens onto `:root[data-theme='dark']` so JS-disabled visitors see light (Starlight 0.30 hardcodes `data-theme="dark"` SSR'd; the panel paints light without JS, so this guard restores parity). The user toggle still wins on subsequent visits via `localStorage['starlight-theme']`. |
| `src/components/Footer.astro` | Override of Starlight's stock per-page footer. Re-renders the stock EditLink + LastUpdated + Pagination + optional Built-with-Starlight kudos (pulled straight from Starlight's `virtual:` namespace so future Starlight upgrades pick up new footer chrome automatically) and appends a small "Support SourceBans++ on GitHub Sponsors" affordance below them. The topbar already carries a heart-icon social link and the landing page carries a one-liner under the CardGrid; the footer is the third surface so anyone who reads to the end of a docs page can find the sponsor link without scrolling back to the top. |
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

Four workflows under `.github/workflows/` cover the docs site:

| Workflow | Trigger | What it does |
| -------- | ------- | ------------ |
| `docs-build.yml` | PRs + main pushes touching `docs/**` | Runs `npm run build`. Uploads the built `dist/` as an artifact. |
| `docs-deploy-trigger.yml` | main pushes touching `docs/**` | Fires a `repository_dispatch` (event_type=`docs-changed`) into `sbpp/sbpp.github.io`, which kicks the actual GitHub Pages deploy. Requires the `DOCS_DEPLOY_PAT` repo secret (fine-grained PAT, `Actions: Read and write` on `sbpp.github.io` only). Until the secret is set, the dispatch step is skipped on every run (the run is green-with-skipped, not red-failing); the deploy shell in `sbpp.github.io` still has a `workflow_dispatch` button as a manual fallback. |
| `docs-screenshots-build.yml` | PRs touching `docs/scripts/capture.mjs` or `docs/package*.json` | Sandboxed verification: `npm ci` + `node --check scripts/capture.mjs`. No secrets, no write permissions; runs the standard `pull_request` token. Catches "did the capture script still parse" on every PR. |
| `docs-screenshots-capture.yml` | PRs labelled `safe-to-screenshot` (same-repo only) + `workflow_dispatch` | Boots the dev stack, seeds the DB, runs `npm run capture` from a TRUSTED-FROM-MAIN checkout, commits PNG deltas back to the PR branch. |

### Screenshot capture security model

`docs-screenshots-capture.yml` runs `pull_request_target` with
`contents: write` so it can write the regenerated PNGs back to the PR
branch. To keep that token out of contributor reach, the workflow:

1. **Splits the checkout.** The trusted code surface (the capture
   script + its package-lock.json) is checked out from the PR's base
   branch (effectively `main`). The PR head is checked out into a
   separate directory that's used only for `docker compose up` and as
   the screenshot output destination — no JS/PHP code from the PR
   head runs on the runner.
2. **Gates on the `safe-to-screenshot` label.** A maintainer applies
   the label after reviewing the PR's docker / install / panel
   changes; the workflow's `if:` guard short-circuits without it.
3. **Auto-strips the label on every push.** A
   `unlabel-on-synchronize` job removes `safe-to-screenshot` whenever
   a new commit lands so the maintainer must re-apply after reviewing
   the new code. A label applied to a benign-looking opening commit
   doesn't grant blanket consent for a follow-up commit.
4. **Refuses fork PRs.** The `head.repo.full_name == github.repository`
   check rejects fork-originated PRs at the workflow level — pushing
   the branch into the upstream repo first is the supported path for
   contributions that need screenshot regeneration.

Maintainers: when you apply `safe-to-screenshot`, eyeball the PR's
diff under `docker/`, `web/install/`, the `web/themes/default/`
templates, and `docs/scripts/capture.mjs` first. Anything that could
exfil environment data or escape the docker sandbox is the threat
model; everything else is fine.

The label needs to exist in the repo first — until that happens (one-
time repo setup), the workflow runs but its `if:` gate silently
returns false. Create the label via the repo's Issues → Labels page
(or `gh label create safe-to-screenshot --description "Maintainer ack to run docs-screenshots-capture.yml" --color 'D73A4A'`).

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
