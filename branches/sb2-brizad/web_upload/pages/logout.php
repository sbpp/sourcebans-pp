<?php
require_once 'api.php';

$userbank = SBConfig::getEnv('userbank');
$userbank->logout();

Util::redirect('index.php');
?>