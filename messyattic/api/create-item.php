<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
/* 
POST
request
{
    naam:           string! 
    omschrijving:   string!
    huishouden:     uuid!
    kamer_id:       uuid
    loc_x:          int
    loc_y:          int
    doos_id:        uuid
    has_foto:           boolean!
}
response
{
    item_id:        uuid!
    foto_url:       url
}
*/

include "api-helper.php";

$method = get_method();
$data = get_request_data();

if ($method === 'GET') {

	// You'd normally do stuff here...
	// Let's just send back a success message

    $config = require '/home/lionsatm/config/db-config.php';
    $conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['dbname']);
    // Check connection
    if ($conn->connect_error) {
        send_response([
            'status' => 'failed',
            'message' => 'connection to database failed',
        ], 500);
        die("Connection failed: " . $conn->connect_error);
    }

    // $sql = "INSERT INTO MyGuests (firstname, lastname, email)
    // VALUES ('John', 'Doe', 'john@example.com')";
    $sql = "INSERT INTO `huishoudens`(`naam`, `omschrijving`, `foto`) 
    VALUES ('Villa kakelbond','Het huis van pipi langkous','path/to/nowhere.jpg')";

    if ($conn->query($sql) === TRUE) {
        send_response([
            'status' => 'success',
            'message' => 'Record created succesfully!',
        ]);
    } else {
        send_response([
            'status' => 'failed',
            'message' => 'record failed to be made.',
        ], 500);
    }

    $conn->close();

}
?>