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
