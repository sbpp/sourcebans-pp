<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

// Admin → Audit log handler (#1123 B19). Auth is enforced upstream by
// the `CheckAdminAccess(ADMIN_OWNER)` gate the matching `case 'audit':`
// arm of `web/includes/page-builder.php` runs before dispatching here,
// matching every other admin.*.php in this directory.
//
// SSR: severity chips + free-text search + numeric pager are all
// driven by GET params, no JSON action. Variable shape mirrors the
// handoff design (handoff/pages/admin/audit.tpl) but pivots filters
// onto the `:prefix_log.type` enum (`m`/`w`/`e`) since the table only
// stores severity, not the action-kind taxonomy the mockup illustrates.

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
global $userbank, $theme;

$AuditPerPage = SB_BANS_PER_PAGE;

$page = 1;
if (isset($_GET['page']) && (int) $_GET['page'] > 0) {
    $page = (int) $_GET['page'];
}

$severity = '';
if (isset($_GET['severity']) && in_array($_GET['severity'], ['m', 'w', 'e'], true)) {
    $severity = (string) $_GET['severity'];
}

$search = '';
if (isset($_GET['search']) && is_string($_GET['search'])) {
    // Cap search input to a sane length so the page can't be wedged by a
    // pathological query string. The LIKE clause below adds the wildcards.
    $search = mb_substr(trim((string) $_GET['search']), 0, 200);
}

$where = [];
$params = [];
if ($severity !== '') {
    $where[] = 'l.type = :severity';
    $params[':severity'] = $severity;
}
if ($search !== '') {
    $where[] = '(l.title LIKE :search OR l.message LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
$whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

// Count first so we can clamp $page against an empty / over-paged
// filter result before computing the offset.
$GLOBALS['PDO']->query("SELECT COUNT(l.lid) AS cnt FROM `:prefix_log` AS l" . $whereSql);
foreach ($params as $k => $v) {
    $GLOBALS['PDO']->bind($k, $v);
}
$countRow = $GLOBALS['PDO']->single();
$total = (int) ($countRow['cnt'] ?? 0);

$pageCount = ($total > 0) ? (int) ceil($total / $AuditPerPage) : 1;
if ($page > $pageCount) {
    $page = $pageCount;
}
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $AuditPerPage;

$GLOBALS['PDO']->query(
    "SELECT l.lid, l.type, l.title, l.message, l.host, l.created, ad.user
       FROM `:prefix_log` AS l
       LEFT JOIN `:prefix_admins` AS ad ON ad.aid = l.aid"
    . $whereSql .
    " ORDER BY l.created DESC, l.lid DESC
       LIMIT :start, :lim"
);
foreach ($params as $k => $v) {
    $GLOBALS['PDO']->bind($k, $v);
}
$GLOBALS['PDO']->bind(':start', $offset, PDO::PARAM_INT);
$GLOBALS['PDO']->bind(':lim',   $AuditPerPage, PDO::PARAM_INT);
$rows = $GLOBALS['PDO']->resultset();

// Severity letter → label + CSS class. The class ties to .badge--info /
// .badge--warning / .badge--error in page_admin_audit.tpl's local <style>,
// so a row with an unknown letter falls through as a neutral "system"
// badge instead of throwing.
$severityMap = [
    'm' => ['label' => 'Info',    'class' => 'info'],
    'w' => ['label' => 'Warning', 'class' => 'warning'],
    'e' => ['label' => 'Error',   'class' => 'error'],
];

$auditLog = [];
foreach ($rows as $row) {
    $sevLetter = (string) ($row['type'] ?? '');
    $sevMeta = $severityMap[$sevLetter] ?? ['label' => 'System', 'class' => 'system'];
    $createdInt = (int) ($row['created'] ?? 0);
    $auditLog[] = [
        'tid'            => (int) ($row['lid'] ?? 0),
        'severity'       => $sevLetter,
        'severity_label' => $sevMeta['label'],
        'severity_class' => $sevMeta['class'],
        'time_human'     => $createdInt > 0 ? Config::time($createdInt) : '—',
        'time_iso'       => $createdInt > 0 ? gmdate('c', $createdInt) : '',
        'actor'          => (string) ($row['user'] ?? '') !== '' ? (string) $row['user'] : 'system',
        'title'          => (string) ($row['title'] ?? ''),
        'detail'         => (string) ($row['message'] ?? ''),
        'ip'             => (string) ($row['host'] ?? ''),
    ];
}

$baseQuery = ['p' => 'admin', 'c' => 'audit'];
if ($severity !== '') {
    $baseQuery['severity'] = $severity;
}
if ($search !== '') {
    $baseQuery['search'] = $search;
}
$prevUrl = '';
$nextUrl = '';
if ($page > 1) {
    $prevQuery = $baseQuery;
    $prevQuery['page'] = $page - 1;
    $prevUrl = 'index.php?' . http_build_query($prevQuery);
}
if ($page < $pageCount) {
    $nextQuery = $baseQuery;
    $nextQuery['page'] = $page + 1;
    $nextUrl = 'index.php?' . http_build_query($nextQuery);
}

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AuditLogView(
    audit_log:        $auditLog,
    total_count:      $total,
    current_severity: $severity,
    search:           $search,
    current_page:     $page,
    page_count:       $pageCount,
    has_prev:         $page > 1,
    has_next:         $page < $pageCount,
    prev_url:         $prevUrl,
    next_url:         $nextUrl,
));
