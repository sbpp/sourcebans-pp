<style>
    pre {
        border: 1px solid black;
        padding: 2px;
        border-radius: 2px;
    }
</style>


<?php
const IN_SB = true;
require_once __DIR__. '/config.php';

if (defined('SB_SECRET_KEY'))
    exit('Upgrade not needed.');


$file = __DIR__ . '/config.php';
$key = base64_encode(openssl_random_pseudo_bytes(47));

$content = file_get_contents($file);
$content .= "define('SB_SECRET_KEY', '$key'); //Secret for JWT";


if (!is_writable($file) || !file_put_contents($file, $content)) {
    echo 'Error writing content to <kbd>config.php</kbd>. Please overwrite <kbd>config.php</kbd> with the following:';
    echo '<pre>';
    echo htmlspecialchars($content);
    echo '</pre>';
    exit;
}

echo '<kbd>config.php</kbd> updated correctly. Please delete this file when done.';



