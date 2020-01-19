<div id="mainwrapper">
    <div id="navigation">
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

            foreach ($steps as $key => $value) {
                if ($key < $step) {
                    print '<strike>Step '.$key.': '.$value.'</strike><br/>';
                } elseif ($key == $step) {
                    print '<b>Step '.$key.': '.$value.'</b><br/>';
                } elseif ($key > $step) {
                    print 'Step '.$key.': '.$value.'<br/>';
                }
            }
            ?>
        </div>
    </div>
