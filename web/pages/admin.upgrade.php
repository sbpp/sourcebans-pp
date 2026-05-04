<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

Admin → Upgrade page (`?p=admin&c=upgrade`).

Renders the dry-run preview produced by the same {@see \Sbpp\Migrator\Migrator}
that backs `web/bin/upgrade.php`. The "Apply changes" button posts to the
`system.upgrade_apply` JSON action, which re-runs the planner inside the
handler so the apply always reflects the live diff at the moment of click.

Gated to `ADMIN_OWNER` by `route()` (page-builder.php) and again by the
template — defence in depth, since the template is what an admin sees if
they reach the page through any other path.
*************************************************************************/

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

global $userbank, $theme;

$planArr   = ['ok' => true, 'total' => 0, 'tables' => [], 'columns' => [], 'settings' => []];
$planError = null;

if ($userbank->HasAccess(ADMIN_OWNER)) {
    try {
        // Open a fresh PDO for the migrator: the differ runs raw
        // information_schema queries, which is awkward through the
        // `Database` wrapper (its `:prefix_` rewriter would mangle bound
        // table names). A bare PDO opened from the same env sidesteps
        // the whole issue and stays scoped to this page.
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            defined('DB_PORT') ? (int) DB_PORT : 3306,
            DB_NAME,
            defined('DB_CHARSET') ? (string) DB_CHARSET : 'utf8mb4',
        );
        $rawPdo = new \PDO($dsn, DB_USER, DB_PASS, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $migrator = \Sbpp\Migrator\Migrator::fromInstallSql(
            ROOT,
            $GLOBALS['PDO']->getPrefix(),
            defined('DB_CHARSET') ? (string) DB_CHARSET : 'utf8mb4',
        );
        $planArr = $migrator->plan($rawPdo)->toArray();
    } catch (\Throwable $e) {
        $planError = $e->getMessage();
    }
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminUpgradeView(
    permission_owner: $userbank->HasAccess(ADMIN_OWNER),
    plan: $planArr,
    plan_error: $planError,
));
