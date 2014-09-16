<?php
ob_start();

ini_set( "log_errors", "On" );
ini_set( "error_log", "error.log" );

define('APPLICATION_URL', 'http://office.divogames.ru/todo/');

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__)));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH),
    get_include_path(),
)));

// Define path to data directory
defined('APPLICATION_DATA')
    || define('APPLICATION_DATA', realpath(dirname(__FILE__) . '/../../data/logs'));

function __autoload($path) {
	return include str_replace('_', '/', $path) . '.php';
}

require_once( './config.php' );

mysql_connect( DB_HOST, DB_USER, DB_PASSWORD ) or $this->throwMySQLError();
mysql_select_db( DB_NAME ) or $this->throwMySQLError();
mysql_query("set names 'utf8'") or returnDatabaseError( mysql_error() );

$rest = new Rest();
$rest->process();
ob_end_flush();
