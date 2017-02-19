<?php
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
$class = ($tabs['active'] == true) ? "active" : "nonactive";
echo '<li class="nonactive">';
CreateLink($tabs['title'], $tabs['url'], $tabs['desc']);
echo '</li>';
