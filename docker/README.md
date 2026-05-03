# Local development with Docker

A one-command dev environment for the SourceBans++ web panel. Spins up
PHP/Apache, MariaDB, Adminer, and Mailpit, seeds the database, and creates a
ready-to-use admin login. Source under `web/` is bind-mounted, so edits show
up on the next request — no rebuilds needed.

## Prerequisites

- Docker 24+ with the Compose plugin (`docker compose`, not `docker-compose`)
- Ports `8080`, `8081`, `8025`, `1025`, `3307` free on the host (override in `.env`)

## Quick start

```sh
./sbpp.sh up
```

That builds the web image, starts everything in the background, runs
`composer install` on first boot, and seeds the schema + a default admin.

Then:

| Service              | URL                          | Notes                          |
| -------------------- | ---------------------------- | ------------------------------ |
| SourceBans++ panel   | <http://localhost:8080>      | login: **admin** / **admin**   |
| Adminer (DB UI)      | <http://localhost:8081>      | server `db`, user `sourcebans` |
| Mailpit (catch-all)  | <http://localhost:8025>      | SMTP on `mailpit:1025`         |
| MariaDB (host port)  | `localhost:3307`             | user `sourcebans` pw same      |

To stop:

```sh
./sbpp.sh down       # keep volumes
./sbpp.sh reset      # nuke volumes too (fresh DB on next up)
```

## What's in the box

```
Dockerfile               php:8.2-apache + pdo_mysql, gmp, intl, zip, mbstring, opcache, composer
docker-compose.yml       web, db, adminer, mailpit
docker/php/
    web-entrypoint.sh    waits for DB, renders config.php, runs composer install, fixes cache perms
    dev-prepend.php      normalizes HTTP_HOST so init.php's localhost guard accepts :8080
docker/db-init/
    00-render-schema.sh  on first DB init: substitutes {prefix}/{charset}, loads struc/data, seeds admin
sbpp.sh                  thin wrapper around `docker compose` + common dev tasks
```

The web container mounts `./web` from the host, with two named-volume
overlays (`vendor/` and `cache/`) so Composer artifacts and Smarty cache
don't leak onto the host filesystem.

## Common tasks

```sh
./sbpp.sh logs                    # tail everything
./sbpp.sh logs web                # tail one service
./sbpp.sh shell                   # bash in the web container (root)
./sbpp.sh shell db                # mariadb client connected to dev DB
./sbpp.sh composer install        # run composer in the web container
./sbpp.sh phpstan                 # phpstan analyse with the project's phpstan.neon
./sbpp.sh db-dump backup.sql      # mysqldump to host file
./sbpp.sh db-load fixtures.sql    # pipe a SQL file into the DB
./sbpp.sh db-reset                # drop just the DB volume and re-seed
./sbpp.sh rebuild                 # `--no-cache` rebuild of the web image
```

## How the bootstrap works

1. **DB**: MariaDB only runs `/docker-entrypoint-initdb.d/*` on the **first**
   boot of a fresh data volume. Our `00-render-schema.sh` reads
   `web/install/includes/sql/struc.sql` and `data.sql`, replaces `{prefix}`
   with `sb` and `{charset}` with `utf8mb4`, pipes them in, then inserts an
   `admin` row with a pinned bcrypt hash for the password `admin`.
2. **Web**: the entrypoint waits until MariaDB answers, generates
   `web/config.php` from env vars (only if absent or empty), runs
   `composer install` if `vendor/` is missing, then `exec`s Apache.
3. **`HTTP_HOST` shim**: `init.php` blocks the panel when the `install/`
   directory is present unless `HTTP_HOST == "localhost"`. The bind mount
   means we can't delete `install/` from the container, and our forwarded
   port produces `localhost:8080`. `dev-prepend.php` is loaded via
   `auto_prepend_file` and rewrites the host to `localhost` for any loopback
   request, satisfying the guard without weakening it elsewhere.

## Customizing

Drop a `.env` next to `docker-compose.yml` to override published ports — see
`docker/.env.example`. To change DB credentials, edit the `environment:`
blocks in `docker-compose.yml` and `./sbpp.sh reset` to re-seed.

To pre-seed your own data, drop additional `*.sql` or `*.sh` files into
`docker/db-init/`. They'll be picked up on the next fresh init (after
`./sbpp.sh reset`).

## Caveats

- **Dev only.** The `HTTP_HOST` shim, the seeded admin password, and the
  exposed DB port are not safe in production.
- **Composer cache.** First `up` can take a couple of minutes to download
  dependencies. Subsequent boots reuse the `vendor` volume.
- **macOS / Windows file watching.** Bind mounts on non-Linux Docker hosts
  can be slow; OPcache is set to revalidate every request which may amplify
  this. Bump `opcache.revalidate_freq` in `docker/Dockerfile` if it bites.
