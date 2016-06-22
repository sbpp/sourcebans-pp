<?php
	include_once("init.php");
	$exportpublic = (isset($GLOBALS['config']['config.exportpublic']) && $GLOBALS['config']['config.exportpublic'] == "1");
	if(!$userbank->HasAccess(ADMIN_OWNER) && !$exportpublic) {
		echo "Don't have access to this feature.";
	}
	else if(!isset($_GET['type'])) {
		echo "You have to specify the type. Only follow links!";
	}
	else {
		if($_GET['type'] == 'steam') {
			header('Content-Type: application/x-httpd-php php');
			header('Content-Disposition: attachment; filename="banned_user.cfg"');
			$bans = $GLOBALS['db']->Execute("SELECT authid FROM `".DB_PREFIX."_bans` WHERE length = '0' AND RemoveType IS NULL AND type = '0'");
			$num = $bans->RecordCount();
			for($x=0;$x<$num;$x++) {
				print("banid 0 ".$bans->fields['authid']."\r\n");
				$bans->MoveNext();
			}
		} elseif($_GET['type'] == 'ip') {
			header('Content-Type: application/x-httpd-php php');
			header('Content-Disposition: attachment; filename="banned_ip.cfg"');
			$bans = $GLOBALS['db']->Execute("SELECT ip FROM `".DB_PREFIX."_bans` WHERE length = '0' AND RemoveType IS NULL AND type = '1'");
			$num = $bans->RecordCount();
			for($x=0;$x<$num;$x++) {
				print("addip 0 ".$bans->fields['ip']."\r\n");
				$bans->MoveNext();
			}
		}
	}
?>
