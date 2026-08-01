<?php
function get_method () {
	return $_SERVER['REQUEST_METHOD'];
}

function get_request_data () {
	return array_merge(empty($_POST) ? array() : $_POST, (array) json_decode(file_get_contents('php://input'), true), $_GET);
}

function send_response ($response, $code = 200) {
	http_response_code($code);
	die(json_encode($response));
}
?>