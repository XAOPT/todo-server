<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: accept, content-type, cache-control, x-requested-with'); // для загрузки файлов

defined('APPLICATION_PATH') || define('APPLICATION_PATH', preg_replace('/\\\/', '/', realpath(dirname(__FILE__))));

/*
ini_set( "log_errors", "On" );
ini_set( "error_log", "error.log" );

defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

defined('APPLICATION_DATA')
    || define('APPLICATION_DATA', realpath(dirname(__FILE__) . '/../../data/logs'));
// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH),
    get_include_path(),
)));

*/
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