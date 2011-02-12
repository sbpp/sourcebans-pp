<?php
	$ret = $GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_mods` SET `icon` = 'l4d.png' WHERE `modfolder` = 'left4dead' AND `icon` = '';");
	return true;
?>