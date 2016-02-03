<?php

error_reporting(E_ALL & ~E_NOTICE);
define('THIS_SCRIPT', 'dle_avatar');
define('CSRF_PROTECTION', true);

ignore_user_abort(true);

require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');

// unserialize and access check

$data = urldecode($_GET['data']);
$hash = $_GET['hash'];

if (md5($vbulletin->config['MasterServer']['password'] . $data . $vbulletin->config['Database']['dbname']) != $hash)
{
    die('Hacking');
}

$info = @unserialize(base64_decode($data));

if (is_array($info))
{
    include_once(DIR . '/includes/class_dle_integration.php');
    $dle = DLEIntegration::getInstance($vbulletin);
    $dle->UpdatevBAvatar($info);
}



?>