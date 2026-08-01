<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "api-helper.php";

$method = get_method();
$data = get_request_data();

if ($method === 'GET') {

	// You'd normally do stuff here...
	// Let's just send back a success message
	send_response([
		'status' => 'success',
		'message' => 'You did it, dude!',
	]);

}
?>