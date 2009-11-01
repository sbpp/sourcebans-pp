<?php
require_once 'api.php';

$userbank = Env::get('userbank');
$userbank->logout();

Util::redirect('index.php');
?>