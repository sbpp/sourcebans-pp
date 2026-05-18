<?php
// Auto-prepended on every request inside the dev container.
//
// Issue #1335 C1: pre-#1335 init.php exempted `HTTP_HOST == "localhost"`
// from the install/ + updater/ presence guard, and this file's job was
// to rewrite `HTTP_HOST` to drop the port (`localhost:8080` -> `localhost`)
// so the bare-string match would accept dev requests. That entire
// shape was a panel-takeover path — anyone reaching a production
// panel with a `localhost` Host header (port-forward, SSH tunnel,
// ngrok, Cloudflare Tunnel) bypassed the guard.
//
// The post-#1335 contract: init.php's guard is unconditional, with a
// single explicit dev-only escape hatch. Defining `SBPP_DEV_KEEP_INSTALL`
// here tells `sbpp_check_install_guard()` to skip the install/ +
// updater/ presence check. The constant is loud-named so a
// production-side define is visibly wrong; the panel's release
// tarball has no path to set it; only the dev container's
// `auto_prepend_file` ini directive (configured in
// `docker/Dockerfile`) actually defines it.
//
// The dev container needs the escape hatch because the worktree is
// bind-mounted into the panel's web root and includes both
// `install/` and `updater/` from git. Deleting either is not an
// option in dev — the docker-compose dev stack seeds the DB out of
// band (the wizard isn't exercised), but `install/` itself stays in
// place so wizard development happens against the same files that
// ship to production.
if (!defined('SBPP_DEV_KEEP_INSTALL')) {
    define('SBPP_DEV_KEEP_INSTALL', true);
}
