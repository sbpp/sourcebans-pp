<h1 align="center">
    <a href="https://sbpp.github.io"><img src="https://raw.githubusercontent.com/sbpp/sourcebans-pp/v1.x/.github/logo.png" height="25%" width="25%"/></a>
    <br/>
    SourceBans++
</h1>

### [![GitHub release](https://img.shields.io/github/release/sbpp/sourcebans-pp.svg?style=flat-square&logo=github&logoColor=white)](https://github.com/sbpp/sourcebans-pp/releases) [![GitHub license](https://img.shields.io/github/license/sbpp/sourcebans-pp?color=blue&style=flat-square)](https://github.com/sbpp/sourcebans-pp/blob/v1.x/LICENSE) [![GitHub issues](https://img.shields.io/github/issues/sbpp/sourcebans-pp.svg?style=flat-square&logo=github&logoColor=white)](https://github.com/sbpp/sourcebans-pp/issues) [![GitHub pull requests](https://img.shields.io/github/issues-pr/sbpp/sourcebans-pp.svg?style=flat-square&logo=github&logoColor=white)](https://github.com/sbpp/sourcebans-pp/pulls) [![GitHub All Releases](https://img.shields.io/github/downloads/sbpp/sourcebans-pp/total.svg?style=flat-square&logo=github&logoColor=white)](https://github.com/sbpp/sourcebans-pp/releases) [![Travis](https://img.shields.io/travis/sbpp/sourcebans-pp.svg?style=flat-square&logo=travis)](https://travis-ci.org/sbpp/sourcebans-pp) [![AppVeyor](https://img.shields.io/appveyor/ci/Sarabveer/sourcebans-pp.svg?style=flat-square&logo=appveyor)](https://ci.appveyor.com/project/Sarabveer/sourcebans-pp) [![Codacy](https://img.shields.io/codacy/grade/1fc9e40bde8e40dca8680e4b2d51256b.svg?style=flat-square)](https://www.codacy.com/app/sbpp/sourcebans-pp) [![Discord](https://img.shields.io/discord/298914017135689728.svg?style=flat-square&logo=discord&label=discord)](https://discord.gg/4Bhj6NU)


Global admin, ban, and communication management system for the Source engine

### Issues
If you have an issue you can report it [here](https://github.com/sbpp/sourcebans-pp/issues/new).
To solve your problems as fast as possible fill out the **issue template** provided
or read how to report issues effectively [here](https://coenjacobs.me/2013/12/06/effective-bug-reports-on-github/).

### Useful Links

* Website: [SourceBans++](https://sbpp.github.io/)
* Install help: [SourceBans++ Docs](https://sbpp.github.io/docs/)
* FAQ: [SourceBans++ FAQ](https://sbpp.github.io/faq/)
* Forum Thread: [SourceBans++ - AlliedModders](https://forums.alliedmods.net/showthread.php?p=2303384)
* Discord Server: [SourceBans++ - Discord](https://discord.gg/4Bhj6NU)

### Requirements

```
* Webserver
  o PHP 7.4 or higher
    * ini setting: memory_limit greater than or equal to 64M
    * GMP extension
  o MySQL 5.6 or MariaDB 10 and higher
* Source Dedicated Server
  o MetaMod: Source
  o SourceMod: Greater Than or Equal To 1.10
```

## How to install a SourceBans++ release version

The easiest way of installing SourceBans++ is to use a [release version](https://github.com/sbpp/sourcebans-pp/releases), since 
those come bundled with all requiered code dependencies and pre-compiled sourcemod plugins.

The [quickstart](https://sbpp.dev/docs/quickstart/) guide gives you a detailed walktrough of the installation process.

## How to install the current master branch version

The master branch doesn't include the required dependencies or compiled plugins you need to run SourceBans++.
Here is a quick summary of getting the master branch code up and running.

### Installing webpanel dependencies
- Follow the [quickstart](https://sbpp.dev/docs/quickstart/) guide and upload the webpanel files to your web server
- Install [composer](https://getcomposer.org/) - [Installation Guide](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos)
- Go to the root of your SourceBans++ installation (where index.php is located)
- run ```composer install```

After successfully installing all dependencies you can procede with the [quickstart](https://sbpp.dev/docs/quickstart/) guide.

### Compiling SourceMod plugins
Follow the Guide '[Compiling SourceMod Plugins](https://wiki.alliedmods.net/Compiling_SourceMod_Plugins)' from the official SourceMod Wiki

## Built With

* [SourceMod](http://www.sourcemod.net/) - Scripting platform for the Source Engine - [License](https://raw.githubusercontent.com/sbpp/sourcebans-pp/v1.x/.github/SOURCEMOD-LICENSE.txt) - [GPL v3](https://raw.githubusercontent.com/sbpp/sourcebans-pp/v1.x/.github/GPLv3)
* [SourceBans 1.4.11](https://github.com/GameConnect/sourcebansv1) - Base of this project - [GPL v3](https://raw.githubusercontent.com/sbpp/sourcebans-pp/v1.x/.github/GPLv3) - [CC BY-NC-SA 3.0](https://github.com/sbpp/sourcebans-pp/blob/v1.x/LICENSE)
* [SourceComms](https://github.com/d-ai/SourceComms) - [GPL v3](https://raw.githubusercontent.com/sbpp/sourcebans-pp/v1.x/.github/GPLv3)
* [SourceBans TF2 Theme](https://forums.alliedmods.net/showthread.php?t=252533)

## Contributing

Please read [CONTRIBUTING.md](https://github.com/sbpp/sourcebans-pp/blob/v1.x/CONTRIBUTING.md) for details on our code of conduct, and the process for submitting pull requests to us. Read [SECURITY.md](https://github.com/sbpp/sourcebans-pp/blob/v1.x/SECURITY.md) if you have a Security Bug in SourceBans++

## Authors

* **GameConnect** - *Initial Work / SourceBans* - [GameConnect](https://www.gameconnect.net/)
* **Sarabveer Singh** - *Initial Work on SourceBans++* - [Sarabveer](https://github.com/Sarabveer)
* **Alexander Trost** - *Continuing Development on SourceBans++* - [Galexrt](https://github.com/galexrt)
* **Marvin Lukaschek** - *Continuing Development on SourceBans++* - [Groruk](https://github.com/groruk)

See also the list of [contributors](https://github.com/sbpp/sourcebans-pp/graphs/contributors) who participated in this project.

## License

This SourceMod plugins of this project are licensed under the `GNU GENERAL PUBLIC LICENSE Version 3` (GPLv3) [License](https://raw.githubusercontent.com/sbpp/sourcebans-pp/v1.x/.github/GPLv3).
The Web panel is licensed under the `Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported` (CC BY-NC-SA 3.0) [License](https://github.com/sbpp/sourcebans-pp/blob/v1.x/LICENSE).
