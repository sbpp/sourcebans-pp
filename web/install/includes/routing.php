<?php
function route($step)
{
    switch ($step) {
        case 6:
            return ['AMXBans Import', '/page.6.php'];
        case 5:
            return ['Initial Setup', '/page.5.php'];
        case 4:
            return ['Table Creation', '/page.4.php'];
        case 3:
            return ['System Requirements Check', '/page.3.php'];
        case 2:
            return ['Database Details', '/page.2.php'];
        default:
            return ['License agreement', '/page.1.php'];
    }
}

function build($title, $page, $step)
{
    require_once(TEMPLATES_PATH.'/header.php');
    require_once(TEMPLATES_PATH.'/submenu.php');
    require_once(TEMPLATES_PATH.'/content.header.php');
    require_once(TEMPLATES_PATH.$page);
    require_once(TEMPLATES_PATH.'/footer.php');
}
