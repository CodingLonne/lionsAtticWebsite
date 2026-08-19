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
    met_foto:       boolean!
    labels:         [uuid!]
    eigenaren:      [uuid!]
    eigendomstype:  string
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

if ($method === 'POST') {
    $conn = database_connect();

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