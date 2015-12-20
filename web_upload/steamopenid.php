<?php

// Steam Login by @duhowpi 2015

session_start();
include_once 'init.php';
include_once 'config.php';
require_once 'includes/openid.php';

define('SB_HOST', SB_WP_URL);
define('SB_URL', SB_WP_URL);

function steamOauth() {
    $openid = new LightOpenID(SB_HOST);
    if(!$openid->mode) {
        $openid->identity = 'http://steamcommunity.com/openid';
        header("Location: " .$openid->authUrl() );
        exit();
    }
    elseif($openid->mode == 'cancel') {
        // User canceled auth.
        return false;
    } else {
        if($openid->validate()) {
            $id = $openid->identity;
            $ptn = "/^http:\/\/steamcommunity\.com\/openid\/id\/(7[0-9]{15,25}+)$/";
            preg_match($ptn, $id, $matches);

            if(!empty($matches[1])){ return $matches[1]; }
            return null;
        } else {
            // Not valid
            return false;
        }
    }
}

function convert64to32($steam_cid){
    $id = array('STEAM_0');
    $id[1] = substr($steam_cid, -1, 1) % 2 == 0 ? 0 : 1;
    $id[2] = bcsub($steam_cid, '76561197960265728');
    if(bccomp($id[2], '0') != 1)
    {
        return false;
    }
    $id[2] = bcsub($id[2], $id[1]);
    list($id[2], ) = explode('.', bcdiv($id[2], 2), 2);
    return implode(':', $id);
}

if(isset($_COOKIE['aid'])){
    header("Location: " .SB_URL);
}

$data = steamOauth();

if($data !== false){
    $data = convert64to32($data);

    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if(defined('DB_PREFIX')){ $prfx = DB_PREFIX ."_"; }else{ $prfx = ""; }

    $resultado = $mysqli->query("SELECT aid,password FROM " .$prfx ."admins WHERE authid = '" .$data ."'; ");
    if($resultado->num_rows == 1){
        while($row = $resultado->fetch_assoc()) {
            setcookie("aid", $row['aid'], time()+LOGIN_COOKIE_LIFETIME);
            setcookie("password", $row['password'], time()+LOGIN_COOKIE_LIFETIME);
        }
    }

    $mysqli->close();
}else{
    header("Location: " .SB_URL ."/index.php?p=login");
}

header("Location: " .SB_URL);