<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

global $theme, $userbank;

new AdminTabs([], $userbank, $theme);

if (!isset($_GET['id'])) {
    echo '<div id="msg-red" >
	<i class="fas fa-times fa-2x"></i>
	<b>Error</b>
	<br />
	No submission or protest id specified. Please only follow links
</div>';
    PageDie();
}

if (!isset($_GET['type']) || ($_GET['type'] != 's' && $_GET['type'] != 'p')) {
    echo '<div id="msg-red" >
	<i class="fas fa-times fa-2x"></i>
	<b>Error</b>
	<br />
	Invalid type. Please only follow links
</div>';
    PageDie();
}

// Submission
$email = "";
if ($_GET['type'] == 's') {
    $GLOBALS['PDO']->query('SELECT email FROM `:prefix_submissions` WHERE subid = :id');
    $GLOBALS['PDO']->bind(':id', $_GET['id']);
    $row   = $GLOBALS['PDO']->single();
    $email = $row['email'] ?? "";
} elseif ($_GET['type'] == 'p') {
    // Protest
    $GLOBALS['PDO']->query('SELECT email FROM `:prefix_protests` WHERE pid = :id');
    $GLOBALS['PDO']->bind(':id', $_GET['id']);
    $row   = $GLOBALS['PDO']->single();
    $email = $row['email'] ?? "";
}

if (empty($email)) {
    echo '<div id="msg-red" >
	<i class="fas fa-times fa-2x"></i>
	<b>Error</b>
	<br />
	There is no email to send to supplied.
</div>';
    PageDie();
}

// $_GET['type'] is constrained above to the literal 's' or 'p' and
// $_GET['id'] is cast to int, so the resulting `CheckEmail('s', 42)`
// JS expression contains no caller-controlled data and is safe to drop
// into the template's onclick attribute via {nofilter}.
$emailJs = "CheckEmail('" . $_GET['type'] . "', " . (int) $_GET['id'] . ")";

echo '<div id="admin-page-content"><div id="1">';
\Sbpp\View\Renderer::render($theme, new \Sbpp\View\AdminBansEmailView(
    email_addr: (string) $email,
    email_js: $emailJs,
));
echo '</div></div>';
