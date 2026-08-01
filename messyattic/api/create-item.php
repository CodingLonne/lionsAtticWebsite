<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Get the API method
 * @return String The API method
 */
function get_method () {
	return $_SERVER['REQUEST_METHOD'];
}

/**
 * Get data object from API data
 * @return Object The data object
 */
function get_request_data () {
	return array_merge(empty($_POST) ? array() : $_POST, (array) json_decode(file_get_contents('php://input'), true), $_GET);
}

/**
 * Send an API response
 * @param  *       $response The API response
 * @param  integer $code     The response code
 * @param  boolean $encode   If true, encode response
 */
function send_response ($response, $code = 200) {
	http_response_code($code);
	die(json_encode($response));
}

// Get the API method
$method = get_method();

// Get any data sent with the request
// Includes query parameters, post data, and body content
$data = get_request_data();

// GET request
// Get some data and respond with it
if ($method === 'GET') {

	// You'd normally do stuff here...
	// Let's just send back a success message
	send_response([
		'status' => 'success',
		'message' => 'You did it, dude!',
	]);

}
?>