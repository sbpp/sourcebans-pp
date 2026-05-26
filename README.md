<h1 align="center">
    <a href="https://sbpp.github.io"><img src="https://raw.githubusercontent.com/sbpp/sourcebans-pp/main/web/themes/default/images/favicon.svg" height="25%" width="25%"/></a>
    <br/>
    SourceBans++
</h1>

<p align="center">
  <a href="https://github.com/sbpp/sourcebans-pp/releases"><img src="https://img.shields.io/github/release/sbpp/sourcebans-pp.svg?style=flat-square&logo=github&logoColor=white" alt="GitHub release"></a>
  <a href="LICENSE.txt"><img src="https://img.shields.io/badge/License-Elastic_2.0-0080FF.svg" alt="License: Elastic 2.0"></a>
  <a href="https://github.com/sbpp/sourcebans-pp/issues"><img src="https://img.shields.io/github/issues/sbpp/sourcebans-pp.svg?style=flat-square&logo=github&logoColor=white" alt="GitHub issues"></a>
  <a href="https://github.com/sbpp/sourcebans-pp/releases"><img src="https://img.shields.io/github/downloads/sbpp/sourcebans-pp/total.svg?style=flat-square&logo=github&logoColor=white" alt="GitHub All Releases"></a>
  <a href="https://discord.gg/tzqYqmAtF5"><img src="https://img.shields.io/discord/298914017135689728.svg?style=flat-square&logo=discord&label=discord" alt="Discord"></a>
</p>

Global admin, ban, and communication management system for the Source
engine.

## Links

- **Docs:** <https://sbpp.github.io/> (install, upgrade, configure, FAQ)
- **Releases:** <https://github.com/sbpp/sourcebans-pp/releases>
- **Issues:** <https://github.com/sbpp/sourcebans-pp/issues>
- **Discord:** <https://discord.gg/tzqYqmAtF5>
- **AlliedModders thread:** <https://forums.alliedmods.net/showthread.php?p=2303384>

## Install

Download the latest `sourcebans-pp-X.Y.Z.webpanel-only.zip` from
[Releases](https://github.com/sbpp/sourcebans-pp/releases), unzip into
your web root, and visit `/install/` in a browser. The wizard walks
you through the rest.

Full install + plugin setup guide:
[**Quickstart**](https://sbpp.github.io/getting-started/quickstart/).

## Upgrade

[**Updating SourceBans++**](https://sbpp.github.io/updating/) covers
the upgrade path for each major version boundary.

## Contributing

Pull requests welcome. See [`CONTRIBUTING.md`](CONTRIBUTING.md) for the
guide, [`AGENTS.md`](AGENTS.md) for conventions and the local Docker
dev stack, and [`ARCHITECTURE.md`](ARCHITECTURE.md) for the codebase
tour.

PRs that touch the web panel (`web/**`) are covered by a Contributor
License Agreement; see [`CLA.md`](CLA.md). The CLA bot leaves
one-line sign instructions on your first such PR; you only need to
sign once. Plugin-only PRs (`game/addons/sourcemod/**`) stay under
GPLv3 and don't need a signature.

Security issues: please open an issue with reproduction details, or
contact a maintainer on Discord if the report needs to be private.

## Sponsors

SourceBans++ is built and maintained on volunteer time. If your
community, server network, or hosting business depends on it,
sponsoring development helps keep the panel healthy and funds the
modernization work landing across v2.x.

[![Sponsor on GitHub](https://img.shields.io/github/sponsors/sbpp?style=flat-square&logo=github&label=Sponsor%20on%20GitHub)](https://github.com/sponsors/sbpp)

**Game-server hosts and SourceBans++-as-a-feature providers offering
the panel as a hosted or managed service to third parties:** that
use case is reserved by the Elastic License 2.0 and covered by a
separate commercial license; see **License** below.

<!-- sponsors:start -->
<!-- Corporate sponsor logos land here once the corporate tier ships. -->
<!-- sponsors:end -->

## License

- **Web panel** (everything under `web/`): [Elastic License 2.0](LICENSE.txt).
  You may use, copy, modify, create derivative works of, and
  redistribute the panel — for hobby use, community use, running it
  for your own clan / network, bundling it into a Docker image,
  publishing a Pterodactyl egg, packaging it for a distro, all of
  that stays free. What ELv2 reserves is the right to **provide the
  panel as a hosted or managed service to third parties** (the
  classic "SourceBans++-as-a-feature" upsell from a game-server
  hosting business); for that, a separate commercial license is
  available. Open a thread in the
  [Commercial licensing discussion category](https://github.com/sbpp/sourcebans-pp/discussions/categories/commercial-licensing)
  or DM [@rumblefrog](https://github.com/rumblefrog) on the
  SourceBans++ [Discord](https://discord.gg/tzqYqmAtF5) (a dedicated
  inbox is on the roadmap once volume warrants it).
- **SourceMod plugins** (everything under `game/addons/sourcemod/`):
  [GPLv3](LICENSE-plugins.txt). Copyleft is the right tool there —
  the managed-service loophole the Elastic License closes for the
  panel doesn't exist for SourcePawn plugins that run in-process on
  the customer's game server.
- **Vendored third-party code** (LightOpenID, TinyMCE, the
  SourceBans 1.4.x lineage, etc.) keeps its own license terms; see
  [`THIRD-PARTY-NOTICES.txt`](THIRD-PARTY-NOTICES.txt).
