<?php
$requestMethod = $_SERVER["REQUEST_METHOD"];
include('../class/Rest.php');
$api = new Users();
switch ($requestMethod) {
	case 'POST':
		$group_id = '';
		if ($_POST['id']) {
			$group_id = $_POST['id'];
		}
		$api->joinOrLeaveGroup($group_id);
		break;
	default:
		// echo $requestMethod;
		header("HTTP/1.0 405 Method Not Allowed");
		break;
}
