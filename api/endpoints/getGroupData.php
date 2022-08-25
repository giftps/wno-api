<?php
$requestMethod = $_SERVER["REQUEST_METHOD"];
include('../class/Rest.php');
$api = new Users();
// return print_r($_GET);
switch ($requestMethod) {
	case 'POST':
        // return var_dump($_POST);
		$group_id = '';
		if ($_POST['id']) {
			$group_id = $_POST['id'];
		}
		$api->getGroupData($group_id);
		break;
	default:
		// echo $requestMethod;
		header("HTTP/1.0 405 Method Not Allowed");
		break;
}
