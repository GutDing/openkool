<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

//get kOOL config
$ko_path = '../';
$ko_menu_akt = 'carddav';
require_once($ko_path.'inc/ko.inc.php');


//Mapping PHP errors to exceptions
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler('exception_error_handler', E_ERROR);


//Backends
$authBackend      = new OpenKool\DAV\DAVAuthBackend($db_connection);
$principalBackend = new OpenKool\DAV\DAVACLPrincipalBackend($db_connection);
$carddavBackend   = new OpenKool\DAV\CardDAVBackend($db_connection);
//$caldavBackend    = new Sabre\CalDAV\Backend\PDO($pdo);

//Setting up the directory tree
$nodes = array(
	new Sabre\DAVACL\PrincipalCollection($principalBackend),
	//new Sabre\CalDAV\CalendarRootNode($authBackend, $caldavBackend),
	new Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
);

//The object tree needs in turn to be passed to the server class
$server = new Sabre\DAV\Server($nodes);
$server->setBaseUri(parse_url($BASE_URL, PHP_URL_PATH).'dav/');

//Plugins
$server->addPlugin(new Sabre\DAV\Auth\Plugin($authBackend,'kOOL CardDAV Server'));
$server->addPlugin(new Sabre\DAV\Browser\Plugin());
//$server->addPlugin(new Sabre\CalDAV\Plugin());
$server->addPlugin(new Sabre\CardDAV\Plugin());
$server->addPlugin(new Sabre\DAVACL\Plugin());

//And off we go!
$server->exec();
