<?php
function route(int $step)
{
    $title = match($step) {
        6 => 'AMXBans Import',
        5 => 'Initial Setup',
        4 => 'Table Creation',
        3 => 'System Requirements Check',
        2 => 'Database Details',
        default => 'License agreement'
    };

    return [$title, "/page.{$step}.php"];
}

function build(string $title, string $page, int $step)
{
    require_once(TEMPLATES_PATH.'/header.php');
    require_once(TEMPLATES_PATH.'/submenu.php');
    require_once(TEMPLATES_PATH.'/content.header.php');
    require_once(TEMPLATES_PATH.$page);
    require_once(TEMPLATES_PATH.'/footer.php');
}
