<?php
require_once 'init.php';

$userbank = Env::get('userbank');
$userbank->logout();

Util::redirect('index.php');
?>