<?php
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
?>
</ul>
        </div>
    </div>
</div>
<div id="mainwrapper">
    <div id="navigation">
        <div id="nav"></div>
        <div id="search">
            <b><u>Installation Progress</u></b><br/><br/>
            <?php
            $steps = array(
                1 => 'License Agreement',
                2 => 'Database Information',
                3 => 'System Requirements',
                4 => 'Table Creation',
                5 => 'Initial Setup'
            );

            if (isset($_GET['step']) && is_numeric($_GET['step'])) {
                foreach ($steps as $key => $value) {
                    if ($key < $_GET['step']) {
                        print '<strike>Step '.$key.': '.$value.'</strike><br/>';
                    } elseif ($key == $_GET['step']) {
                        print '<b>Step '.$key.': '.$value.'</b><br/>';
                    } elseif ($key > $_GET['step']) {
                        print 'Step '.$key.': '.$value.'<br/>';
                    }
                }
            }
            ?>
        </div>
    </div>
