# Contributing to SourceBans++

Thanks for considering a contribution. Pull requests are welcome.
Before opening one, please take a few minutes to read this document and
the project's developer guides.

## Where to start

- **[`AGENTS.md`](AGENTS.md)** — conventions, the local Docker dev
  stack (`./sbpp.sh`), the six quality gates CI runs on every PR, and
  the cheat-sheet for common changes.
- **[`ARCHITECTURE.md`](ARCHITECTURE.md)** — codebase tour: how the
  web panel boots, request lifecycle, database access patterns, and
  how the SourceMod plugins fit in.
- **[Docs site](https://sbpp.github.io/)** (sources under
  [`docs/`](docs/)) — self-hoster documentation (install, upgrade,
  configure, FAQ). Self-hoster-visible changes ship docs updates in
  the same PR.

## Contributor License Agreement (web panel only)

The SourceBans++ **web panel** (everything under
[`web/`](web/)) is offered under a dual-licence model: free for hobby
and community use under [CC BY-NC-SA 3.0](LICENSE.md), and under a
separate commercial licence for production use by game-server hosting
companies that bundle SourceBans++ as a paid feature (see
[README.md](README.md#license) for the commercial-licence contact).

To make that arrangement workable, contributions to `web/**` from
anyone other than the project maintainer are accepted under a
Contributor Licence Agreement ("CLA") that grants the maintainer the
right to relicense the web panel under different terms in the future,
**including the commercial terms above**. You retain copyright in
everything you contribute — the CLA is a licence grant, not a
copyright assignment.

The full text lives in [`CLA.md`](CLA.md). It's about a page long.

### How to sign

1. Open a pull request that touches `web/**`.
2. Wait ~30 seconds for the CLA bot to comment with the sign
   instructions. (First-time PRs occasionally take a minute — that's
   normal; the bot is a GitHub Action, not a hosted service.)
3. Reply on the PR with exactly:

   > I have read the CLA Document and I hereby sign the CLA

4. The bot updates the CLA status check to green within a few seconds.
   You're done.

You only need to sign once — your signature applies to every future
PR you open against this repo, and is recorded on the
`cla-signatures` branch of the repository.

### Who doesn't need to sign

- **The maintainer** (`rumblefrog`) — covered by ownership.
- **GitHub App bots** (e.g. `dependabot[bot]`) — covered by ownership
  of the configuration that opens those PRs. The allowlist in
  [`.github/workflows/cla.yml`](.github/workflows/cla.yml) is the
  source of truth.
- **PRs that only touch SourceMod plugins** under
  `game/addons/sourcemod/**` — those stay under GPLv3 (strong
  copyleft) and aren't part of the dual-licence arrangement. If your
  PR mixes `web/**` and plugin files, the CLA check fires because of
  the `web/**` half; signing once unblocks both.

### Why a CLA

The web panel is offered free for hobby and community use, and under
a separate commercial licence for production use. The CLA gives the
maintainer the right to offer both — without it, every contributor
would need to be contacted individually for every future relicensing
decision, which doesn't scale.

If you have legal questions about the CLA, please open an issue or
reach out via the [Discord](https://discord.gg/tzqYqmAtF5) before
signing.

## Quality gates

CI runs six gates on every PR (PHPStan, PHPUnit, ts-check, API
contract, Playwright E2E, plugin build). Mirror them locally with
`./sbpp.sh` before opening — see the
[Quality gates](AGENTS.md#quality-gates) section of `AGENTS.md` for
the full list and which subset applies to plugin-only changes.

## Reporting security issues

Please open an issue with reproduction details. If the report needs
to be private, contact a maintainer on
[Discord](https://discord.gg/tzqYqmAtF5) first.
