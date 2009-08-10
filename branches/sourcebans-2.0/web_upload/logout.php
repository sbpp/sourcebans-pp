<?php
require_once 'init.php';

CUserManager::logout();
Util::redirect('index.php');
?>