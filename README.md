<h1 align="center">
    <a href="https://sbpp.github.io"><img src="https://raw.githubusercontent.com/sbpp/sourcebans-pp/main/web/themes/default/images/favicon.svg" height="25%" width="25%"/></a>
    <br/>
    SourceBans++
</h1>

<p align="center">
  <a href="https://github.com/sbpp/sourcebans-pp/releases"><img src="https://img.shields.io/github/release/sbpp/sourcebans-pp.svg?style=flat-square&logo=github&logoColor=white" alt="GitHub release"></a>
  <a href="LICENSE.md"><img src="https://img.shields.io/badge/License-CC_BY--NC--SA_3.0-blue.svg" alt="License: CC BY-NC-SA 3.0"></a>
  <a href="https://github.com/sbpp/sourcebans-pp/issues"><img src="https://img.shields.io/github/issues/sbpp/sourcebans-pp.svg?style=flat-square&logo=github&logoColor=white" alt="GitHub issues"></a>
  <a href="https://github.com/sbpp/sourcebans-pp/releases"><img src="https://img.shields.io/github/downloads/sbpp/sourcebans-pp/total.svg?style=flat-square&logo=github&logoColor=white" alt="GitHub All Releases"></a>
  <a href="https://discord.gg/tzqYqmAtF5"><img src="https://img.shields.io/discord/298914017135689728.svg?style=flat-square&logo=discord&label=discord" alt="Discord"></a>
</p>

Global admin, ban, and communication management system for the Source
engine.

## Links

- **Docs:** <https://sbpp.github.io/> — install, upgrade, configure, FAQ
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
License Agreement — see [`CLA.md`](CLA.md). The CLA bot leaves
one-line sign instructions on your first such PR; you only need to
sign once. Plugin-only PRs (`game/addons/sourcemod/**`) stay under
GPLv3 and don't need a signature.

Security issues: please open an issue with reproduction details, or
contact a maintainer on Discord if the report needs to be private.

## Sponsors

SourceBans++ is built and maintained on volunteer time. If your
community, server network, or hosting business depends on it,
sponsoring development helps keep the panel healthy &mdash; and
funds the modernization work that's been landing across v2.x.

[![Sponsor on GitHub](https://img.shields.io/github/sponsors/sbpp?style=flat-square&logo=github&label=Sponsor%20on%20GitHub)](https://github.com/sponsors/sbpp)

**Game-server hosts and SourceBans++-as-a-feature providers** &mdash;
production / commercial use of the web panel is covered by a
separate commercial license; see **License** below.

<!-- sponsors:start -->
<!-- Corporate sponsor logos land here once the corporate tier ships. -->
<!-- sponsors:end -->

## License

- **SourceMod plugins:** [GPLv3](https://raw.githubusercontent.com/sbpp/sourcebans-pp/v1.x/.github/GPLv3).
- **Web panel:** [CC BY-NC-SA 3.0](LICENSE.md).
  Hobby / community use is free under the linked terms; for
  production / commercial use (e.g. game-server hosting companies
  bundling SourceBans++ as a paid feature), a separate commercial
  license is available &mdash; reach out via the contact link on
  the [sponsor page](https://sbpp.github.io/sponsor/) or Discord.
