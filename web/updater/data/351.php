<?php
	$ret2 = $GLOBALS['db']->Execute("ALTER TABLE `".DB_PREFIX."_comments` ADD FULLTEXT `commenttxt` (`commenttxt`);");
	if(!$ret2)
		return false;
    return true;
?>