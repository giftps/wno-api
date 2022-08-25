<?php
$requestMethod = $_SERVER["REQUEST_METHOD"];
include('../class/Rest.php');
$api = new Users();
switch ($requestMethod) {
	case 'POST':
		$id = '';
		if ($_POST['id']) {
			$id = $_POST['id'];
		}
		$api->getGroupMemberData($id);
		break;
	default:
		header("HTTP/1.0 405 Method Not Allowed");
		break;
}
