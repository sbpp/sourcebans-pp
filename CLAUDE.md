# SourceBans++ — guide for Claude Code

## Repo layout

- `web/` — PHP 8.2 web panel (Smarty templates, PDO/MariaDB). Entry: `web/index.php`.
- `web/install/` — first-run installer; **not used** in the Docker dev setup (we seed directly).
- `game/addons/` — SourceMod plugin sources.
- `docker/`, `docker-compose.yml`, `sbpp.sh` — local dev stack (see below).

## Local dev stack (Docker)

Everything in `docker/README.md`. The TL;DR for an LLM that wants to run things:

```sh
./sbpp.sh up                # build + start; idempotent, safe to re-run
./sbpp.sh status            # are containers up?
./sbpp.sh logs web          # tail web logs
./sbpp.sh phpstan           # run static analysis
./sbpp.sh shell             # bash inside the web container
./sbpp.sh mysql             # mariadb client on the dev DB
./sbpp.sh db-reset          # wipe DB volume and re-seed
./sbpp.sh down              # stop, keep volumes
./sbpp.sh reset             # stop and drop ALL volumes
```

After `up`, the panel is at <http://localhost:8080>, login **admin / admin**.
Adminer at :8081, Mailpit at :8025, MariaDB exposed on host port 3307.

The web container bind-mounts `./web`, so file edits are picked up on the
next request — no restart needed unless you change `composer.json`
(re-run `./sbpp.sh composer install`) or anything in `docker/` (run
`./sbpp.sh rebuild`).

## Quality gates

PHPStan is the project's static-analysis gate. Inside the dev container:

```sh
./sbpp.sh phpstan
```

`web/phpstan-baseline.neon` captures pre-existing violations so a clean tree
passes; new code should be clean. Regenerate the baseline only when a real
fix removes an entry — see the README for the command.

## Conventions

- **Don't add new `install/` flow**. The dev container provisions the DB out
  of band; the installer wizard is legacy and stays for production users.
- **Database access** goes through `web/includes/Database.php` (PDO).
  ADOdb has been fully removed (commit `b9c812b2`); do not reintroduce it.
- **CSRF** tokens are required on every state-changing form/xajax call
  (commit `352148a9`). Use `Smarty {csrf_field}` in templates and
  `CSRF::validate()` in handlers.
