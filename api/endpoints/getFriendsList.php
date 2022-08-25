<?php
$requestMethod = $_SERVER["REQUEST_METHOD"];
include('../class/Rest.php');
$api = new Users();
switch ($requestMethod) {
	case 'GET-niu':
		$idu = '';
		$type = 0;
		if ($_GET['idu']) {
			$idu = $_GET['idu'];
		}
		$api->getFriendsList();
		break;
	default:
		$api->getFriendsList();
		// header("HTTP/1.0 405 Method Not Allowed");
		break;
}
