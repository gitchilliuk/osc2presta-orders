<?php

define('PS_SHOP_PATH', 'http://localhost/prestashop/');
define('PS_WS_AUTH_KEY', 'J14PKBRW54I1R9ZTRLICEY2Q95M8C23F');
define('DEBUG', true);
$local = true;
if ($local) {
    define('DB_HOST_1', 'localhost');
    define('DB_USER_1', 'root');
    define('DB_PASSWORD_1', '');
    define('DB_NAME_1', 'osc2ps');
    define('DB_TABLE_PREFIX_1', '');
} else {
    define('DB_HOST_1', 'localhost');
    define('DB_USER_1', 'some DB user production');
    define('DB_PASSWORD_1', 'DB password production');
    define('DB_NAME_1', 'DB name production');
    define('DB_TABLE_PREFIX_1', '');
}
define("USE_API", false);//Keep it false (API version is outdated)
if ($local) {
    define('DB_HOST_2', 'localhost');
    define('DB_USER_2', 'root');
    define('DB_PASSWORD_2', '');
    define('DB_NAME_2', 'prestashop');
    define('DB_TABLE_PREFIX_2', 'ps_');
} else {
    define('DB_HOST_2', 'localhost');
    define('DB_USER_2', 'production DB user');
    define('DB_PASSWORD_2', 'production DB password');
    define('DB_NAME_2', 'production DB name');
    define('DB_TABLE_PREFIX_2', 'ps_');
}

$dbSource = mysqli_connect(DB_HOST_1, DB_USER_1, DB_PASSWORD_1, DB_NAME_1);
if (!$dbSource) {
    echo "Error: Unable to connect to Source MySQL." . PHP_EOL;
    echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
    echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
    exit;
}
$dbTarget = mysqli_connect(DB_HOST_2, DB_USER_2, DB_PASSWORD_2, DB_NAME_2);
if (!$dbTarget) {
    echo "Error: Unable to connect to Target MySQL." . PHP_EOL;
    echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
    echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
    exit;
}
//require 'PSWebServiceLibrary.php';
require 'functions.php';
