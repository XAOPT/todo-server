<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: accept, content-type, cache-control, x-requested-with'); // для загрузки файлов

defined('APPLICATION_PATH') || define('APPLICATION_PATH', preg_replace('/\\\/', '/', realpath(dirname(__FILE__))));

ob_start();

function __autoload($path) {
    return include preg_replace('/_/', '/', $path, 1) . '.php';
}

require_once( './config.php' );

mysql_connect( DB_HOST, DB_USER, DB_PASSWORD );
mysql_select_db( DB_NAME );
mysql_query("SET NAMES '" . DB_CHARSET . "'");

$rest = new Rest();

ob_end_flush();
